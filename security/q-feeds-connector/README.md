# plugin to connect to QFeeds threat platform

Register for a token at : https://qfeeds.com/opnsense/

# command line control

Settings are persisted in `/usr/local/etc/qfeeds.conf`, using the following format:

```
[api]
key=tip_xxxxxxxx
```

Our commandline tool contains all actions used by the UI, which is practical for debuggging.

```
usage: qfeedsctl.py [-h] [--target_dir TARGET_DIR] [-f] [-v] [{fetch_index,fetch,show_index,firewall_load,update,stats} ...]

positional arguments:
  {fetch_index,fetch,show_index,firewall_load,update,stats}

options:
  -h, --help            show this help message and exit
  --target_dir TARGET_DIR
  -f                    forced (auto index)
  -v                    verbose output
```

The index is the driver for most actions, which is a json encoded file in `/var/db/qfeeds-tables/index.json`.

Actions supported:

*   fetch_index --> download the index file
*   fetch   --> download the lists
*   firewall_load  --> collect ip lists into pre-defined firewall tables
*   update  [sleep when almost time] --> run fetch_index --> fetch --> firewall_load (to be used by cron)
*   show_index  --> dumps the index
*   stats --> dumps feed information
*   logs --> dumps firewall log information for aliases offered by Q-Feeds


Example usage:

```
/usr/local/opnsense/scripts/qfeeds/qfeedsctl.py update
```

**Fetch index and update lists when updated remotely**
