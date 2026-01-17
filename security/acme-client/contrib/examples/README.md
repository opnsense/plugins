# Custom DNS API Script Examples

This directory contains example scripts for use with the "Custom DNS API Script"
feature in the OPNsense ACME Client plugin.

## Available Examples

### dns_myinternal.sh
An SSH-based script for internal BIND DNS servers. This script:
- Connects to a remote DNS server via SSH
- Uses `nsupdate` to add/remove TXT records
- Supports custom SSH key configuration

**Use case:** Organizations with internal DNS servers that need to issue
certificates for internal domains.

### dns_myapi.sh
A template script for HTTP/REST-based DNS APIs. This script:
- Makes HTTP requests to add/remove TXT records
- Includes response parsing examples
- Easy to customize for any REST API

**Use case:** DNS providers with HTTP APIs not yet supported by acme.sh.

## Installation

1. Copy the desired script to OPNsense:
   ```bash
   scp dns_myinternal.sh root@opnsense:/usr/local/share/examples/acme.sh/dnsapi/
   ```

2. Set permissions:
   ```bash
   ssh root@opnsense "chmod 644 /usr/local/share/examples/acme.sh/dnsapi/dns_myinternal.sh"
   ```

3. Configure in OPNsense:
   - Navigate to **Services > ACME Client > Challenge Types**
   - Select **DNS-01** as Challenge Type
   - Select **Custom DNS API Script** as DNS Service
   - Enter the script name (e.g., `dns_myinternal` or `myinternal`)
   - Configure required environment variables

## Creating Your Own Script

Custom scripts must implement two functions:

```bash
# Add a TXT record
dns_SCRIPTNAME_add() {
  fulldomain=$1    # e.g., _acme-challenge.example.com
  txtvalue=$2      # The ACME challenge token
  # Add the TXT record
  return 0         # Return 0 on success, non-zero on failure
}

# Remove a TXT record
dns_SCRIPTNAME_rm() {
  fulldomain=$1
  txtvalue=$2
  # Remove the TXT record
  return 0
}
```

Scripts can use acme.sh helper functions:
- `_info "message"` - Log informational message
- `_debug "message"` - Log debug message (when debug enabled)
- `_err "message"` - Log error message

## Environment Variables

Configure environment variables in the OPNsense ACME Client UI:
- Up to 4 custom environment variables supported
- Variable names must be uppercase (e.g., `DNS_SERVER_HOST`)
- Values are stored securely

## Debugging

1. Enable debug logging in **Services > ACME Client > Settings**
2. Check logs in **Services > ACME Client > Log**
3. Test scripts manually:
   ```bash
   export DNS_SERVER_HOST="dns.example.com"
   . /usr/local/share/examples/acme.sh/dnsapi/dns_myinternal.sh
   dns_myinternal_add "_acme-challenge.test.example.com" "test_token"
   ```
