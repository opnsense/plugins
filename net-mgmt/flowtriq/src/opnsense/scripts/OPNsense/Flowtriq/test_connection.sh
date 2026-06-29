#!/bin/sh

# Test connectivity to the Flowtriq agent host
# Reads the configured agent host and port from the softflowd rc.conf

RC_CONF="/etc/rc.conf.d/softflowd"

if [ ! -f "$RC_CONF" ]; then
    echo "ERROR: Configuration not found. Save settings first."
    exit 1
fi

# Extract host and port from softflowd_flags
FLAGS=$(grep "softflowd_flags" "$RC_CONF" 2>/dev/null | head -1)
if [ -z "$FLAGS" ]; then
    echo "ERROR: softflowd is not configured. Save settings first."
    exit 1
fi

# Parse -n host:port from flags
DEST=$(echo "$FLAGS" | sed -n 's/.*-n \([^ ]*\).*/\1/p')
if [ -z "$DEST" ]; then
    echo "ERROR: Could not parse destination from configuration."
    exit 1
fi

HOST=$(echo "$DEST" | rev | cut -d: -f2- | rev)
PORT=$(echo "$DEST" | rev | cut -d: -f1 | rev)

echo "Testing connectivity to ftagent at ${HOST}:${PORT}..."
echo ""

# Test DNS resolution
if ! echo "$HOST" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo "Resolving hostname ${HOST}..."
    RESOLVED=$(host "$HOST" 2>/dev/null | head -1)
    if [ $? -ne 0 ]; then
        echo "WARNING: DNS resolution failed for ${HOST}"
    else
        echo "  $RESOLVED"
    fi
    echo ""
fi

# Test ICMP reachability
echo "Ping test:"
PING_RESULT=$(ping -c 3 -W 2 "$HOST" 2>&1)
if [ $? -eq 0 ]; then
    echo "  Host is reachable"
    echo "  $(echo "$PING_RESULT" | tail -1)"
else
    echo "  WARNING: Host did not respond to ping (ICMP may be blocked)"
fi
echo ""

# Test UDP port (send a small packet)
echo "UDP port test (${PORT}):"
if command -v nc >/dev/null 2>&1; then
    echo "test" | nc -u -w 2 "$HOST" "$PORT" 2>/dev/null
    echo "  Sent test packet to ${HOST}:${PORT}/udp"
    echo "  (UDP is connectionless; verify flows arrive at ftagent)"
else
    echo "  nc not available, skipping UDP test"
fi
echo ""

# Check if softflowd is running
echo "softflowd status:"
if pgrep -x softflowd >/dev/null 2>&1; then
    echo "  softflowd is running (PID: $(pgrep -x softflowd))"
    # Show softflowd statistics if available
    if command -v softflowctl >/dev/null 2>&1; then
        echo ""
        echo "Flow statistics:"
        softflowctl statistics 2>/dev/null | head -20
    fi
else
    echo "  softflowd is not running"
    echo "  Save settings and ensure the service is enabled"
fi
echo ""
echo "Verify flows arrive at ftagent:"
echo "  ssh your-ftagent-host 'journalctl -u ftagent -f | grep flow'"
