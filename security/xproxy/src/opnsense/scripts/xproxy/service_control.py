#!/usr/local/bin/python3

"""
Xproxy service lifecycle manager.
Reads OPNsense config.xml, generates xray-core config, manages
xray-core and hev-socks5-tunnel processes, and configures the TUN interface.

Usage: service_control.py <start|stop|restart|reconfigure|status>
"""

import sys
import os
import re
import json
import signal
import time
import subprocess
import ipaddress
import xml.etree.ElementTree as ET
import fcntl

CONFIG_XML = '/conf/config.xml'
XRAY_BIN = '/usr/local/bin/xray'
HEV_BIN = '/usr/local/bin/hev-socks5-tunnel'
CONFIG_DIR = '/usr/local/etc/xproxy'
XRAY_CONFIG = os.path.join(CONFIG_DIR, 'config.json')
HEV_CONFIG = os.path.join(CONFIG_DIR, 'hev-socks5-tunnel.yml')
XRAY_PID = '/var/run/xproxy_xray.pid'
HEV_PID = '/var/run/xproxy_hev.pid'
LOCK_FILE = '/var/run/xproxy.lock'
ACTIVE_FLAG = '/var/run/xproxy_service.active'
LOG_FILE = '/var/log/xproxy.log'
LOG_MAX_BYTES = 2 * 1024 * 1024  # 2 MB

TUN_DEVICE_RE = re.compile(r'^tun[0-9]{1,3}$')
SUPPORTED_PROTOCOLS = ('vless', 'vmess', 'shadowsocks', 'trojan')


# ---------------------------------------------------------------------------
# Locking — prevents concurrent start/stop/reconfigure from corrupting state
# ---------------------------------------------------------------------------

_lock_fd = None


def _acquire_lock():
    global _lock_fd
    try:
        _lock_fd = open(LOCK_FILE, 'w')
        fcntl.flock(_lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except (IOError, OSError):
        log_error('xproxy: another instance is already running, waiting...')
        try:
            fcntl.flock(_lock_fd, fcntl.LOCK_EX)
        except (IOError, OSError):
            pass


def _release_lock():
    global _lock_fd
    if _lock_fd:
        try:
            fcntl.flock(_lock_fd, fcntl.LOCK_UN)
            _lock_fd.close()
        except (IOError, OSError):
            pass
        _lock_fd = None


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _safe_int(value, default, minimum=None, maximum=None):
    try:
        n = int(str(value).strip())
    except (TypeError, ValueError):
        return default
    if minimum is not None and n < minimum:
        return default
    if maximum is not None and n > maximum:
        return default
    return n


def log_error(msg):
    ts = time.strftime('%Y/%m/%d %H:%M:%S')
    line = ts + ' ' + msg + '\n'
    try:
        with open(LOG_FILE, 'a') as f:
            f.write(line)
    except OSError:
        pass
    print(msg, file=sys.stderr)


def _rotate_log():
    """Rotate log file when it exceeds LOG_MAX_BYTES instead of truncating."""
    try:
        if os.path.getsize(LOG_FILE) > LOG_MAX_BYTES:
            rotated = LOG_FILE + '.1'
            if os.path.exists(rotated):
                os.unlink(rotated)
            os.rename(LOG_FILE, rotated)
    except OSError:
        pass


# ---------------------------------------------------------------------------
# Config reading
# ---------------------------------------------------------------------------

def read_config():
    """Read xproxy settings from OPNsense config.xml."""
    try:
        tree = ET.parse(CONFIG_XML)
    except (ET.ParseError, OSError) as e:
        log_error('xproxy: failed to parse config.xml: %s' % e)
        return None
    root = tree.getroot()
    xp = root.find('.//OPNsense/xproxy')
    if xp is None:
        return None

    def txt(parent, tag, default=''):
        el = parent.find(tag)
        return el.text if el is not None and el.text else default

    general = xp.find('general')
    if general is None:
        return None

    cfg = {
        'enabled': txt(general, 'enabled', '0'),
        'active_server': txt(general, 'active_server'),
        'socks_port': _safe_int(txt(general, 'socks_port', '10808'), 10808, 1, 65535),
        'http_port': _safe_int(txt(general, 'http_port', '10809'), 10809, 1, 65535),
        'socks_listen': txt(general, 'socks_listen', '127.0.0.1'),
        'http_listen': txt(general, 'http_listen', '127.0.0.1'),
        'tun_device': txt(general, 'tun_device', 'tun9'),
        'tun_address': txt(general, 'tun_address', '10.255.0.1'),
        'tun_gateway': txt(general, 'tun_gateway', '10.255.0.2'),
        'policy_route_lan': txt(general, 'policy_route_lan', '1'),
        'log_level': 'warning',
        'bypass_ips': txt(general, 'bypass_ips', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.0/8'),
        'metrics_exporter': txt(general, 'metrics_exporter', '0'),
        'servers': [],
    }

    servers_node = xp.find('servers')
    if servers_node is not None:
        for srv in servers_node:
            if srv.tag != 'server':
                continue
            server = {
                'uuid': srv.attrib.get('uuid', ''),
                'enabled': txt(srv, 'enabled', '0'),
                'description': txt(srv, 'description'),
                'protocol': txt(srv, 'protocol', 'vless'),
                'address': txt(srv, 'address'),
                'port': _safe_int(txt(srv, 'port', '443'), 443, 1, 65535),
                'user_id': txt(srv, 'user_id'),
                'password': txt(srv, 'password'),
                'encryption': txt(srv, 'encryption', 'none'),
                'flow': txt(srv, 'flow', '').replace('_', '-'),
                'transport': txt(srv, 'transport', 'tcp'),
                'transport_host': txt(srv, 'transport_host'),
                'transport_path': txt(srv, 'transport_path'),
                'security': txt(srv, 'security', 'none'),
                'sni': txt(srv, 'sni'),
                'fingerprint': txt(srv, 'fingerprint', 'chrome'),
                'alpn': txt(srv, 'alpn'),
                'reality_pubkey': txt(srv, 'reality_pubkey'),
                'reality_short_id': txt(srv, 'reality_short_id'),
            }
            cfg['servers'].append(server)

    return cfg


def find_active_server(cfg):
    active_uuid = cfg.get('active_server', '')
    if not active_uuid:
        return None
    for srv in cfg['servers']:
        if srv['uuid'] == active_uuid:
            return srv
    return None


# ---------------------------------------------------------------------------
# Xray config generation
# ---------------------------------------------------------------------------

def build_xray_config(cfg, server):
    """Generate xray-core JSON config for the active server."""
    socks_port = cfg['socks_port']
    http_port = cfg['http_port']
    socks_listen = (cfg.get('socks_listen') or '127.0.0.1').strip() or '127.0.0.1'
    http_listen = (cfg.get('http_listen') or '127.0.0.1').strip() or '127.0.0.1'
    log_level = cfg['log_level']
    bypass_list = [s.strip() for s in cfg['bypass_ips'].split(',') if s.strip()]

    inbounds = [
        {
            "tag": "socks-in",
            "protocol": "socks",
            "listen": socks_listen,
            "port": socks_port,
            "settings": {"udp": True},
            "sniffing": {
                "enabled": True,
                "destOverride": ["http", "tls", "quic"],
                "routeOnly": True,
            },
        },
        {
            "tag": "http-in",
            "protocol": "http",
            "listen": http_listen,
            "port": http_port,
            "sniffing": {
                "enabled": True,
                "destOverride": ["http", "tls"],
                "routeOnly": True,
            },
        },
    ]

    outbound = build_outbound(server)
    outbounds = [
        outbound,
        {"tag": "direct", "protocol": "freedom"},
        {"tag": "block", "protocol": "blackhole"},
    ]

    routing_rules = []
    if bypass_list:
        routing_rules.append({
            "type": "field",
            "ip": bypass_list,
            "outboundTag": "direct",
        })

    server_addr = (server.get('address') or '').strip()
    if server_addr:
        try:
            ipaddress.ip_address(server_addr)
            routing_rules.append({
                "type": "field",
                "ip": [server_addr],
                "outboundTag": "direct",
            })
        except ValueError:
            routing_rules.append({
                "type": "field",
                "domain": ["full:" + server_addr],
                "outboundTag": "direct",
            })

    # Xray's built-in DNS prevents a routing loop: without it,
    # domainStrategy "IPIfNonMatch" resolves names through the tunnel,
    # each lookup opens new connections, and sockets/FDs spiral until
    # the kernel kills the process. "AsIs" + explicit DNS servers
    # breaks the cycle — proxy-server lookups go direct via 1.1.1.1,
    # everything else is forwarded as-is without triggering resolution.
    dns_servers = [
        "1.1.1.1",
        "8.8.8.8",
        "localhost",
    ]
    if server_addr:
        try:
            ipaddress.ip_address(server_addr)
        except ValueError:
            dns_servers.insert(0, {
                "address": "1.1.1.1",
                "domains": ["full:" + server_addr],
            })

    config = {
        "log": {"loglevel": log_level, "error": LOG_FILE},
        "dns": {
            "servers": dns_servers,
            "disableCache": False,
            "queryStrategy": "UseIPv4",
        },
        "policy": {
            "levels": {
                "0": {
                    "handshake": 4,
                    "connIdle": 15,
                    "uplinkOnly": 1,
                    "downlinkOnly": 2,
                    "bufferSize": 512,
                }
            },
        },
        "inbounds": inbounds,
        "outbounds": outbounds,
        "routing": {
            "domainStrategy": "AsIs",
            "rules": routing_rules,
        },
    }
    return config


def build_outbound(srv):
    """Build protocol-specific outbound config."""
    proto = srv['protocol']
    outbound = {"tag": "proxy", "protocol": proto}

    if proto == 'vless':
        user = {"id": srv['user_id'], "encryption": srv['encryption'] or 'none'}
        if srv['flow']:
            user["flow"] = srv['flow']
        outbound["settings"] = {
            "vnext": [{"address": srv['address'], "port": srv['port'], "users": [user]}]
        }
    elif proto == 'vmess':
        outbound["settings"] = {
            "vnext": [{
                "address": srv['address'],
                "port": srv['port'],
                "users": [{"id": srv['user_id'], "alterId": 0, "security": srv['encryption'] or 'auto'}]
            }]
        }
    elif proto == 'shadowsocks':
        outbound["settings"] = {
            "servers": [{
                "address": srv['address'],
                "port": srv['port'],
                "method": srv['encryption'] or 'aes-256-gcm',
                "password": srv['password'],
            }]
        }
    elif proto == 'trojan':
        outbound["settings"] = {
            "servers": [{
                "address": srv['address'],
                "port": srv['port'],
                "password": srv['password'],
            }]
        }

    stream = build_stream_settings(srv)
    if stream:
        outbound["streamSettings"] = stream

    return outbound


def build_stream_settings(srv):
    """Build streamSettings for transport and security."""
    stream = {"network": srv['transport'] or 'tcp'}

    security = srv['security']
    if security == 'tls':
        tls = {}
        if srv['sni']:
            tls["serverName"] = srv['sni']
        if srv['fingerprint']:
            tls["fingerprint"] = srv['fingerprint']
        if srv['alpn']:
            tls["alpn"] = [a.strip() for a in srv['alpn'].split(',') if a.strip()]
        stream["security"] = "tls"
        stream["tlsSettings"] = tls
    elif security == 'reality':
        reality = {
            "fingerprint": srv['fingerprint'] or 'chrome',
        }
        if srv['sni']:
            reality["serverName"] = srv['sni']
        if srv['reality_pubkey']:
            reality["publicKey"] = srv['reality_pubkey']
        if srv['reality_short_id']:
            reality["shortId"] = srv['reality_short_id']
        stream["security"] = "reality"
        stream["realitySettings"] = reality

    transport = srv['transport']
    if transport == 'ws':
        ws = {"path": srv['transport_path'] or '/'}
        if srv['transport_host']:
            ws["headers"] = {"Host": srv['transport_host']}
        stream["wsSettings"] = ws
    elif transport == 'grpc':
        stream["grpcSettings"] = {"serviceName": srv['transport_path'] or ''}
    elif transport == 'h2':
        h2 = {"path": srv['transport_path'] or '/'}
        if srv['transport_host']:
            h2["host"] = [srv['transport_host']]
        stream["httpSettings"] = h2
    elif transport == 'httpupgrade':
        hu = {"path": srv['transport_path'] or '/'}
        if srv['transport_host']:
            hu["host"] = srv['transport_host']
        stream["httpupgradeSettings"] = hu

    stream["sockopt"] = {
        "tcpFastOpen": True,
        "tcpNoDelay": True,
        "tcpKeepAliveInterval": 30,
    }

    return stream


def write_xray_config(config):
    os.makedirs(CONFIG_DIR, exist_ok=True)
    tmp = XRAY_CONFIG + '.tmp'
    try:
        with open(tmp, 'w') as f:
            json.dump(config, f, indent=2)
        os.rename(tmp, XRAY_CONFIG)
    except OSError as e:
        log_error('xproxy: failed to write xray config: %s' % e)
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


def _validate_xray_config():
    """Ask xray to parse the config without running; returns True on success."""
    if not os.path.isfile(XRAY_BIN):
        return True  # can't validate if binary missing; let start_xray fail
    r = subprocess.run(
        [XRAY_BIN, 'run', '-test', '-c', XRAY_CONFIG],
        capture_output=True, timeout=10, check=False,
    )
    if r.returncode != 0:
        log_error('xproxy: xray config validation failed: %s' %
                  (r.stderr.decode('utf-8', errors='replace').strip()))
        return False
    return True


# ---------------------------------------------------------------------------
# PID / process management
# ---------------------------------------------------------------------------

def read_pid(pidfile):
    try:
        with open(pidfile, 'r') as f:
            pid = int(f.read().strip())
            return pid if pid > 0 else None
    except (IOError, ValueError):
        return None


def _pid_running(pid):
    """Check whether a specific PID is alive."""
    try:
        os.kill(pid, 0)
        return True
    except OSError:
        return False


def _pid_is_ours(pid, expected_name):
    """Verify the PID belongs to the expected binary (prevents stale-PID misfire)."""
    try:
        r = subprocess.run(
            ['ps', '-o', 'comm=', '-p', str(pid)],
            capture_output=True, timeout=5, check=False,
        )
        comm = r.stdout.decode('utf-8', errors='replace').strip()
        return expected_name in comm
    except (subprocess.TimeoutExpired, OSError):
        return True  # can't verify — assume ours


def is_running(pidfile, expected_name=None):
    pid = read_pid(pidfile)
    if pid is None:
        return False
    if not _pid_running(pid):
        _cleanup_stale_pid(pidfile)
        return False
    if expected_name and not _pid_is_ours(pid, expected_name):
        _cleanup_stale_pid(pidfile)
        return False
    return True


def _cleanup_stale_pid(pidfile):
    try:
        os.unlink(pidfile)
    except OSError:
        pass


def kill_pid(pidfile, expected_name=None):
    """SIGTERM with escalation to SIGKILL after 5 seconds."""
    pid = read_pid(pidfile)
    if pid is None:
        return
    if not _pid_running(pid):
        _cleanup_stale_pid(pidfile)
        return
    if expected_name and not _pid_is_ours(pid, expected_name):
        _cleanup_stale_pid(pidfile)
        return

    try:
        os.kill(pid, signal.SIGTERM)
    except OSError:
        _cleanup_stale_pid(pidfile)
        return

    for _ in range(50):
        time.sleep(0.1)
        if not _pid_running(pid):
            _cleanup_stale_pid(pidfile)
            return

    log_error('xproxy: PID %d did not exit after SIGTERM, sending SIGKILL' % pid)
    try:
        os.kill(pid, signal.SIGKILL)
    except OSError:
        pass
    time.sleep(0.5)
    _cleanup_stale_pid(pidfile)


def _kill_orphans(binary_name):
    """Find and kill any orphaned processes matching binary_name."""
    try:
        r = subprocess.run(
            ['pgrep', '-f', binary_name],
            capture_output=True, timeout=5, check=False,
        )
        if r.returncode != 0:
            return
        for line in r.stdout.decode('utf-8', errors='replace').strip().split('\n'):
            pid_str = line.strip()
            if pid_str and pid_str.isdigit():
                pid = int(pid_str)
                if pid != os.getpid():
                    try:
                        os.kill(pid, signal.SIGTERM)
                    except OSError:
                        pass
    except (subprocess.TimeoutExpired, OSError):
        pass


# ---------------------------------------------------------------------------
# Go runtime environment
# ---------------------------------------------------------------------------

def _xray_env():
    """Go runtime tuning for sustained throughput.

    GOGC=100 keeps the default collection frequency (lower values cause
    more frequent GC pauses that choke streaming traffic).
    GOMEMLIMIT gives the runtime headroom so it can batch collections
    instead of stop-the-world pausing mid-transfer.
    """
    env = os.environ.copy()
    env['GOGC'] = '100'
    env['GOMEMLIMIT'] = '512MiB'
    return env


# ---------------------------------------------------------------------------
# Start / stop xray
# ---------------------------------------------------------------------------

def start_xray():
    if is_running(XRAY_PID, 'xray'):
        return True

    if not os.path.isfile(XRAY_BIN):
        log_error('xproxy: xray binary not found at %s' % XRAY_BIN)
        return False

    if not os.path.isfile(XRAY_CONFIG):
        log_error('xproxy: xray config not found at %s' % XRAY_CONFIG)
        return False

    if not _validate_xray_config():
        return False

    cmd = [
        '/usr/sbin/daemon', '-c', '-f', '-p', XRAY_PID,
        XRAY_BIN, 'run', '-c', XRAY_CONFIG,
    ]
    subprocess.run(cmd, env=_xray_env(), check=False)

    for _ in range(10):
        time.sleep(0.5)
        if is_running(XRAY_PID, 'xray'):
            return True

    log_error('xproxy: xray failed to start')
    return False


# ---------------------------------------------------------------------------
# hev-socks5-tunnel config + lifecycle
# ---------------------------------------------------------------------------

def _write_hev_config(cfg):
    """Generate a YAML config file for hev-socks5-tunnel."""
    device = cfg.get('tun_device') or 'tun9'
    address = (cfg.get('tun_address') or '10.255.0.1').strip()
    socks_port = cfg['socks_port']

    yml_lines = [
        "tunnel:",
        "  name: %s" % device,
        "  mtu: 8500",
        "  ipv4: %s" % address,
        "",
        "socks5:",
        "  port: %d" % socks_port,
        "  address: 127.0.0.1",
        "  udp: 'udp'",
        "",
        "misc:",
        "  task-stack-size: 86016",
        "  tcp-buffer-size: 262144",
        "  udp-read-write-timeout: 30000",
        "  connect-timeout: 5000",
        "  log-file: stderr",
        "  log-level: warn",
        "  pid-file: %s" % HEV_PID,
    ]
    yml = '\n'.join(yml_lines) + '\n'

    os.makedirs(CONFIG_DIR, exist_ok=True)
    tmp = HEV_CONFIG + '.tmp'
    try:
        with open(tmp, 'w') as f:
            f.write(yml)
        os.rename(tmp, HEV_CONFIG)
    except OSError as e:
        log_error('xproxy: failed to write hev config: %s' % e)
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


def start_hev(cfg):
    if is_running(HEV_PID, 'hev-socks5-tunnel'):
        return True

    if not os.path.isfile(HEV_BIN):
        log_error('xproxy: hev-socks5-tunnel binary not found at %s' % HEV_BIN)
        return False

    device = cfg.get('tun_device') or 'tun9'
    if not TUN_DEVICE_RE.match(device):
        log_error('xproxy: invalid TUN device name: %s' % device)
        return False

    # Also handle legacy PID file location
    for stale_pid in [HEV_PID, '/var/run/xproxy_tun2socks.pid']:
        kill_pid(stale_pid, 'hev-socks5-tunnel')

    subprocess.run(
        ['ifconfig', device, 'destroy'],
        capture_output=True, check=False,
    )

    _write_hev_config(cfg)
    cmd = ['/usr/sbin/daemon', '-c', '-f', HEV_BIN, HEV_CONFIG]

    for attempt in range(3):
        subprocess.run(cmd, check=False)
        for _ in range(20):
            time.sleep(0.5)
            if is_running(HEV_PID, 'hev-socks5-tunnel'):
                return True
        log_error('xproxy: hev-socks5-tunnel attempt %d failed' % (attempt + 1))
        kill_pid(HEV_PID, 'hev-socks5-tunnel')
        subprocess.run(
            ['ifconfig', device, 'destroy'],
            capture_output=True, check=False,
        )
        if attempt < 2:
            time.sleep(2)

    log_error('xproxy: hev-socks5-tunnel failed to start after 3 retries')
    return False


# ---------------------------------------------------------------------------
# TUN interface configuration
# ---------------------------------------------------------------------------

def _tun_exists(device):
    """Check whether the TUN device exists via ifconfig."""
    r = subprocess.run(
        ['ifconfig', device],
        capture_output=True, check=False,
    )
    return r.returncode == 0


def _tun_has_addr(device, address, gateway):
    """Check whether the TUN device has the expected address AND gateway."""
    r = subprocess.run(
        ['ifconfig', device],
        capture_output=True, check=False,
    )
    if r.returncode != 0:
        return False
    out = r.stdout.decode('utf-8', errors='replace')
    # FreeBSD shows "inet 10.255.0.1 --> 10.255.0.2" for point-to-point
    return address in out and gateway in out


def configure_tun(cfg):
    """Assign the inet address and point-to-point gateway on the TUN device.

    hev-socks5-tunnel creates the interface and sets the MTU, but on
    FreeBSD it does not assign an inet address.  We must wait for the
    device to appear, then configure local + gateway so OPNsense can
    route through XPROXY_TUN.
    """
    device = cfg.get('tun_device') or 'tun9'
    if not TUN_DEVICE_RE.match(device):
        return False
    address = (cfg.get('tun_address') or '').strip()
    gateway = (cfg.get('tun_gateway') or '').strip()
    try:
        a = ipaddress.ip_address(address)
        g = ipaddress.ip_address(gateway)
        if a.version != 4 or g.version != 4:
            log_error('xproxy: TUN addresses must be IPv4')
            return False
    except ValueError:
        log_error('xproxy: invalid TUN address=%s or gateway=%s' % (address, gateway))
        return False

    for _ in range(20):
        if _tun_exists(device):
            break
        time.sleep(0.5)
    else:
        log_error('xproxy: %s did not appear after 10s — cannot configure' % device)
        return False

    if _tun_has_addr(device, address, gateway):
        return True

    r = subprocess.run(
        ['ifconfig', device, 'inet', address, gateway, 'up'],
        capture_output=True, check=False,
    )
    if r.returncode != 0:
        stderr = r.stderr.decode('utf-8', errors='replace').strip()
        log_error('xproxy: ifconfig %s failed: %s' % (device, stderr))
        return False

    if not _tun_has_addr(device, address, gateway):
        log_error('xproxy: %s address/gateway not assigned after ifconfig' % device)
        return False

    return True


# ---------------------------------------------------------------------------
# Stop / cleanup
# ---------------------------------------------------------------------------

def stop_services(cfg):
    """Stop hev-socks5-tunnel and xray, destroy TUN device, clean orphans."""
    kill_pid(HEV_PID, 'hev-socks5-tunnel')
    # Handle legacy PID file
    legacy_pid = '/var/run/xproxy_tun2socks.pid'
    if os.path.exists(legacy_pid):
        kill_pid(legacy_pid, 'hev-socks5-tunnel')

    device = (cfg.get('tun_device', 'tun9') if cfg else 'tun9') or 'tun9'
    if TUN_DEVICE_RE.match(device):
        subprocess.run(
            ['ifconfig', device, 'destroy'],
            capture_output=True, check=False,
        )

    kill_pid(XRAY_PID, 'xray')

    _kill_orphans('hev-socks5-tunnel')
    _kill_orphans(XRAY_BIN)


# ---------------------------------------------------------------------------
# Filter reload (detached to avoid configd deadlock)
# ---------------------------------------------------------------------------

def schedule_filter_reload():
    """Spawn a detached filter reload that runs after this configd action exits.

    Calling ``configctl filter reload`` directly inside a configd action
    creates a nested configd call that deadlocks during boot (configd
    waits for the action to finish while the action waits for configd to
    process the filter reload).  By spawning the process detached, it
    executes after the current action returns and configd is free.
    """
    subprocess.Popen(
        ['/usr/local/sbin/configctl', 'filter', 'reload'],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        stdin=subprocess.DEVNULL,
        start_new_session=True,
    )


# ---------------------------------------------------------------------------
# sysctl network tuning
# ---------------------------------------------------------------------------

_SYSCTL_TUNABLES = {
    'kern.ipc.maxsockbuf': '16777216',
    'net.inet.tcp.recvbuf_max': '8388608',
    'net.inet.tcp.sendbuf_max': '8388608',
    'net.inet.tcp.recvspace': '262144',
    'net.inet.tcp.sendspace': '262144',
    'net.inet.tcp.fast_finwait2_recycle': '1',
    'net.inet.tcp.finwait2_timeout': '5000',
}


def _apply_sysctl_tuning():
    """Apply TCP buffer tuning at runtime (idempotent)."""
    subprocess.run(['kldload', 'cc_cdg'], capture_output=True, check=False)
    args = ['sysctl'] + ['%s=%s' % (k, v) for k, v in _SYSCTL_TUNABLES.items()]
    subprocess.run(args, capture_output=True, check=False)


# ---------------------------------------------------------------------------
# Actions
# ---------------------------------------------------------------------------

def do_start():
    cfg = read_config()
    if cfg is None or cfg['enabled'] != '1':
        return
    server = find_active_server(cfg)
    if server is None:
        log_error('xproxy: no server matches the active selection — '
                  'go to General tab and select a server')
        return
    if not (server.get('address') or '').strip():
        log_error('xproxy: active server has no address')
        return
    if server.get('protocol') not in SUPPORTED_PROTOCOLS:
        log_error('xproxy: unsupported protocol %r' % (server.get('protocol'),))
        return

    _apply_sysctl_tuning()

    xray_config = build_xray_config(cfg, server)
    write_xray_config(xray_config)

    if not start_xray():
        return

    if cfg.get('policy_route_lan', '1') != '0' and os.path.isfile(HEV_BIN):
        if start_hev(cfg):
            configure_tun(cfg)

    _set_active_flag()
    schedule_filter_reload()

    if cfg.get('metrics_exporter', '0') == '1':
        _start_exporter()
    else:
        _stop_exporter()


def _set_active_flag():
    """Create the runtime flag file indicating the service is up."""
    try:
        with open(ACTIVE_FLAG, 'w') as f:
            f.write(str(os.getpid()))
    except OSError:
        pass


def _clear_active_flag():
    """Remove the runtime flag file."""
    try:
        os.unlink(ACTIVE_FLAG)
    except OSError:
        pass


EXPORTER_PID = '/var/run/tunbridge_exporter.pid'


def _start_exporter():
    """Start the Prometheus exporter with auto-restart."""
    exporter = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tunbridge_exporter.py')
    if not os.path.isfile(exporter):
        return
    pid = read_pid(EXPORTER_PID)
    if pid and _pid_running(pid):
        return
    subprocess.Popen(
        ['/usr/sbin/daemon', '-r', '-p', EXPORTER_PID,
         '/usr/local/bin/python3', exporter],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        stdin=subprocess.DEVNULL,
    )


def _stop_exporter():
    """Stop the Prometheus exporter if running."""
    kill_pid(EXPORTER_PID, 'tunbridge_exporter')


def do_stop():
    cfg = read_config()
    stop_services(cfg)
    _stop_exporter()
    _clear_active_flag()
    schedule_filter_reload()


def do_reconfigure():
    """Hot-reload: restart only xray-core, keep tunnel alive to minimise downtime."""
    cfg = read_config()
    _rotate_log()

    if cfg is None or cfg['enabled'] != '1':
        stop_services(cfg)
        _stop_exporter()
        _clear_active_flag()
        schedule_filter_reload()
        return

    server = find_active_server(cfg)
    if server is None:
        log_error('xproxy: no server matches the active selection — '
                  'go to General tab and select a server')
        stop_services(cfg)
        _stop_exporter()
        _clear_active_flag()
        schedule_filter_reload()
        return

    if not (server.get('address') or '').strip():
        log_error('xproxy: active server has no address')
        stop_services(cfg)
        _stop_exporter()
        _clear_active_flag()
        schedule_filter_reload()
        return

    if server.get('protocol') not in SUPPORTED_PROTOCOLS:
        log_error('xproxy: unsupported protocol %r' % (server.get('protocol'),))
        stop_services(cfg)
        _stop_exporter()
        _clear_active_flag()
        schedule_filter_reload()
        return

    _apply_sysctl_tuning()

    xray_config = build_xray_config(cfg, server)
    write_xray_config(xray_config)

    kill_pid(XRAY_PID, 'xray')
    _kill_orphans(XRAY_BIN)
    if not start_xray():
        return

    policy_route = cfg.get('policy_route_lan', '1') != '0'
    hev_running = is_running(HEV_PID, 'hev-socks5-tunnel')

    if policy_route and os.path.isfile(HEV_BIN):
        if not hev_running:
            if start_hev(cfg):
                configure_tun(cfg)
        else:
            configure_tun(cfg)
    elif hev_running:
        kill_pid(HEV_PID, 'hev-socks5-tunnel')
        _kill_orphans('hev-socks5-tunnel')
        device = (cfg.get('tun_device', 'tun9') or 'tun9')
        if TUN_DEVICE_RE.match(device):
            subprocess.run(
                ['ifconfig', device, 'destroy'],
                capture_output=True, check=False,
            )

    _set_active_flag()
    schedule_filter_reload()

    if cfg.get('metrics_exporter', '0') == '1':
        _start_exporter()
    else:
        _stop_exporter()


def do_status():
    xray_up = is_running(XRAY_PID, 'xray')
    tun_up = is_running(HEV_PID, 'hev-socks5-tunnel')
    if xray_up and tun_up:
        print("xproxy is running")
    elif xray_up:
        print("xproxy is running (xray-core only, tunnel not active)")
    else:
        print("xproxy is not running")


def main():
    if len(sys.argv) < 2:
        print("Usage: service_control.py <start|stop|restart|reconfigure|status>")
        sys.exit(1)

    action = sys.argv[1]

    if action == 'status':
        do_status()
        return

    _acquire_lock()
    try:
        if action == 'start':
            do_start()
        elif action == 'stop':
            do_stop()
        elif action == 'restart':
            do_stop()
            do_start()
        elif action == 'reconfigure':
            do_reconfigure()
        else:
            print("Unknown action: " + action)
            sys.exit(1)
    finally:
        _release_lock()


if __name__ == '__main__':
    main()
