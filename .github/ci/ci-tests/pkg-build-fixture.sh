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
    query) printf '%s\n' '9.20.24' ;;
    rquery) printf '%s\n' '26.1.11_10' ;;
    config) printf '%s\n' 'FreeBSD:14:amd64' ;;
    version) printf '%s\n' '=' ;;
    *) exit 2 ;;
esac
