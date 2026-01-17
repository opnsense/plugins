#!/usr/bin/env sh

# dns_myapi.sh - Custom DNS API script template for HTTP-based DNS APIs
#
# This is a template script for DNS providers with HTTP/REST APIs.
# Customize this script for your specific DNS provider.
#
# INSTALLATION:
#   1. Copy this script to /usr/local/share/examples/acme.sh/dnsapi/
#   2. Rename it to match your provider (e.g., dns_myprovider.sh)
#   3. Update the function names to match (e.g., dns_myprovider_add)
#   4. Set permissions: chmod 644 /usr/local/share/examples/acme.sh/dnsapi/dns_myprovider.sh
#   5. In OPNsense ACME Client, select "Custom DNS API Script" and enter the script name
#
# Required Environment Variables (configure in OPNsense ACME Client UI):
#   MY_API_TOKEN      - Your DNS provider's API token
#   MY_API_URL        - Base URL for your DNS provider's API (optional)
#
# Note: Rename these variables to match your provider's naming convention

# Default API URL - change this to your provider's API endpoint
MY_API_URL="${MY_API_URL:-https://api.example.com/dns/v1}"

########  Public functions  #####################

# Add a TXT record
#   Usage: dns_myapi_add _acme-challenge.example.com "challenge_token_value"
dns_myapi_add() {
  fulldomain=$1
  txtvalue=$2

  _info "Adding TXT record for $fulldomain"

  # Validate required environment variables
  if [ -z "$MY_API_TOKEN" ]; then
    _err "MY_API_TOKEN is not set"
    return 1
  fi

  # Extract zone and record name from the full domain
  # Adjust this logic based on your DNS provider's requirements
  _zone=$(_get_zone "$fulldomain")
  _record=$(_get_record "$fulldomain" "$_zone")

  _debug "Zone: $_zone"
  _debug "Record: $_record"
  _debug "TXT Value: $txtvalue"

  # Make HTTP request to add the TXT record
  # Adjust the endpoint, method, and payload format for your provider
  _response=$(curl -s -X POST "${MY_API_URL}/zones/${_zone}/records" \
    -H "Authorization: Bearer $MY_API_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"type\":\"TXT\",\"name\":\"${_record}\",\"content\":\"${txtvalue}\",\"ttl\":300}")

  # Check for success - adjust based on your API's response format
  if echo "$_response" | grep -qi "success\|created\|\"id\""; then
    _info "TXT record added successfully"
    return 0
  else
    _err "Failed to add TXT record: $_response"
    return 1
  fi
}

# Remove a TXT record
#   Usage: dns_myapi_rm _acme-challenge.example.com "challenge_token_value"
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

  # First, find the record ID (many APIs require this for deletion)
  # Adjust the endpoint and response parsing for your provider
  _record_id=$(curl -s -X GET "${MY_API_URL}/zones/${_zone}/records?type=TXT&name=${_record}" \
    -H "Authorization: Bearer $MY_API_TOKEN" \
    | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

  if [ -z "$_record_id" ]; then
    _info "TXT record not found, nothing to remove"
    return 0
  fi

  # Delete the record
  _response=$(curl -s -X DELETE "${MY_API_URL}/zones/${_zone}/records/${_record_id}" \
    -H "Authorization: Bearer $MY_API_TOKEN")

  # Check for success
  if echo "$_response" | grep -qi "success\|deleted" || [ -z "$_response" ]; then
    _info "TXT record removed successfully"
    return 0
  else
    _err "Failed to remove TXT record: $_response"
    return 1
  fi
}

########  Private functions  #####################

# Get the zone name from a fully qualified domain
# This extracts the last two parts (e.g., example.com from _acme-challenge.www.example.com)
# Adjust for your DNS structure if needed
_get_zone() {
  _domain=$1

  echo "$_domain" | awk -F. '{
    n = NF
    if (n >= 2) {
      print $(n-1) "." $n
    }
  }'
}

# Get the record name (subdomain part) from a full domain
# Returns everything before the zone (e.g., _acme-challenge.www from _acme-challenge.www.example.com)
_get_record() {
  _domain=$1
  _zone=$2

  echo "$_domain" | sed "s/\.$_zone\$//"
}
