#!/bin/sh

# During initial RPZ loading named can allocate substantially more memory than
# it needs after startup. Preserve enough free RAM for the firewall and its
# essential services by stopping named before the kernel OOM killer intervenes.

NAMED_GUARD_DNSBL_FILE=${NAMED_GUARD_DNSBL_FILE:-/usr/local/etc/namedb/dnsbl.inc}
NAMED_GUARD_RC_CONF=${NAMED_GUARD_RC_CONF:-/etc/rc.conf.d/named}
NAMED_GUARD_DEFAULT_MIN_FREE_MB=${NAMED_GUARD_DEFAULT_MIN_FREE_MB:-300}
NAMED_GUARD_TIMEOUT_SECONDS=${NAMED_GUARD_TIMEOUT_SECONDS:-90}
NAMED_GUARD_SAMPLE_SECONDS=${NAMED_GUARD_SAMPLE_SECONDS:-0.1}
NAMED_GUARD_GETCONF=${NAMED_GUARD_GETCONF:-getconf}
NAMED_GUARD_LOGGER=${NAMED_GUARD_LOGGER:-logger}
NAMED_GUARD_PGREP=${NAMED_GUARD_PGREP:-pgrep}
NAMED_GUARD_PS=${NAMED_GUARD_PS:-ps}
NAMED_GUARD_RECOVER=${NAMED_GUARD_RECOVER:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblMemoryRecovery.sh}
NAMED_GUARD_SLEEP=${NAMED_GUARD_SLEEP:-sleep}
NAMED_GUARD_SYSCTL=${NAMED_GUARD_SYSCTL:-sysctl}
NAMED_GUARD_KILL=${NAMED_GUARD_KILL:-/bin/kill}
NAMED_GUARD_STATUS=${NAMED_GUARD_STATUS:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblStatus.py}

dnsbl_enabled()
{
    if [ "${NAMED_GUARD_ENABLED:-}" = "1" ]; then
        return 0
    fi

    grep -q '^named_dnsbl="[^"]' "${NAMED_GUARD_RC_CONF}" 2>/dev/null
}

minimum_free_kb()
{
    if [ -n "${NAMED_GUARD_MIN_FREE_KB:-}" ]; then
        echo "${NAMED_GUARD_MIN_FREE_KB}"
        return
    fi

    memory_guard_mb=$(sed -n 's/^named_memory_guard_mb="\([0-9][0-9]*\)"$/\1/p' "${NAMED_GUARD_RC_CONF}" 2>/dev/null | head -n 1)
    case "${memory_guard_mb}" in
        ''|*[!0-9]*) memory_guard_mb=${NAMED_GUARD_DEFAULT_MIN_FREE_MB} ;;
    esac
    echo $((memory_guard_mb * 1024))
}

stop_named()
{
    local pid="$1"
    local free_kb="$2"
    local rss_kb

    rss_kb=$("${NAMED_GUARD_PS}" -o rss= -p "${pid}" 2>/dev/null | tr -d ' ')
    "${NAMED_GUARD_STATUS}" guard_recovered "Memory Guard stopped DNSBL/RPZ loading and is restarting BIND without DNSBL."
    "${NAMED_GUARD_LOGGER}" -p daemon.crit -t named \
        "DNSBL startup memory guard stopped named (pid ${pid}): ${free_kb} KiB free, below the ${NAMED_GUARD_MIN_FREE_KB} KiB minimum; RSS ${rss_kb:-unknown} KiB."
    "${NAMED_GUARD_KILL}" -TERM "${pid}"

    # Give named a moment to exit cleanly before reclaiming memory decisively.
    "${NAMED_GUARD_SLEEP}" 1
    if "${NAMED_GUARD_PGREP}" -o named >/dev/null 2>&1; then
        "${NAMED_GUARD_KILL}" -KILL "${pid}"
    fi

    "${NAMED_GUARD_RECOVER}" "${pid}"
}

if [ ! -s "${NAMED_GUARD_DNSBL_FILE}" ] || ! dnsbl_enabled; then
    "${NAMED_GUARD_STATUS}" disabled "DNSBL/RPZ is disabled."
    exit 0
fi

named_pid=${1:-}
NAMED_GUARD_MIN_FREE_KB=$(minimum_free_kb)
case "${NAMED_GUARD_MIN_FREE_KB}" in
    ''|*[!0-9]*) exit 0 ;;
esac
if [ "${NAMED_GUARD_MIN_FREE_KB}" -eq 0 ]; then
    exit 0
fi
page_size=$("${NAMED_GUARD_GETCONF}" PAGESIZE 2>/dev/null) || exit 0
case "${page_size}" in
    ''|*[!0-9]*) exit 0 ;;
esac

minimum_free_pages=$((NAMED_GUARD_MIN_FREE_KB * 1024 / page_size))
samples=$((NAMED_GUARD_TIMEOUT_SECONDS * 10))
sample=0

while [ "${sample}" -lt "${samples}" ]; do
    pid=$("${NAMED_GUARD_PGREP}" -o named 2>/dev/null) || {
        "${NAMED_GUARD_SLEEP}" "${NAMED_GUARD_SAMPLE_SECONDS}"
        sample=$((sample + 1))
        continue
    }
    if [ -n "${named_pid}" ] && [ "${pid}" != "${named_pid}" ]; then
        exit 0
    fi
    free_pages=$("${NAMED_GUARD_SYSCTL}" -n vm.stats.vm.v_free_count 2>/dev/null) || break
    case "${free_pages}" in
        ''|*[!0-9]*) break ;;
    esac

    if [ "${free_pages}" -lt "${minimum_free_pages}" ]; then
        stop_named "${pid}" $((free_pages * page_size / 1024))
        exit 1
    fi

    "${NAMED_GUARD_SLEEP}" "${NAMED_GUARD_SAMPLE_SECONDS}"
    sample=$((sample + 1))
done

"${NAMED_GUARD_STATUS}" dnsbl_active "BIND loaded DNSBL/RPZ successfully."

exit 0
