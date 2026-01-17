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

# dns_myinternal.sh - Example custom DNS API script for internal BIND DNS server
#
# This script manages DNS TXT records via SSH to an internal DNS server
# for ACME DNS-01 challenge validation.
#
# INSTALLATION:
#   1. Copy this script to /usr/local/share/examples/acme.sh/dnsapi/
#   2. chmod 644 /usr/local/share/examples/acme.sh/dnsapi/dns_myinternal.sh
#   3. Configure SSH key-based authentication to your DNS server
#   4. In OPNsense ACME Client, select "Custom DNS API Script"
#
# Required Environment Variables:
#   DNS_SERVER_HOST   - Hostname or IP of the DNS server
#   DNS_SERVER_USER   - SSH username for the DNS server
#
# Optional Environment Variables:
#   DNS_SSH_KEY       - Path to SSH private key

########  Public functions  #####################

dns_myinternal_add() {
  fulldomain=$1
  txtvalue=$2

  _info "Adding TXT record for $fulldomain"

  if [ -z "$DNS_SERVER_HOST" ]; then
    _err "DNS_SERVER_HOST is not set"
    return 1
  fi

  if [ -z "$DNS_SERVER_USER" ]; then
    _err "DNS_SERVER_USER is not set"
    return 1
  fi

  _zone=$(_get_zone "$fulldomain")
  if [ -z "$_zone" ]; then
    _err "Could not determine zone for $fulldomain"
    return 1
  fi

  _ssh_opts="-o BatchMode=yes -o StrictHostKeyChecking=accept-new"
  if [ -n "$DNS_SSH_KEY" ]; then
    _ssh_opts="$_ssh_opts -i $DNS_SSH_KEY"
  fi

  _cmd="nsupdate -l << NSUPDATE_EOF
zone $_zone
update add $fulldomain 300 TXT \"$txtvalue\"
send
NSUPDATE_EOF"

  # shellcheck disable=SC2029
  if ssh $_ssh_opts "$DNS_SERVER_USER@$DNS_SERVER_HOST" "$_cmd" 2>&1; then
    _info "TXT record added successfully"
    return 0
  else
    _err "Failed to add TXT record"
    return 1
  fi
}

dns_myinternal_rm() {
  fulldomain=$1
  txtvalue=$2

  _info "Removing TXT record for $fulldomain"

  _zone=$(_get_zone "$fulldomain")
  if [ -z "$_zone" ]; then
    _err "Could not determine zone for $fulldomain"
    return 1
  fi

  _ssh_opts="-o BatchMode=yes -o StrictHostKeyChecking=accept-new"
  if [ -n "$DNS_SSH_KEY" ]; then
    _ssh_opts="$_ssh_opts -i $DNS_SSH_KEY"
  fi

  _cmd="nsupdate -l << NSUPDATE_EOF
zone $_zone
update delete $fulldomain TXT
send
NSUPDATE_EOF"

  # shellcheck disable=SC2029
  if ssh $_ssh_opts "$DNS_SERVER_USER@$DNS_SERVER_HOST" "$_cmd" 2>&1; then
    _info "TXT record removed successfully"
    return 0
  else
    _err "Failed to remove TXT record"
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
