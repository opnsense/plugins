#!/bin/sh

DNSBL_APPLY_DNSBL_SCRIPT=${DNSBL_APPLY_DNSBL_SCRIPT:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsbl.py}
DNSBL_APPLY_STATUS_SCRIPT=${DNSBL_APPLY_STATUS_SCRIPT:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblStatus.py}
DNSBL_APPLY_PLUGINCTL=${DNSBL_APPLY_PLUGINCTL:-/usr/local/sbin/pluginctl}
DNSBL_APPLY_LOCK_DIR=${DNSBL_APPLY_LOCK_DIR:-/var/run/bind/dnsbl-apply.lock}
DNSBL_APPLY_LOGGER=${DNSBL_APPLY_LOGGER:-logger}
DNSBL_APPLY_SLEEP=${DNSBL_APPLY_SLEEP:-sleep}
DNSBL_APPLY_TERMINAL_WAIT_SECONDS=${DNSBL_APPLY_TERMINAL_WAIT_SECONDS:-120}

release_lock()
{
    rm -f "${DNSBL_APPLY_LOCK_DIR}/pid"
    rmdir "${DNSBL_APPLY_LOCK_DIR}"
}

if ! mkdir "${DNSBL_APPLY_LOCK_DIR}" 2>/dev/null; then
    "${DNSBL_APPLY_LOGGER}" -p daemon.notice -t named \
        "DNSBL apply ignored because another DNSBL operation is already running."
    exit 0
fi
printf '%s\n' "$$" > "${DNSBL_APPLY_LOCK_DIR}/pid"
trap release_lock EXIT HUP INT TERM

"${DNSBL_APPLY_DNSBL_SCRIPT}" "$@" || exit $?
"${DNSBL_APPLY_STATUS_SCRIPT}" starting "BIND is loading DNSBL/RPZ; monitoring Memory Guard."
"${DNSBL_APPLY_PLUGINCTL}" -c bind_start || {
    "${DNSBL_APPLY_STATUS_SCRIPT}" failed "BIND could not start after DNSBL download."
    exit 1
}

elapsed=0
while [ "${elapsed}" -lt "${DNSBL_APPLY_TERMINAL_WAIT_SECONDS}" ]; do
    stage=$("${DNSBL_APPLY_STATUS_SCRIPT}" --stage)
    case "${stage}" in
        dnsbl_active|guard_recovered|disabled|failed)
            exit 0
            ;;
    esac
    "${DNSBL_APPLY_SLEEP}" 1
    elapsed=$((elapsed + 1))
done

"${DNSBL_APPLY_STATUS_SCRIPT}" failed "DNSBL startup monitoring did not reach a terminal state."
exit 1
