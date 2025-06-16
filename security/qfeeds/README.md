# Q-Feeds OPNsense Plugin

This plugin fetches threat intelligence feeds from Q-Feeds and creates OPNsense aliases for use in firewall rules.

## Features

- Fetches and updates IOCs from Q-Feeds
- Creates/updates OPNsense aliases (e.g., `qfeeds_malware_ip`)
- GUI for settings, logs, and advanced cleanup
- Automatic updates via cron
- Log viewing and flushing from the GUI

## Installation (Manual)

1. Copy the contents of `files/` to the corresponding locations on your OPNsense box.
2. Make sure `/usr/local/opnsense/scripts/qfeeds/update_feeds.php` is executable.
3. Save settings in the GUI to generate the cron job.

## Usage

- Go to **Services > Q-Feeds** in the OPNsense GUI.
- Enter your API token, select feeds, and set the update interval.
- Create firewall rules using the generated aliases.
- Check the Logs tab for update status and troubleshooting.

## Support

- [Q-Feeds Website](https://qfeeds.com)
- [Q-Feeds Support](mailto:support@qfeeds.com) 