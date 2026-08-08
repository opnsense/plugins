#!/bin/sh

if [ -n "${PKG_CALL_LOG:-}" ]
then
    printf '%s\n' "$*" >> "$PKG_CALL_LOG"
fi

case "$1" in
    update) exit 0 ;;
    fetch)
        [ "$2" = -y ] && [ "$3" = -r ] && [ "$4" = OPNsense ] || exit 2
        [ "$5" = -o ] && [ "$7" = pkg-2.3.1_1 ] || exit 2
        mkdir -p "$6/All"
        printf '%s\n' 'fixture target package archive' > "$6/All/pkg-2.3.1_1.pkg"
        ;;
    add)
        [ "$2" = -f ] || exit 2
        if [ -n "${PKG_STATIC_PATH:-}" ]
        then
            printf '%s\n' \
                '#!/bin/sh' \
                'printf '\''%s\n'\'' "$*" >> "$PKG_STATIC_CALL_LOG"' \
                'if [ "$1" = -v ]; then printf '\''%s\n'\'' '\''2.3.1'\''; exit 0; fi' \
                'if [ "$1" = query ]; then printf '\''%s\n'\'' '\''/usr/local/opnsense/mvc/app/models/OPNsense/Bind/Menu/Menu.xml|1$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'\''; exit 0; fi' \
                'exit 64' > "$PKG_STATIC_PATH"
            chmod 0755 "$PKG_STATIC_PATH"
        fi
        ;;
    lock)
        case "$2" in
            -y) : > "${PKG_LOCK_MARKER:?}" ;;
            -l) [ -f "${PKG_LOCK_MARKER:?}" ] && printf '%s\n' 'pkg-2.3.1_1' ;;
            *) exit 2 ;;
        esac
        ;;
    install)
        if [ "${3:-}" = python3 ] && [ -n "${PYTHON_COMMAND:-}" ]
        then
            printf '%s\n' '#!/bin/sh' 'exec python3 "$@"' > "$PYTHON_COMMAND"
            chmod 0755 "$PYTHON_COMMAND"
        fi
        exit 0
        ;;
    query)
        case "$*" in
        *'%n = pkg'*|*'pkg-2.3.1_1.pkg'*)
            printf '%s\n' 'pkg|2.3.1_1|ports-mgmt/pkg|FreeBSD:14:amd64'
            ;;
        *)
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
        esac
        ;;
    rquery) printf '%s\n' '26.1.11_10' ;;
    config) printf '%s\n' 'FreeBSD:14:amd64' ;;
    version) printf '%s\n' "${PKG_VERSION_COMPARISON:-=}" ;;
    *) exit 2 ;;
esac
