#!/bin/sh

if [ -n "${PKG_CALL_LOG:-}" ]
then
    printf '%s\n' "$*" >> "$PKG_CALL_LOG"
fi

case "$1" in
    update) exit 0 ;;
    install)
        if [ "${3:-}" = python3 ] && [ -n "${PYTHON_COMMAND:-}" ]
        then
            printf '%s\n' '#!/bin/sh' 'exec python3 "$@"' > "$PYTHON_COMMAND"
            chmod 0755 "$PYTHON_COMMAND"
        fi
        exit 0
        ;;
    query)
        if [ "${4:-}" = '%n\t%v\t%o' ]
        then
            case "$3" in
                *bind-tools*) printf 'bind-tools\t%s\t%s\n' "${PKG_BIND_TOOLS_VERSION:-9.20.26}" "${PKG_BIND_TOOLS_ORIGIN:-dns/bind-tools}" ;;
                *) printf 'bind920\t%s\t%s\n' "${PKG_BIND_VERSION:-9.20.26}" "${PKG_BIND_ORIGIN:-dns/bind920}" ;;
            esac
        else
            printf '%s\n' '9.20.26'
        fi
        ;;
    rquery) printf '%s\n' '26.1.11_10' ;;
    config) printf '%s\n' 'FreeBSD:14:amd64' ;;
    version) printf '%s\n' "${PKG_VERSION_COMPARISON:-=}" ;;
    *) exit 2 ;;
esac
