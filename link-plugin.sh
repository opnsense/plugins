#! /usr/bin/env bash

if [[ ! -d "/app" ]] ; then
    ln -s /usr/local/opnsense/mvc/app
fi

for folder in "controllers" "models" "views"; do
    if [[ ! -d "/app/$folder/OPNsense/OpenApi" ]] ; then
        pushd "/app/$folder/OPNsense"
        ln -s "/gitroot/opnsense/plugins/devel/openapi/src/opnsense/mvc/app/$folder/OPNsense/OpenApi"
        popd
    fi
done

if [[ ! -d "/usr/local/opnsense/service/templates/OPNsense/OpenApi" ]] ; then
    pushd /usr/local/opnsense/service/templates/OPNsense/
    ln -s "/gitroot/opnsense/plugins/devel/openapi/src/opnsense/service/templates/OPNsense/OpenApi"
    popd
fi

if [[ ! -f "/usr/local/opnsense/service/conf/actions.d/actions_openapi.conf" ]] ; then
    pushd /usr/local/opnsense/service/conf/actions.d/
    ln -s "/gitroot/opnsense/plugins/devel/openapi/src/opnsense/service/conf/actions.d/actions_openapi.conf"
    popd
fi

if [[ ! -f "/usr/local/opnsense/scripts/OPNsense/OpenApi" ]] ; then
    pushd /usr/local/opnsense/scripts/OPNsense
    ln -s "/gitroot/opnsense/plugins/devel/openapi/src/opnsense/scripts/" OpenApi
    popd
fi

# if [[ ! -d "/usr/local/etc/openapi" ]] ; then
#     pushd /usr/local/etc/
#     ln -s "/gitroot/opnsense/plugins/devel/openapi/src/opnsense/mvc/app/$folder/OPNsense/OpenApi"
#     popd
# fi
