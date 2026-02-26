# LightScope for OPNsense

OPNsense plugin for LightScope network security monitoring.

## Features

- Captures blocked TCP SYN packets from pf firewall via pflog0
- Configurable honeypot ports with PROXY protocol forwarding
- Uploads security telemetry to thelightscope.com for analysis
- Privacy-preserving IP randomization

## Requirements

- OPNsense 24.x or later (FreeBSD 14.x)
- pf firewall with logging enabled
- Python 3.11

## Installation

### From Package

```bash
pkg install /path/to/os-lightscope-1.0.pkg
```

### Building from Source

```bash
# Clone into OPNsense plugins directory
cd /usr/plugins/security
git clone <repo> lightscope

# Or copy files
cp -r /path/to/OPNsense/* /usr/plugins/security/lightscope/

# Build
cd /usr/plugins/security/lightscope
make package

# Install
pkg install work/pkg/os-lightscope-1.0.pkg
```

## Configuration

Configuration file: `/usr/local/etc/lightscope.conf`

```ini
[Settings]
# Database identifier - auto-generated if empty
database =

# IP randomization key - auto-generated if empty
randomization_key =

# Comma-separated honeypot ports (leave empty to disable)
honeypot_ports = 8080,2323,8443,3389,5900

# Remote honeypot server
honeypot_server = 128.9.28.79
honeypot_ssh_port = 12345
honeypot_telnet_port = 12346
```

## Usage

### Start the Service

```bash
# One-time start
service os-lightscope onestart

# Enable at boot
sysrc lightscope_enable="YES"
service os-lightscope start
```

### Check Status

```bash
# View running processes
ps aux | grep lightscope

# Check honeypot ports
sockstat -l | grep -E "8080|2323|8443|3389|5900"
```

### View Logs

Logs are output to stdout/stderr. When running via rc.d, check:
```bash
tail -f /var/log/messages | grep lightscope
```

## How It Works

1. **pf firewall** blocks unwanted traffic and logs to pflog0 interface
2. **pflog_reader** captures packets from pflog0, parses TCP SYN packets
3. **packet_handler** processes packets and prepares for upload
4. **uploader** batches and sends data to thelightscope.com
5. **honeypot** listens on configured ports and forwards connections

### Data Flow

```
pflog0 → pflog_reader → packet_handler → uploader → thelightscope.com
                                              ↑
honeypot ports → honeypot → honeypot_uploader ┘
```

### Data Storage

All data is kept in memory only. No disk writes for packet data.
- Queue max size: 100,000 items
- Batch size: 600 items (or 5 second idle flush)
- On upload failure: retry after 5 seconds

## Testing

```bash
# Load pf modules
kldload pf pflog

# Create pflog interface
ifconfig pflog0 create

# Enable pf with a test block rule
pfctl -e
echo 'block out log quick proto tcp from any to any port 9998' | pfctl -f -

# Start service
service os-lightscope onestart

# Generate blocked traffic
nc -w 1 8.8.8.8 9998

# Check logs for:
# "pflog_reader: sent X packets"
# "uploader: Sent X items"
```

## Troubleshooting

### pflog_reader not capturing packets

1. Verify pf is enabled: `pfctl -s info`
2. Verify pflog0 exists: `ifconfig pflog0`
3. Verify rules have `log` keyword: `pfctl -sr`
4. Test capture directly: `tcpdump -i pflog0 -c 5`

### Service won't start

1. Check python3 symlink exists: `ls -la /usr/local/bin/python3`
2. If missing: `ln -s /usr/local/bin/python3.11 /usr/local/bin/python3`

### Packets captured but not uploaded

1. Check network connectivity to thelightscope.com
2. Look for upload errors in logs

## Dependencies

Installed automatically via pkg:
- python311
- py311-dpkt
- py311-requests
- py311-psutil
- py311-pypcap

## Files

```
/usr/local/etc/lightscope.conf.sample    - Sample configuration
/usr/local/etc/lightscope.conf           - Active configuration (created on first run)
/usr/local/etc/rc.d/os-lightscope        - Service script
/usr/local/opnsense/scripts/lightscope/  - Python scripts
  ├── lightscope_daemon.py               - Main daemon
  ├── pflog_reader.py                    - Packet capture
  ├── honeypot.py                        - Honeypot listener
  └── uploader.py                        - Data upload
```

## Technical Notes

### pflog Header Size

FreeBSD 14.x uses a 72-byte pflog header (see `/usr/include/net/if_pflog.h`).
The IP packet starts at offset 72 in captured pflog packets.

### Honeypot PROXY Protocol

Honeypot connections are forwarded with PROXY protocol v1 header:
```
PROXY TCP4 <client_ip> <database_id> <client_port> <local_port>
```

## License

BSD 2-Clause License - see LICENSE file

## Support

- Dashboard: https://thelightscope.com
- Issues: https://github.com/Thelightscope/thelightscope/issues
