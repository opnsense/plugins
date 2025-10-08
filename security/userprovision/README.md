# OPNsense User Provisioning (userprovision)

Headless API to upsert Users, Groups, external RADIUS servers and query logs (DHCP/access).




## API
- POST /api/userprovision/group/ensure
  - name
- GET  /api/userprovision/group/list
- POST /api/userprovision/group/delete
  - name
- POST /api/userprovision/user/upsert
  - username, full_name?, password?, groups[]?
- GET  /api/userprovision/user/list
- POST /api/userprovision/user/delete
  - username
- POST /api/userprovision/user/disable
  - username
- POST /api/userprovision/user/enable
  - username
- POST /api/userprovision/user/setpassword
  - username, password
- POST /api/userprovision/radiusserver/upsert
  - name, host, secret, services?, auth_port?, acct_port?, timeout?, stationid?, descr?, sync_memberof?, sync_create_local_users?, sync_memberof_groups[]?, sync_default_groups[]?
- GET  /api/userprovision/radiusserver/list
- POST /api/userprovision/radiusserver/delete
  - name
- POST /api/userprovision/dhcp/upsert
  - iface, mac, ipaddr, descr?
- POST /api/userprovision/dhcp/apply
  - restart dhcpd to apply static-map changes
- GET  /api/userprovision/logs/dhcp
  - user?, limit?, q?, since?(RFC822), until?(RFC822), format?=json|csv
- GET  /api/userprovision/logs/access
  - user?, limit?, q?, since?(RFC822), until?(RFC822), format?=json|csv

### Captive Portal (CP) endpoints
- POST /api/userprovision/cp/zone/ensure
  - name, description?, interfaces[]
- GET  /api/userprovision/cp/zone/get
  - zone
- POST /api/userprovision/cp/zone/auth
  - zone, method=radius|local, server (required if method=radius)
- POST /api/userprovision/cp/zone/accounting
  - zone, enabled=true|false, interval?=300 (numeric)
- POST /api/userprovision/cp/zone/enable
  - zone, enabled=true|false
- POST /api/userprovision/cp/bypass/set
  - zone, ip? or mac?, enabled=true|false  (duplicate ip/mac entries are not added)
- POST /api/userprovision/cp/apply
  - restart captiveportal

Validation (CP):
- interfaces[]: interface name is validated; duplicates are not added
- auth: if method=radius, server name must exist and type=radius
- accounting interval: numeric
- bypass: ip/mac format is validated; duplicate allowed_ip/allowed_mac entries are not added

### Generic login (optional, alternative UI)
- POST /api/userprovision/auth/login
  - server=<authserver name>, username, password
  - Note: Returns Access‑Accept/Reject information; does not open a CP session.

## Settings
 The plugin does not maintain a separate runtime database; it relies on config (static-map), CP/RADIUS sessions, and logs.
  “Dynamic” leases without a static-map can be seen via logs; for full and persistent tracking, create a static-map for the user's device.
  If your log paths differ, set the settings.dhcpLogPath/accessLogPath values accordingly.
- GET  /api/userprovision/settings/get
- POST /api/userprovision/settings/set (JSON body)
  - settings.dhcpLogPath: default `/var/log/dhcpd.log`
  - settings.accessLogPath: default `/var/log/system.log`
  - settings.auditLogPath: default `/var/log/userprovision_audit.log`

## RADIUS Server API Details

### Required Fields (required)
- **name**: Descriptive name (3-64 chars, letters/digits/._-)
- **host**: Hostname or IP address (valid IP or RFC compliant hostname)
- **secret**: Shared Secret (not empty)

### Optional Fields (optional)
- **services**: "both" (Auth+Accounting) or "auth" (Auth only). Default: "both"
- **auth_port**: Authentication port. Default: 1812
- **acct_port**: Accounting port. Default: 1813
- **timeout**: Authentication timeout in seconds. Default: 5
- **stationid**: Called Station ID (MAC:SSID format)
- **descr**: Description text
- **sync_memberof**: Synchronize groups (boolean)
- **sync_create_local_users**: Automatic user creation (boolean)
- **sync_memberof_groups**: Limit groups array (group names)
- **sync_default_groups**: Default groups array (group names)

### Examples

# Create new RADIUS server (upsert = insert + update)
curl -u KEY:SECRET -d 'name=TestRADIUS&host=10.0.0.5&secret=mysecret' \
  http://fw/api/userprovision/radiusserver/upsert
# Returns: {"status":"ok","created":true}

# Update existing RADIUS server (same endpoint, automatically detects existing)
curl -u KEY:SECRET -d 'name=TestRADIUS&host=10.0.0.6&timeout=20' \
  http://fw/api/userprovision/radiusserver/upsert
# Returns: {"status":"ok","created":false}

# Full RADIUS configuration (you can extend the code to support all fields as needed)
curl -u KEY:SECRET -d 'name=FullRADIUS&host=radius.company.com&secret=strongsecret&services=both&auth_port=1812&acct_port=1813&timeout=10&stationid=00:11:22:33:44:55:company.com&descr=Company RADIUS&sync_memberof=1&sync_create_local_users=1&sync_default_groups=adminsapi_users' \
  http://fw/api/userprovision/radiusserver/upsert

# Partial update (only change timeout) - upsert handles edit automatically
curl -u KEY:SECRET -d 'name=TestRADIUS&timeout=15' \
  http://fw/api/userprovision/radiusserver/upsert

# List all RADIUS servers
curl -u KEY:SECRET http://fw/api/userprovision/radiusserver/list

# Delete server
curl -u KEY:SECRET -d 'name=TestRADIUS' \
  http://fw/api/userprovision/radiusserver/delete


## Validation rules
- username/group name: 3–64 chars; letters, digits, `._-`
- password: min 8 chars; upper, lower, digit, special
- iface: 2–32 chars; letters/digits `._-`
- mac: `aa:bb:cc:dd:ee:ff`
- ipaddr: IPv4/IPv6 (filter_var)
- host: IP or RFC-compliant hostname
- timeout: numeric and positive
- services: "both" or "auth"

## RBAC / API key
1) System → Access → Users → (API user) → Generate API key/secret
2) Effective Privileges → Add privilege for api/userprovision/*
3) Call endpoints with -u KEY:SECRET

## Audit log
This plugin writes a concise audit trail to `/var/log/userprovision_audit.log` (path configurable in settings).

## End-to-end flow (National ID - TCKN)

# 1) Add external RADIUS server to the firewall (one-time)
curl -u KEY:SECRET -d 'name=ext-rad&host=10.0.0.5&secret=XXXX&auth_port=1812&acct_port=1813' \
  http://fw/api/userprovision/radiusserver/upsert

# 2) Create Captive Portal zone and assign RADIUS
curl -u KEY:SECRET -d 'name=ZONE1&interfaces[]=lan' http://fw/api/userprovision/cp/zone/ensure
curl -u KEY:SECRET -d 'zone=ZONE1&method=radius&server=ext-rad' http://fw/api/userprovision/cp/zone/auth
curl -u KEY:SECRET -d 'zone=ZONE1&enabled=true' http://fw/api/userprovision/cp/zone/enable
curl -u KEY:SECRET -X POST http://fw/api/userprovision/cp/apply

# 3) At registration time (National ID)
curl -u KEY:SECRET -d 'name=GUEST' http://fw/api/userprovision/group/ensure
curl -u KEY:SECRET -d 'username=12345678901&full_name=John%20Doe&groups[]=GUEST' \
  http://fw/api/userprovision/user/upsert

# 4) (Optional) CP bypass or DHCP static-map
curl -u KEY:SECRET -d 'zone=ZONE1&mac=aa:bb:cc:dd:ee:ff&enabled=true' \
  http://fw/api/userprovision/cp/bypass/set
curl -u KEY:SECRET -d 'iface=lan&mac=aa:bb:cc:dd:ee:ff&ipaddr=10.0.10.25&descr=12345678901' \
  http://fw/api/userprovision/dhcp/upsert
curl -u KEY:SECRET -X POST http://fw/api/userprovision/dhcp/apply

# 5) Logs
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/dhcp?user=12345678901&limit=200'
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/access?user=12345678901&limit=200'


## Examples

# group ensure
curl -u KEY:SECRET -d 'name=VIP' http://fw/api/userprovision/group/ensure

# user upsert
curl -u KEY:SECRET -d 'username=12345678901&full_name=John%20Doe&groups[]=VIP' \
  http://fw/api/userprovision/user/upsert

# external RADIUS
curl -u KEY:SECRET -d 'name=ext-rad&host=10.0.0.5&secret=XXXX&descr=External' \
  http://fw/api/userprovision/radiusserver/upsert

# dhcp static map + apply
curl -u KEY:SECRET -d 'iface=lan&mac=aa:bb:cc:dd:ee:ff&ipaddr=10.0.10.25&descr=12345678901' \
  http://fw/api/userprovision/dhcp/upsert
curl -u KEY:SECRET -X POST http://fw/api/userprovision/dhcp/apply

# logs with filters
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/dhcp?user=12345678901&q=lease&since=2025-09-25T00:00:00Z&format=csv'
# read settings
curl -u KEY:SECRET http://fw/api/userprovision/settings/get

# change dhcp log path
curl -u KEY:SECRET -H 'Content-Type: application/json' \
  -d '{"settings":{"dhcpLogPath":"/var/log/dhcpd.log"}}' \
  http://fw/api/userprovision/settings/set


curl -u KEY:SECRET -d 'username=12345678901' http://fw/api/userprovision/user/disable
curl -u KEY:SECRET -d 'username=12345678901' http://fw/api/userprovision/user/enable
curl -u KEY:SECRET -d 'username=12345678901&password=StrongPass#1' http://fw/api/userprovision/user/setpassword

curl -u KEY:SECRET http://fw/api/userprovision/radiusserver/list
curl -u KEY:SECRET -d 'name=ext-rad' http://fw/api/userprovision/radiusserver/delete


# list
curl -u KEY:SECRET http://fw/api/userprovision/user/list
curl -u KEY:SECRET http://fw/api/userprovision/group/list

# delete
curl -u KEY:SECRET -d 'username=12345678901' http://fw/api/userprovision/user/delete
curl -u KEY:SECRET -d 'name=VIP' http://fw/api/userprovision/group/delete


 DHCP static map
curl -u KEY:SECRET -d 'iface=lan&mac=aa:bb:cc:dd:ee:ff&ipaddr=10.0.10.25&descr=12345678901' \
  http://fw/api/userprovision/dhcp/upsert

# RADIUS server (with descr)
curl -u KEY:SECRET -d 'name=ext-rad&host=10.0.0.5&secret=XXXX&descr=External Radius' \
  http://fw/api/userprovision/radiusserver/upsert

# Logs with filters and CSV
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/dhcp?user=12345678901&q=lease&since=2025-09-25T00:00:00Z&format=csv'
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/access?user=12345678901&limit=500&format=json'

# DHCP static-map
curl -u KEY:SECRET -d 'iface=lan&mac=aa:bb:cc:dd:ee:ff&ipaddr=10.0.10.25&descr=12345678901' \
  http://fw/api/userprovision/dhcp/upsert

# RADIUS server
curl -u KEY:SECRET -d 'name=ext-rad&host=10.0.0.5&secret=XXXX' \
  http://fw/api/userprovision/radiusserver/upsert

# Logs
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/dhcp?user=12345678901&limit=200'
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/access?user=12345678901&limit=200'

# group
curl -u KEY:SECRET -d 'name=VIP' http://fw/api/userprovision/group/ensure

# user (National ID or another ID) and group assignment
curl -u KEY:SECRET -d 'username=12345678901&full_name=John%20Doe&groups[]=VIP' \
  http://fw/api/userprovision/user/upsert

# external RADIUS record
curl -u KEY:SECRET -d 'name=ext-rad&host=10.0.0.5&secret=XXXX&auth_port=1812&acct_port=1813&timeout=5' \
  http://fw/api/userprovision/radiusserver/upsert

# logs
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/dhcp?user=12345678901&limit=300'
curl -u KEY:SECRET 'http://fw/api/userprovision/logs/access?user=12345678901&limit=300'


