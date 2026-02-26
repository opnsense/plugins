#!/bin/sh
#
# Reconfigure LightScope - regenerate config and restart service
#
# Copyright (c) 2025 Eric Kapitanski <e@alumni.usc.edu>
# University of Southern California Information Sciences Institute
#

CONFIG_FILE="/usr/local/etc/lightscope.conf"

# Update honeypot_ports from OPNsense model (preserves database ID)
# Note: pluginctl returns empty string if not set, which is valid (no honeypot ports)
HONEYPOT_PORTS=$(/usr/local/sbin/pluginctl -g OPNsense.Lightscope.general.honeypot_ports 2>/dev/null)
if [ -f "$CONFIG_FILE" ]; then
    # Check if line exists
    if grep -q "^honeypot_ports" "$CONFIG_FILE"; then
        sed -i '' "s/^honeypot_ports.*/honeypot_ports = $HONEYPOT_PORTS/" "$CONFIG_FILE"
    else
        # Add the line if it doesn't exist
        echo "honeypot_ports = $HONEYPOT_PORTS" >> "$CONFIG_FILE"
    fi
    echo "Updated honeypot_ports to: $HONEYPOT_PORTS"
fi

# Reload firewall rules (for honeypot ports)
/usr/local/etc/rc.filter_configure

# Check if service should be enabled
ENABLED=$(/usr/local/sbin/pluginctl -g OPNsense.Lightscope.general.enabled 2>/dev/null)

if [ "$ENABLED" = "1" ]; then
    # Restart service if enabled
    /usr/local/etc/rc.d/os-lightscope onerestart
else
    # Stop service if disabled
    /usr/local/etc/rc.d/os-lightscope onestop 2>/dev/null
fi

exit 0
