#!/bin/bash
#
# Deploy os-hcloud-ddns to OPNsense for testing
#

set -e

# Konfiguration
OPNSENSE_IP="${1:-}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="${SCRIPT_DIR}/src"

# Farben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ -z "$OPNSENSE_IP" ]; then
    echo -e "${YELLOW}Usage: $0 <opnsense-ip>${NC}"
    echo ""
    echo "Example:"
    echo "  $0 192.168.1.1"
    echo "  $0 opnsense.local"
    exit 1
fi

# Determine SSH/SCP method: key-based or password
SSH_CMD="ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5"
SCP_CMD="scp -o StrictHostKeyChecking=no -q"

if ssh -o ConnectTimeout=5 -o BatchMode=yes root@${OPNSENSE_IP} "true" 2>/dev/null; then
    AUTH_METHOD="key"
else
    # No key auth available - use sshpass
    if ! command -v sshpass &>/dev/null; then
        echo -e "${RED}ERROR: No SSH key configured and 'sshpass' not installed.${NC}"
        echo "Install sshpass or configure SSH key authentication."
        exit 1
    fi
    # Use SSHPASS env var if already set, otherwise prompt
    if [ -z "$SSHPASS" ]; then
        read -s -p "root@${OPNSENSE_IP} password: " SSHPASS
        echo ""
        export SSHPASS
    fi
    SSH_CMD="sshpass -e ${SSH_CMD}"
    SCP_CMD="sshpass -e ${SCP_CMD}"
    AUTH_METHOD="password"
fi

echo -e "${GREEN}=== Deploying os-hcloud-ddns to ${OPNSENSE_IP} (${AUTH_METHOD} auth) ===${NC}"
echo ""

# Test SSH connection
echo -e "${YELLOW}[1/5] Testing SSH connection...${NC}"
if ! ${SSH_CMD} root@${OPNSENSE_IP} "echo 'SSH OK'" 2>/dev/null; then
    echo -e "${RED}ERROR: Cannot connect to root@${OPNSENSE_IP}${NC}"
    echo "Make sure:"
    echo "  1. SSH is enabled on OPNsense"
    echo "  2. Your SSH key or password is correct"
    echo "  3. The IP address is correct"
    exit 1
fi

# Clean up old plugin artifacts (renamed from HCloudDDNS to HCloudDNS in Dec 2025)
echo -e "${YELLOW}[2/6] Cleaning up old plugin artifacts (hcloudddns → hclouddns)...${NC}"
${SSH_CMD} root@${OPNSENSE_IP} "
rm -f /usr/local/etc/inc/plugins.inc.d/hcloudddns.inc
rm -f /usr/local/etc/rc.syshook.d/monitor/50-hcloudddns
rm -f /usr/local/etc/rc.syshook.d/carp/20-hcloudddns
rm -f /usr/local/opnsense/service/conf/actions.d/actions_hcloudddns.conf
rm -rf /usr/local/opnsense/scripts/HCloudDDNS
rm -rf /usr/local/opnsense/mvc/app/controllers/OPNsense/HCloudDDNS
rm -rf /usr/local/opnsense/mvc/app/models/OPNsense/HCloudDDNS
rm -rf /usr/local/opnsense/mvc/app/views/OPNsense/HCloudDDNS
"

# Create directories on OPNsense
echo -e "${YELLOW}[3/6] Creating directories...${NC}"
${SSH_CMD} root@${OPNSENSE_IP} "
mkdir -p /usr/local/opnsense/scripts/HCloudDNS/lib
mkdir -p /usr/local/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/Api
mkdir -p /usr/local/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/forms
mkdir -p /usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/ACL
mkdir -p /usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/Menu
mkdir -p /usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/Migrations
mkdir -p /usr/local/opnsense/mvc/app/views/OPNsense/HCloudDNS
mkdir -p /usr/local/opnsense/service/conf/actions.d
mkdir -p /usr/local/etc/inc/plugins.inc.d
mkdir -p /usr/local/etc/rc.syshook.d/carp
mkdir -p /usr/local/etc/rc.syshook.d/monitor
"

# Copy files
echo -e "${YELLOW}[4/6] Copying files...${NC}"

# Python scripts
${SCP_CMD} ${SRC_DIR}/opnsense/scripts/HCloudDNS/*.py root@${OPNSENSE_IP}:/usr/local/opnsense/scripts/HCloudDNS/
${SCP_CMD} ${SRC_DIR}/opnsense/scripts/HCloudDNS/lib/*.py root@${OPNSENSE_IP}:/usr/local/opnsense/scripts/HCloudDNS/lib/

# PHP Controllers
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/*.php root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/Api/*.php root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/Api/

# Forms
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/forms/*.xml root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/controllers/OPNsense/HCloudDNS/forms/

# Models
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/models/OPNsense/HCloudDNS/*.php root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/models/OPNsense/HCloudDNS/*.xml root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/models/OPNsense/HCloudDNS/ACL/*.xml root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/ACL/
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/models/OPNsense/HCloudDNS/Menu/*.xml root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/Menu/
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/models/OPNsense/HCloudDNS/Migrations/*.php root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/models/OPNsense/HCloudDNS/Migrations/

# Views
${SCP_CMD} ${SRC_DIR}/opnsense/mvc/app/views/OPNsense/HCloudDNS/*.volt root@${OPNSENSE_IP}:/usr/local/opnsense/mvc/app/views/OPNsense/HCloudDNS/

# Configd actions
${SCP_CMD} ${SRC_DIR}/opnsense/service/conf/actions.d/actions_hclouddns.conf root@${OPNSENSE_IP}:/usr/local/opnsense/service/conf/actions.d/

# Plugin hook
${SCP_CMD} ${SRC_DIR}/etc/inc/plugins.inc.d/hclouddns.inc root@${OPNSENSE_IP}:/usr/local/etc/inc/plugins.inc.d/

# Syshooks (gateway monitor + CARP transition)
${SCP_CMD} ${SRC_DIR}/etc/rc.syshook.d/monitor/50-hclouddns root@${OPNSENSE_IP}:/usr/local/etc/rc.syshook.d/monitor/
${SCP_CMD} ${SRC_DIR}/etc/rc.syshook.d/carp/20-hclouddns root@${OPNSENSE_IP}:/usr/local/etc/rc.syshook.d/carp/

# Syslog filter template (for Log File tab)
${SSH_CMD} root@${OPNSENSE_IP} "mkdir -p /usr/local/opnsense/service/templates/OPNsense/Syslog/local"
${SCP_CMD} ${SRC_DIR}/opnsense/service/templates/OPNsense/Syslog/local/hclouddns.conf root@${OPNSENSE_IP}:/usr/local/opnsense/service/templates/OPNsense/Syslog/local/

# Set permissions and restart services
echo -e "${YELLOW}[5/6] Setting permissions and restarting services...${NC}"
${SSH_CMD} root@${OPNSENSE_IP} "
chmod +x /usr/local/opnsense/scripts/HCloudDNS/*.py
chmod +x /usr/local/opnsense/scripts/HCloudDNS/lib/*.py
chmod +x /usr/local/etc/rc.syshook.d/carp/20-hclouddns
chmod +x /usr/local/etc/rc.syshook.d/monitor/50-hclouddns
service configd restart
# Regenerate syslog-ng config to include hclouddns filter and reload
configctl template reload OPNsense/Syslog
service syslog-ng restart
"

# Test
echo -e "${YELLOW}[6/6] Testing installation...${NC}"
RESULT=$(${SSH_CMD} root@${OPNSENSE_IP} "configctl hclouddns status 2>&1 || echo 'FAIL'")
if [[ "$RESULT" == *"FAIL"* ]] || [[ "$RESULT" == *"error"* ]]; then
    echo -e "${RED}WARNING: configctl test returned unexpected result${NC}"
    echo "$RESULT"
else
    echo -e "${GREEN}configctl hclouddns status: OK${NC}"
fi

echo ""
echo -e "${GREEN}=== Deployment complete! ===${NC}"
echo ""
echo "Next steps:"
echo "  1. Open https://${OPNSENSE_IP} in your browser"
echo "  2. Navigate to: Services → Hetzner Cloud DDNS"
echo "  3. If menu doesn't appear, run on OPNsense:"
echo "     service php-fpm restart"
echo ""
echo "To test the backend manually:"
echo "  ssh root@${OPNSENSE_IP}"
echo "  configctl hclouddns validate YOUR_HETZNER_TOKEN"
echo "  configctl hclouddns list zones YOUR_HETZNER_TOKEN"
