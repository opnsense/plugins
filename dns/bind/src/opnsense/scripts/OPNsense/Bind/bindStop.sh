#!/bin/sh

# Stop the DHCP watcher before named, then discard journals for watcher-managed
# zones. Zone templates regenerate the static database from the model; keeping
# an older dynamic-update journal would make BIND reject that new base file.

WATCHER_PIDFILE="/var/run/bind_dhcplease.pid"
WATCHER_CONFIG="/usr/local/etc/bind/dhcpwatcher.conf"
ZONE_DIR="/usr/local/etc/namedb/primary"
STATE_FILE="/var/cache/bind/dhcplease_state.json"

if [ -r "$WATCHER_PIDFILE" ]; then
    watcher_pid=$(tr -cd '0-9' < "$WATCHER_PIDFILE")
    if [ -n "$watcher_pid" ] && kill -0 "$watcher_pid" 2>/dev/null; then
        kill "$watcher_pid"
        wait_count=0
        while kill -0 "$watcher_pid" 2>/dev/null && [ "$wait_count" -lt 50 ]; do
            sleep 0.1
            wait_count=$((wait_count + 1))
        done
        if kill -0 "$watcher_pid" 2>/dev/null; then
            logger -t bind -p daemon.warning "DHCP watcher did not stop cleanly; terminating it before regenerating zones"
            kill -KILL "$watcher_pid"
        fi
    fi
    rm -f "$WATCHER_PIDFILE"
fi

/usr/local/etc/rc.d/named stop

# The rendered watcher config is empty while BIND is disabled. Also read the
# saved model so stale journals are removed when BIND is enabled again.
model_zones=$(/usr/local/bin/php -r '
$config = simplexml_load_file("/conf/config.xml");
if (!isset($config->OPNsense->bind)) {
    exit;
}
$domains = [];
foreach ($config->OPNsense->bind->domain->domains->domain as $domain) {
    $domains[(string)$domain["uuid"]] = (string)$domain->domainname;
    if ((string)$domain->type === "reverse") {
        echo (string)$domain->domainname, PHP_EOL;
    }
}
foreach ($config->OPNsense->bind->watcher->mappings->mapping as $mapping) {
    $uuid = (string)$mapping->hostname_suffix;
    if (isset($domains[$uuid])) {
        echo $domains[$uuid], PHP_EOL;
    }
}
' 2>/dev/null)

{
    [ -r "$WATCHER_CONFIG" ] && sed -n 's/^[[:space:]]*hostname_suffix[[:space:]]*=[[:space:]]*//p' "$WATCHER_CONFIG"
    printf '%s\n' "$model_zones"
} |
    sort -u |
    while IFS= read -r zone; do
        case "$zone" in
            '' | *[!A-Za-z0-9.-]* | .*)
                continue
                ;;
        esac

        zonepath="$ZONE_DIR/$zone.db"
        if [ -e "$zonepath.jnl" ] || [ -e "$zonepath.jnw" ] || [ -e "$zonepath.jbk" ]; then
            logger -t bind -p daemon.notice "clearing dynamic update journal for $zone before regenerating its zone file"
            rm -f "$zonepath.jnl" "$zonepath.jnw" "$zonepath.jbk"
        fi
    done

# The journals are rebuilt from active DHCP leases on the next watcher start,
# so persisted "already applied" state must not suppress that replay.
rm -f "$STATE_FILE"
