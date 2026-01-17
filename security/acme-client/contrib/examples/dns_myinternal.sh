#!/usr/bin/env sh

# dns_myinternal.sh - Custom DNS API script for internal BIND DNS server
#
# This script manages DNS TXT records via SSH to an internal DNS server
# for ACME DNS-01 challenge validation.
#
# INSTALLATION:
#   1. Copy this script to /usr/local/share/examples/acme.sh/dnsapi/
#   2. Set permissions: chmod 644 /usr/local/share/examples/acme.sh/dnsapi/dns_myinternal.sh
#   3. Configure SSH key-based authentication to your DNS server
#   4. In OPNsense ACME Client, select "Custom DNS API Script" and enter "dns_myinternal"
#
# Required Environment Variables (configure in OPNsense ACME Client UI):
#   DNS_SERVER_HOST   - Hostname or IP of the DNS server
#   DNS_SERVER_USER   - SSH username for the DNS server
#
# Optional Environment Variables:
#   DNS_SSH_KEY       - Path to SSH private key (uses default if not set)
#
# Prerequisites on DNS Server:
#   - nsupdate command available
#   - BIND configured to allow local dynamic updates
#   - SSH public key added to authorized_keys

########  Public functions  #####################

# Add a TXT record
#   Usage: dns_myinternal_add _acme-challenge.example.com "challenge_token_value"
dns_myinternal_add() {
  fulldomain=$1
  txtvalue=$2

  _info "Adding TXT record for $fulldomain"

  # Validate required environment variables
  if [ -z "$DNS_SERVER_HOST" ]; then
    _err "DNS_SERVER_HOST is not set"
    return 1
  fi

  if [ -z "$DNS_SERVER_USER" ]; then
    _err "DNS_SERVER_USER is not set"
    return 1
  fi

  # Extract the zone from the domain
  _zone=$(_get_zone "$fulldomain")
  if [ -z "$_zone" ]; then
    _err "Could not determine zone for $fulldomain"
    return 1
  fi

  # Extract record name (everything before the zone)
  _record=$(echo "$fulldomain" | sed "s/\.$_zone\$//" | sed 's/\.$//')

  _info "Zone: $_zone, Record: $_record"

  # Build SSH command
  _ssh_opts="-o BatchMode=yes -o StrictHostKeyChecking=accept-new"
  if [ -n "$DNS_SSH_KEY" ]; then
    _ssh_opts="$_ssh_opts -i $DNS_SSH_KEY"
  fi

  # Create the TXT record via SSH using nsupdate
  _cmd="nsupdate -l << NSUPDATE_EOF
zone $_zone
update add $fulldomain 300 TXT \"$txtvalue\"
send
NSUPDATE_EOF"

  _debug "Running: ssh $_ssh_opts $DNS_SERVER_USER@$DNS_SERVER_HOST"

  # shellcheck disable=SC2029
  if ssh $_ssh_opts "$DNS_SERVER_USER@$DNS_SERVER_HOST" "$_cmd" 2>&1; then
    _info "TXT record added successfully"
    return 0
  else
    _err "Failed to add TXT record"
    return 1
  fi
}

# Remove a TXT record
#   Usage: dns_myinternal_rm _acme-challenge.example.com "challenge_token_value"
dns_myinternal_rm() {
  fulldomain=$1
  txtvalue=$2

  _info "Removing TXT record for $fulldomain"

  # Extract the zone
  _zone=$(_get_zone "$fulldomain")
  if [ -z "$_zone" ]; then
    _err "Could not determine zone for $fulldomain"
    return 1
  fi

  # Build SSH command
  _ssh_opts="-o BatchMode=yes -o StrictHostKeyChecking=accept-new"
  if [ -n "$DNS_SSH_KEY" ]; then
    _ssh_opts="$_ssh_opts -i $DNS_SSH_KEY"
  fi

  # Remove the TXT record
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

# Get the zone name from a fully qualified domain
# This simple implementation extracts the last two parts of the domain
# Adjust this function for your DNS structure if needed
_get_zone() {
  _domain=$1

  echo "$_domain" | awk -F. '{
    n = NF
    if (n >= 2) {
      print $(n-1) "." $n
    }
  }'
}
