#!/bin/sh

log_msg() {
    logger -t cloudflared-install "$1"
    echo "$1"
}

DEST="/usr/local/bin/cloudflared"
GITHUB_API="https://api.github.com/repos/kjake/cloudflared/releases/latest"
API_CACHE="/tmp/cloudflared_release.json"

OS_VERSION=$(uname -r | cut -d'.' -f1)
ARCH=$(uname -m)

log_msg "Detected FreeBSD ${OS_VERSION} / ${ARCH}"

# Fetch latest release metadata
log_msg "Fetching latest release version from GitHub..."
fetch -o "${API_CACHE}" "${GITHUB_API}" > /dev/null 2>&1

if [ ! -s "${API_CACHE}" ]; then
    log_msg "ERROR: Could not reach GitHub API. Check internet connectivity."
    exit 1
fi

# Parse tag_name using Python (reliable on single-line JSON)
LATEST_TAG=$(python3 -c "import json,sys; print(json.load(open('${API_CACHE}'))['tag_name'])" 2>/dev/null)

if [ -z "${LATEST_TAG}" ]; then
    log_msg "ERROR: Could not parse release version from GitHub API response."
    exit 1
fi

rm -f "${API_CACHE}"
log_msg "Latest version: ${LATEST_TAG}"

BINARY_NAME="cloudflared-freebsd${OS_VERSION}-${ARCH}"
BINARY_URL="https://github.com/kjake/cloudflared/releases/download/${LATEST_TAG}/${BINARY_NAME}"

log_msg "Downloading ${BINARY_NAME}..."
fetch -o "${DEST}" "${BINARY_URL}" > /dev/null 2>&1

if [ $? -eq 0 ] && [ -s "${DEST}" ]; then
    chmod +x "${DEST}"
    log_msg "Installation of cloudflared ${LATEST_TAG} successful."
else
    rm -f "${DEST}"
    log_msg "ERROR: Download failed. Binary may not exist for FreeBSD ${OS_VERSION} / ${ARCH}."
    log_msg "URL attempted: ${BINARY_URL}"
    exit 1
fi
