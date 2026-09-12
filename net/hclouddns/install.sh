#!/bin/sh
#
# HCloudDNS Plugin Installer for OPNsense
# Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
#
# Usage: ./install.sh [user@]hostname
#

set -e

TARGET="${1:-root@192.168.1.1}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=========================================="
echo "  HCloudDNS Plugin Installer v2.0.0"
echo "=========================================="
echo ""
echo "Target: $TARGET"
echo "Source: $SCRIPT_DIR/src"
echo ""

# Check if src directory exists
if [ ! -d "$SCRIPT_DIR/src" ]; then
    echo "ERROR: src/ directory not found!"
    exit 1
fi

echo ">>> Copying files to OPNsense..."
scp -r "$SCRIPT_DIR/src/"* "$TARGET:/usr/local/"

echo ""
echo ">>> Setting permissions..."
ssh "$TARGET" "chmod +x /usr/local/opnsense/scripts/HCloudDNS/*.py" 2>/dev/null || true
ssh "$TARGET" "chmod +x /usr/local/etc/rc.syshook.d/monitor/50-hclouddns" 2>/dev/null || true

echo ""
echo ">>> Restarting configd service..."
ssh "$TARGET" 'service configd restart'

echo ""
echo "=========================================="
echo "  Installation complete!"
echo "=========================================="
echo ""
echo "Access the plugin at:"
echo "  Services -> Hetzner Cloud DNS"
echo ""
echo "If menu doesn't appear, clear browser cache"
echo "or restart the web GUI:"
echo "  service php-fpm restart"
echo ""
