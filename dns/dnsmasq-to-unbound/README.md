# Dnsmasq to Unbound DNS Registration

This OPNsense plugin automatically registers dnsmasq DHCP leases and static host entries in Unbound DNS, enabling local hostname resolution for DHCP clients.

## Features

- Watches dnsmasq lease file and static hosts for changes
- Registers A and PTR records in Unbound DNS
- Supports multiple domains via dnsmasq's IP-range-to-domain mapping
- Deduplicates records (static entries take precedence over leases)
- Automatic cleanup of stale records
- System status notifications in OPNsense web UI
- Periodic reconciliation to handle Unbound restarts

## Requirements

- OPNsense with Unbound DNS resolver enabled (remote control is enabled by default)
- dnsmasq plugin installed and configured with DHCP

## Installation

Install via the OPNsense plugin system or manually:

```
pkg install os-dnsmasq-to-unbound
```

## Configuration

Navigate to **Services > Dnsmasq to Unbound** in the OPNsense web UI.

### Settings

| Option | Description |
|--------|-------------|
| Enable | Enable/disable the DNS registration service |
| Watch Leases | Register DNS entries for DHCP leases |
| Watch Static | Register DNS entries for static host mappings |
| Domain Filter | Limit registration to specific domains (comma-separated) |

### Domain Configuration

The plugin reads domain configuration from dnsmasq's configuration:

- **Global domain**: `domain=lan` in dnsmasq.conf
- **Range-specific domains**: `domain=guest,192.168.20.1,192.168.20.254`

If no domain is configured in dnsmasq, DHCP leases cannot be registered (static hosts with explicit domains will still work).

## How It Works

1. The daemon watches `/var/db/dnsmasq.leases` and `/var/etc/dnsmasq-hosts` for changes
2. When changes are detected, it parses the files and compares with current Unbound state
3. New records are added, changed records are updated, and stale records are removed
4. Records are marked with a TXT record (`managed-by=dnsmasq-to-unbound`) for identification
5. Every 5 minutes, a full reconciliation runs to catch any missed changes

## Troubleshooting

### Service Status

Check service status via CLI:
```
configctl dnsmasqtounbound status
```

View registered records:
```
configctl dnsmasqtounbound listrecords
```

### System Logs

Check system logs for errors:
```
grep dnsmasq_watcher /var/log/system/latest.log
```

### Common Issues

**"Unbound remote control not enabled"**
- This should not normally occur as OPNsense enables remote control by default
- Check that Unbound is running and restart if necessary

**"No domain configured in dnsmasq.conf"**
- Add `domain=lan` (or your domain) to dnsmasq configuration

**Records not appearing**
- Verify the service is running
- Check that Unbound is running and controllable
- Ensure domains match the domain filter (if configured)

### Status Notifications

The plugin reports status via OPNsense's system status indicator:

| Status | Meaning |
|--------|---------|
| OK (green) | Service running normally |
| Warning (yellow) | Some records skipped (check logs) |
| Error (red) | Service failed (check logs for details) |

## License

BSD 2-Clause License. See source files for full license text.
