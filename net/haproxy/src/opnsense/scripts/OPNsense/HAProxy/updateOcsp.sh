#!/bin/sh
# This file is based on:
# https://github.com/acmesh-official/acme.sh/blob/master/deploy/haproxy.sh
#
# Copyright (C) 2021 Neil Pang
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

HAPROXY_DIR="/tmp/haproxy/ssl"
HAPROXY_SOCKET="/var/run/haproxy.socket"

for _pem in "$HAPROXY_DIR"/*.pem; do
    cert_file="$(basename "$_pem")"
    _issuer="${HAPROXY_DIR}/${cert_file%.pem}.issuer"
    _ocsp="${_pem}.ocsp"
    cert_cn="$(openssl x509 -in "$_pem" -noout -text | sed -nE 's/.*Subject:.*CN = ([^,]*)(,.*)?$/\1/p')"

    if [ ! -f "$_issuer" ]; then
        continue
    fi

    if [ -r "${_issuer}" ]; then
        _ocsp_url="$(openssl x509 -noout -ocsp_uri -in "$_pem")"
        if [ -n "$_ocsp_url" ]; then
            _ocsp_host="$(echo "$_ocsp_url" | cut -d/ -f3)"
            subjectdn="$(openssl x509 -in "$_issuer" -subject -noout | cut -d'/' -f2,3,4,5,6,7,8,9,10)"
            issuerdn="$(openssl x509 -in "$_issuer" -issuer -noout | cut -d'/' -f2,3,4,5,6,7,8,9,10)"
            if [ "$subjectdn" = "$issuerdn" ]; then
                _cafile_argument="-CAfile \"${_issuer}\""
            else
                _cafile_argument=""
            fi
            _openssl_version=$(openssl version | cut -d' ' -f2)
            _openssl_major=$(echo "${_openssl_version}" | cut -d '.' -f1)
            _openssl_minor=$(echo "${_openssl_version}" | cut -d '.' -f2)
            if [ "${_openssl_major}" -eq "1" ] && [ "${_openssl_minor}" -ge "1" ] || [ "${_openssl_major}" -ge "2" ]; then
                _header_sep="="
            else
                _header_sep=" "
            fi

            _openssl_ocsp_cmd="openssl ocsp \
                -issuer \"${_issuer}\" \
                -cert \"${_pem}\" \
                -url \"${_ocsp_url}\" \
                -header Host${_header_sep}\"${_ocsp_host}\" \
                -respout \"${_ocsp}\" \
                -verify_other \"${_issuer}\" \
                ${_cafile_argument} \
                | grep -q \"${_pem}: good\""

            eval "${_openssl_ocsp_cmd}"
            _ret=$?

            if [ "${_ret}" != "0" ]; then
                echo "Updating OCSP stapling failed with return code ${_ret}"
            else
                _update="$(openssl enc -base64 -A -in "${_ocsp}")"
                if ! echo "set ssl ocsp-response ${_update}" | socat stdio $HAPROXY_SOCKET; then
                    echo "Updating haproxy OCSP stapling via socket failed"
                fi
            fi
        fi
    fi
done
