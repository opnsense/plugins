#!/usr/local/bin/python3
"""
lightscope_daemon.py - Main LightScope daemon for OPNsense

This is the main entry point that:
1. Reads configuration from OPNsense model or config file
2. Spawns the pflog reader process
3. Spawns the honeypot listener process
4. Spawns the uploader process
5. Processes captured packets and sends to uploader
"""

import os
import sys
import signal
import time
import hashlib
import ipaddress
import random
import string
import configparser
import multiprocessing
import threading
from collections import deque

# Add script directory to path for imports
script_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, script_dir)

from pflog_reader import read_pflog
from honeypot import run_honeypot
from uploader import send_data, send_honeypot_data

try:
    import requests
except ImportError:
    print("Error: requests not found", file=sys.stderr)
    sys.exit(1)

try:
    import psutil
except ImportError:
    psutil = None

# Version
LS_VERSION = "opnsense-1.0"

# Configuration paths
CONFIG_FILE = "/usr/local/etc/lightscope.conf"
CONFIG_FILE_SAMPLE = "/usr/local/etc/lightscope.conf.sample"


class LightScopeConfig:
    """Configuration manager for LightScope."""

    def __init__(self, config_file=CONFIG_FILE):
        self.config_file = config_file
        self.database = ""
        self.randomization_key = ""
        self.honeypot_ports = "8080,2323,8443,3389,5900"
        self.honeypot_server = "128.9.28.79"
        self.honeypot_ssh_port = 12345
        self.honeypot_telnet_port = 12346

        self._load_or_create_config()

    def _generate_database_name(self):
        """Generate a unique database name with OPNsense prefix."""
        import datetime
        today = datetime.date.today().strftime("%Y%m%d")
        rand_part = ''.join(random.choices(string.ascii_lowercase, k=44))
        return f"opn{today}_{rand_part}"

    def _generate_randomization_key(self):
        """Generate a randomization key."""
        rand_part = ''.join(random.choices(string.ascii_lowercase, k=46))
        return f"randomization_key_{rand_part}"

    def _load_or_create_config(self):
        """Load config from file or create with defaults."""
        config = configparser.ConfigParser()

        # Try to load existing config
        if os.path.exists(self.config_file):
            config.read(self.config_file)
        else:
            # Copy from sample if available
            if os.path.exists(CONFIG_FILE_SAMPLE):
                import shutil
                shutil.copy(CONFIG_FILE_SAMPLE, self.config_file)
                config.read(self.config_file)

        # Ensure Settings section exists
        if 'Settings' not in config:
            config.add_section('Settings')

        # Load or generate database name
        self.database = config.get('Settings', 'database', fallback='').strip()
        if not self.database:
            self.database = self._generate_database_name()
            config.set('Settings', 'database', self.database)
            print(f"Generated new database name: {self.database}")

        # Load or generate randomization key
        self.randomization_key = config.get('Settings', 'randomization_key', fallback='').strip()
        if not self.randomization_key:
            self.randomization_key = self._generate_randomization_key()
            config.set('Settings', 'randomization_key', self.randomization_key)

        # Load honeypot settings
        self.honeypot_ports = config.get('Settings', 'honeypot_ports', fallback=self.honeypot_ports)
        self.honeypot_server = config.get('Settings', 'honeypot_server', fallback=self.honeypot_server)
        self.honeypot_ssh_port = config.getint('Settings', 'honeypot_ssh_port', fallback=self.honeypot_ssh_port)
        self.honeypot_telnet_port = config.getint('Settings', 'honeypot_telnet_port', fallback=self.honeypot_telnet_port)

        print(f"Loaded honeypot_ports from config: '{self.honeypot_ports}'")

        # Save config
        try:
            with open(self.config_file, 'w') as f:
                config.write(f)
        except Exception as e:
            print(f"Warning: Could not save config: {e}")

        print(f"Database: {self.database}")
        print(f"View reports at: https://thelightscope.com/light_table/{self.database}")

    def get_dict(self):
        """Return config as dictionary."""
        return {
            'database': self.database,
            'randomization_key': self.randomization_key,
            'honeypot_ports': self.honeypot_ports,
            'honeypot_server': self.honeypot_server,
            'honeypot_ssh_port': self.honeypot_ssh_port,
            'honeypot_telnet_port': self.honeypot_telnet_port
        }


def fetch_external_info():
    """Fetch external network information from thelightscope.com."""
    try:
        resp = requests.get("https://thelightscope.com/ipinfo", timeout=10)
        resp.raise_for_status()
        data = resp.json()

        queried_ip = data.get("queried_ip")
        asn_rec = data["results"].get("asn", {}).get("record", {})
        loc_rec = data["results"].get("location", {}).get("record", {})
        company_rec = data["results"].get("company", {}).get("record", {})

        return {
            "queried_ip": queried_ip,
            "ASN": asn_rec.get("asn"),
            "domain": asn_rec.get("domain"),
            "city": loc_rec.get("city"),
            "country": loc_rec.get("country"),
            "as_type": company_rec.get("as_type"),
            "type": asn_rec.get("type")
        }
    except Exception as e:
        print(f"Warning: Could not fetch external info: {e}")
        return {
            "queried_ip": "0.0.0.0",
            "ASN": "unknown",
            "domain": "unknown",
            "city": "unknown",
            "country": "unknown",
            "as_type": "unknown",
            "type": "unknown"
        }


def get_system_info():
    """Get system information."""
    import platform
    info = {
        "System": platform.system(),
        "Release": platform.release(),
        "Version": platform.version(),
        "Machine": platform.machine(),
        "processor": platform.processor(),
        "architecture": platform.architecture()[0]
    }

    if psutil:
        info["Total_Memory"] = f"{psutil.virtual_memory().total / (1024 ** 3):.2f} GB"
    else:
        info["Total_Memory"] = "unknown"

    return info


def randomize_ip(ip_str, key):
    """Randomize an IP address for privacy."""
    try:
        ip = ipaddress.ip_address(ip_str)
    except ValueError:
        return "unknown"

    def hash_segment(segment, k):
        combined = f"{segment}-{k}"
        h = hashlib.sha256(combined.encode()).hexdigest()
        return int(h[:2], 16) % 256

    orig_bytes = ip.packed
    new_bytes = bytes(hash_segment(b, key) for b in orig_bytes)

    try:
        rand_ip = ipaddress.IPv4Address(new_bytes) if ip.version == 4 else ipaddress.IPv6Address(new_bytes)
        return str(rand_ip)
    except:
        return "unknown"


def check_ip_is_private(ip_str):
    """Check if IP is private."""
    try:
        ip_obj = ipaddress.ip_address(ip_str)
        return "True" if ip_obj.is_private else "False"
    except ValueError:
        return "unknown"


class PacketProcessor:
    """Processes packets from pflog and prepares them for upload."""

    def __init__(self, config, external_info, system_info, upload_pipe):
        self.config = config
        self.external_info = external_info
        self.system_info = system_info
        self.upload_pipe = upload_pipe
        self.packet_count = 0
        self.HEARTBEAT_INTERVAL = 15 * 60  # 15 minutes

    def process_batch(self, batch):
        """Process a batch of packets from pflog."""
        for pkt in batch:
            self.packet_count += 1
            self._prepare_and_send(pkt)

    def _prepare_and_send(self, pkt):
        """Prepare packet data and send to uploader."""
        payload = {
            "db_name": self.config['database'],
            "system_time": str(pkt.packet_time),
            "ip_version": pkt.ip_version,
            "ip_ihl": pkt.ip_ihl,
            "ip_tos": pkt.ip_tos,
            "ip_len": pkt.ip_len,
            "ip_id": pkt.ip_id,
            "ip_flags": pkt.ip_flags if isinstance(pkt.ip_flags, str) else ",".join(str(v) for v in pkt.ip_flags),
            "ip_frag": pkt.ip_frag,
            "ip_ttl": pkt.ip_ttl,
            "ip_proto": pkt.ip_proto,
            "ip_chksum": pkt.ip_chksum,
            "ip_src": pkt.ip_src,
            "ip_dst_randomized": randomize_ip(pkt.ip_dst, self.config['randomization_key']),
            "ip_options": pkt.ip_options if isinstance(pkt.ip_options, str) else ",".join(str(v) for v in pkt.ip_options),
            "tcp_sport": pkt.tcp_sport,
            "tcp_dport": pkt.tcp_dport,
            "tcp_seq": pkt.tcp_seq,
            "tcp_ack": pkt.tcp_ack,
            "tcp_dataofs": pkt.tcp_dataofs,
            "tcp_reserved": pkt.tcp_reserved,
            "tcp_flags": pkt.tcp_flags,
            "tcp_window": pkt.tcp_window,
            "tcp_chksum": pkt.tcp_chksum,
            "tcp_urgptr": pkt.tcp_urgptr,
            "tcp_options": "",
            "ext_dst_ip_country": self.external_info.get('country', 'unknown'),
            "type": self.external_info.get('type', 'unknown'),
            "ASN": self.external_info.get('ASN', 'unknown'),
            "domain": self.external_info.get('domain', 'unknown'),
            "city": self.external_info.get('city', 'unknown'),
            "as_type": self.external_info.get('as_type', 'unknown'),
            "ip_dst_is_private": check_ip_is_private(pkt.ip_dst),
            "external_is_private": check_ip_is_private(self.external_info.get('queried_ip', '')),
            "open_ports": "",
            "previously_open_ports": "",
            "interface": "pflog0",
            "internal_ip_randomized": randomize_ip(pkt.ip_dst, self.config['randomization_key']),
            "external_ip_randomized": randomize_ip(self.external_info.get('queried_ip', ''), self.config['randomization_key']),
            "System_info": self.system_info.get('System', 'OPNsense'),
            "Release_info": self.system_info.get('Release', 'unknown'),
            "Version_info": self.system_info.get('Version', 'unknown'),
            "Machine_info": self.system_info.get('Machine', 'unknown'),
            "Total_Memory": self.system_info.get('Total_Memory', 'unknown'),
            "processor": self.system_info.get('processor', 'unknown'),
            "architecture": self.system_info.get('architecture', 'unknown'),
            "honeypot_status": "False",
            "payload": "N/A",
            "ls_version": LS_VERSION
        }

        try:
            self.upload_pipe.send(payload)
        except Exception as e:
            print(f"Error sending packet data: {e}", flush=True)

    def _send_heartbeat(self):
        """Send heartbeat message."""
        heartbeat = {
            "db_name": "heartbeats",
            "unwanted_db": self.config['database'],
            "pkts_last_hb": self.packet_count,
            "ext_dst_ip_country": self.external_info.get('country', 'unknown'),
            "type": self.external_info.get('type', 'unknown'),
            "ASN": self.external_info.get('ASN', 'unknown'),
            "domain": self.external_info.get('domain', 'unknown'),
            "city": self.external_info.get('city', 'unknown'),
            "as_type": self.external_info.get('as_type', 'unknown'),
            "external_is_private": check_ip_is_private(self.external_info.get('queried_ip', '')),
            "open_ports": self.config['honeypot_ports'],
            "previously_open_ports": "",
            "interface": "pflog0",
            "internal_ip_randomized": "",
            "external_ip_randomized": randomize_ip(self.external_info.get('queried_ip', ''), self.config['randomization_key']),
            "System_info": self.system_info.get('System', 'OPNsense'),
            "Release_info": self.system_info.get('Release', 'unknown'),
            "Version_info": self.system_info.get('Version', 'unknown'),
            "Machine_info": self.system_info.get('Machine', 'unknown'),
            "Total_Memory": self.system_info.get('Total_Memory', 'unknown'),
            "processor": self.system_info.get('processor', 'unknown'),
            "architecture": self.system_info.get('architecture', 'unknown'),
            "open_honeypot_ports": self.config['honeypot_ports'],
            "ls_version": LS_VERSION
        }

        try:
            self.upload_pipe.send(heartbeat)
            self.packet_count = 0
            print("Sent heartbeat", flush=True)
        except Exception as e:
            print(f"Error sending heartbeat: {e}", flush=True)


def packet_handler(pflog_pipe, upload_pipe, config, external_info, system_info):
    """
    Process packets from pflog reader and send to uploader.

    This runs in its own process.
    """
    processor = PacketProcessor(config, external_info, system_info, upload_pipe)

    print("packet_handler: Started", flush=True)

    # Send initial heartbeat at startup
    processor._send_heartbeat()

    # Start a background thread for periodic heartbeats
    def heartbeat_loop():
        while True:
            time.sleep(processor.HEARTBEAT_INTERVAL)
            processor._send_heartbeat()

    hb_thread = threading.Thread(target=heartbeat_loop, daemon=True)
    hb_thread.start()

    while True:
        try:
            batch = pflog_pipe.recv()
            processor.process_batch(batch)
        except (EOFError, OSError):
            print("packet_handler: Pipe closed, exiting", flush=True)
            break
        except Exception as e:
            print(f"packet_handler: Error: {e}", flush=True)
            time.sleep(1)


def main():
    """Main entry point for LightScope daemon."""
    print(f"LightScope for OPNsense v{LS_VERSION}", flush=True)
    print("Starting...", flush=True)

    # Write PID file
    pid = os.getpid()
    try:
        with open("/var/run/lightscope.pid", "w") as f:
            f.write(str(pid))
    except Exception as e:
        print(f"Warning: Could not write PID file: {e}")

    # Load configuration
    config = LightScopeConfig()
    config_dict = config.get_dict()

    # Fetch external network info
    print("Fetching external network information...", flush=True)
    external_info = fetch_external_info()
    print(f"External IP: {external_info.get('queried_ip', 'unknown')}", flush=True)

    # Get system info
    system_info = get_system_info()

    # Create pipes
    pflog_consumer, pflog_producer = multiprocessing.Pipe(duplex=False)
    upload_consumer, upload_producer = multiprocessing.Pipe(duplex=False)
    hp_upload_consumer, hp_upload_producer = multiprocessing.Pipe(duplex=False)

    # Start processes
    processes = []

    # 1. pflog reader
    p_pflog = multiprocessing.Process(
        target=read_pflog,
        args=(pflog_producer,),
        name="pflog_reader"
    )
    p_pflog.start()
    processes.append(p_pflog)
    print("Started pflog reader", flush=True)

    # 2. Packet handler
    p_handler = multiprocessing.Process(
        target=packet_handler,
        args=(pflog_consumer, upload_producer, config_dict, external_info, system_info),
        name="packet_handler"
    )
    p_handler.start()
    processes.append(p_handler)
    print("Started packet handler", flush=True)

    # 3. Data uploader
    p_uploader = multiprocessing.Process(
        target=send_data,
        args=(upload_consumer,),
        name="uploader"
    )
    p_uploader.start()
    processes.append(p_uploader)
    print("Started uploader", flush=True)

    # 4. Honeypot uploader
    p_hp_uploader = multiprocessing.Process(
        target=send_honeypot_data,
        args=(hp_upload_consumer,),
        name="honeypot_uploader"
    )
    p_hp_uploader.start()
    processes.append(p_hp_uploader)
    print("Started honeypot uploader", flush=True)

    # 5. Honeypot listener
    if config_dict.get('honeypot_ports'):
        p_honeypot = multiprocessing.Process(
            target=run_honeypot,
            args=(config_dict, hp_upload_producer, config_dict['database']),
            name="honeypot"
        )
        p_honeypot.start()
        processes.append(p_honeypot)
        print("Started honeypot listener", flush=True)
    else:
        print("Honeypot disabled (no ports configured)", flush=True)

    # Signal handler for clean shutdown
    def shutdown(signum, frame):
        print("\nShutting down...", flush=True)
        for p in processes:
            if p.is_alive():
                p.terminate()
        for p in processes:
            p.join(timeout=5)
        try:
            os.remove("/var/run/lightscope.pid")
        except:
            pass
        sys.exit(0)

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)

    print("LightScope is running. Press Ctrl+C to stop.", flush=True)

    # Monitor processes
    while True:
        time.sleep(60)

        for p in processes:
            if not p.is_alive():
                print(f"Process {p.name} died, restarting...", flush=True)
                # For now, just exit and let the service manager restart us
                shutdown(None, None)


if __name__ == "__main__":
    multiprocessing.freeze_support()
    main()
