#!/usr/bin/env sh

# Copyright (C) 2026 Norm Brandinger
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

# dns_myapi.sh - Example custom DNS API script template for HTTP-based DNS APIs
#
# This is a template script for DNS providers with HTTP/REST APIs.
# Customize this script for your specific DNS provider.
#
# INSTALLATION:
#   1. Copy and rename to match your provider (e.g., dns_myprovider.sh)
#   2. Update function names to match (e.g., dns_myprovider_add)
#   3. Copy to /usr/local/share/examples/acme.sh/dnsapi/
#   4. In OPNsense ACME Client, select "Custom DNS API Script"
#
# Required Environment Variables:
#   MY_API_TOKEN      - Your DNS provider's API token

MY_API_URL="${MY_API_URL:-https://api.example.com/dns/v1}"

########  Public functions  #####################

dns_myapi_add() {
  fulldomain=$1
  txtvalue=$2

  _info "Adding TXT record for $fulldomain"

  if [ -z "$MY_API_TOKEN" ]; then
    _err "MY_API_TOKEN is not set"
    return 1
  fi

  _zone=$(_get_zone "$fulldomain")
  _record=$(_get_record "$fulldomain" "$_zone")

  _response=$(curl -s -X POST "${MY_API_URL}/zones/${_zone}/records" \
    -H "Authorization: Bearer $MY_API_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"type\":\"TXT\",\"name\":\"${_record}\",\"content\":\"${txtvalue}\",\"ttl\":300}")

  if echo "$_response" | grep -qi "success\|created\|\"id\""; then
    _info "TXT record added successfully"
    return 0
  else
    _err "Failed to add TXT record: $_response"
    return 1
  fi
}

dns_myapi_rm() {
  fulldomain=$1
  txtvalue=$2

  _info "Removing TXT record for $fulldomain"

  if [ -z "$MY_API_TOKEN" ]; then
    _err "MY_API_TOKEN is not set"
    return 1
  fi

  _zone=$(_get_zone "$fulldomain")
  _record=$(_get_record "$fulldomain" "$_zone")

  _record_id=$(curl -s -X GET "${MY_API_URL}/zones/${_zone}/records?type=TXT&name=${_record}" \
    -H "Authorization: Bearer $MY_API_TOKEN" \
    | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

  if [ -z "$_record_id" ]; then
    _info "TXT record not found, nothing to remove"
    return 0
  fi

  _response=$(curl -s -X DELETE "${MY_API_URL}/zones/${_zone}/records/${_record_id}" \
    -H "Authorization: Bearer $MY_API_TOKEN")

  if echo "$_response" | grep -qi "success\|deleted" || [ -z "$_response" ]; then
    _info "TXT record removed successfully"
    return 0
  else
    _err "Failed to remove TXT record: $_response"
    return 1
  fi
}

########  Private functions  #####################

_get_zone() {
  _domain=$1
  echo "$_domain" | awk -F. '{
    n = NF
    if (n >= 2) {
      print $(n-1) "." $n
    }
  }'
}

_get_record() {
  _domain=$1
  _zone=$2
  echo "$_domain" | sed "s/\.$_zone\$//"
}
