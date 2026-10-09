#!/bin/sh

# Download hev-socks5-tunnel prebuilt binary for FreeBSD.
# Supports retry logic, version tracking, and architecture detection.

set -e

HEV_BIN="/usr/local/bin/hev-socks5-tunnel"
HEV_REPO="heiher/hev-socks5-tunnel"
VERSION_FILE="/usr/local/etc/xproxy/.hev-version"
MAX_RETRIES=3
RETRY_DELAY=3
ARCH=$(uname -m)

case "$ARCH" in
    amd64|x86_64)
        ASSET="hev-socks5-tunnel-freebsd-x86_64"
        ;;
    aarch64|arm64)
        ASSET="hev-socks5-tunnel-freebsd-aarch64"
        ;;
    *)
        echo "Error: unsupported architecture: $ARCH" >&2
        exit 1
        ;;
esac

fetch_tag() {
    fetch -qo - "https://api.github.com/repos/${HEV_REPO}/releases/latest" \
        | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p'
}

TAG=""
attempt=1
while [ $attempt -le $MAX_RETRIES ]; do
    TAG=$(fetch_tag)
    if [ -n "$TAG" ]; then
        break
    fi
    echo "Attempt $attempt/$MAX_RETRIES: failed to fetch latest release tag, retrying in ${RETRY_DELAY}s..." >&2
    sleep $RETRY_DELAY
    attempt=$((attempt + 1))
done

if [ -z "$TAG" ]; then
    echo "Error: could not determine latest release tag after $MAX_RETRIES attempts" >&2
    if [ -x "$HEV_BIN" ]; then
        echo "Keeping existing binary at $HEV_BIN"
        exit 0
    fi
    exit 1
fi

# Skip download if already at this version
if [ -x "$HEV_BIN" ] && [ -f "$VERSION_FILE" ]; then
    INSTALLED=$(cat "$VERSION_FILE" 2>/dev/null)
    if [ "$INSTALLED" = "$TAG" ]; then
        echo "hev-socks5-tunnel $TAG already installed (up to date)"
        exit 0
    fi
fi

URL="https://github.com/${HEV_REPO}/releases/download/${TAG}/${ASSET}"
echo "Downloading hev-socks5-tunnel ${TAG} for ${ARCH}..."

TMP_BIN="${HEV_BIN}.download"
attempt=1
while [ $attempt -le $MAX_RETRIES ]; do
    if fetch -o "$TMP_BIN" "$URL"; then
        break
    fi
    echo "Attempt $attempt/$MAX_RETRIES: download failed, retrying in ${RETRY_DELAY}s..." >&2
    rm -f "$TMP_BIN"
    sleep $RETRY_DELAY
    attempt=$((attempt + 1))
done

if [ ! -f "$TMP_BIN" ]; then
    echo "Error: download failed after $MAX_RETRIES attempts" >&2
    if [ -x "$HEV_BIN" ]; then
        echo "Keeping existing binary at $HEV_BIN"
        exit 0
    fi
    exit 1
fi

# Validate the downloaded file is a real binary (not an HTML error page)
if file "$TMP_BIN" | grep -q "HTML\|text"; then
    echo "Error: downloaded file is not a valid binary (got HTML/text)" >&2
    rm -f "$TMP_BIN"
    exit 1
fi

chmod 0755 "$TMP_BIN"
mv -f "$TMP_BIN" "$HEV_BIN"

mkdir -p "$(dirname "$VERSION_FILE")"
echo "$TAG" > "$VERSION_FILE"

echo "hev-socks5-tunnel ${TAG} installed to $HEV_BIN"
