#! /usr/bin/env bash

helpmsg="Usage: $(realpath $0) [-r|--root-dir ROOTDIR] PLUGIN [...PLUGIN]"

while [[ $# -gt 0 ]]; do
  case $1 in
    -h|--help|help)
      echo "$helpmsg"
      exit 0
      ;;
    -r|--root-dir)
      ROOTDIR="$2"
      shift
      shift
      ;;
    -*|--*)
      echo "Unknown option $1"
      exit 1
      ;;
    *)
      POSITIONAL_ARGS+=("$1")
      shift
      ;;
  esac
done

if [[ -z "$POSITIONAL_ARGS" ]]; then
    echo $helpmsg 1>&2
    exit 1
fi

ROOTDIR="${ROOTDIR:-/usr/}"

if [[ ! -d "/app" ]] ; then
    pushd /
    ln -s /usr/local/opnsense/mvc/app
    popd
fi

for plugin in ${POSITIONAL_ARGS[@]}; do
    plugin_lower=$(echo $plugin | tr '[:upper:]' '[:lower:]')

    for folder in "controllers" "models" "views"; do
        if [[ ! -d "/app/$folder/OPNsense/$plugin" ]] ; then
            pushd "/app/$folder/OPNsense"
            ln -s "$ROOTDIR/plugins/devel/$plugin_lower/src/opnsense/mvc/app/$folder/OPNsense/$plugin"
            popd
        fi
    done

    if [[ ! -d "/usr/local/opnsense/service/templates/OPNsense/$plugin" ]] ; then
        pushd /usr/local/opnsense/service/templates/OPNsense/
        ln -s "$ROOTDIR/plugins/devel/$plugin_lower/src/opnsense/service/templates/OPNsense/$plugin"
        popd
    fi

    if [[ ! -f "/usr/local/opnsense/service/conf/actions.d/actions_$plugin_lower.conf" ]] ; then
        pushd /usr/local/opnsense/service/conf/actions.d/
        ln -s "$ROOTDIR/plugins/devel/$plugin_lower/src/opnsense/service/conf/actions.d/actions_$plugin_lower.conf"
        popd
    fi

    if [[ ! -f "/usr/local/opnsense/scripts/OPNsense/$plugin" ]] ; then
        pushd /usr/local/opnsense/scripts/OPNsense
        ln -s "$ROOTDIR/plugins/devel/$plugin_lower/src/opnsense/scripts/" $plugin
        popd
    fi
done
