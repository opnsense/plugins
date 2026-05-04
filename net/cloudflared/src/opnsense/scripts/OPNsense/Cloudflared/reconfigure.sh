#!/bin/sh

log_msg() {
    logger -t cloudflared-reconfigure "$1"
    echo "$1"
}

log_msg "Starting cloudflared reconfiguration..."

# Dados estáticos: criar diretórios apenas se não existirem
if [ ! -d /usr/local/etc/cloudflared ]; then
    mkdir -p /usr/local/etc/cloudflared
    chmod 750 /usr/local/etc/cloudflared
    log_msg "Created /usr/local/etc/cloudflared"
fi

if [ ! -d /usr/local/etc/sysctl.conf.d ]; then
    mkdir -p /usr/local/etc/sysctl.conf.d
    log_msg "Created /usr/local/etc/sysctl.conf.d"
fi

# Dados dinâmicos: recarregar templates (rc.conf.d e token sempre atualizados)
log_msg "Reloading configuration templates..."
/usr/local/sbin/configctl template reload OPNsense/Cloudflared

# Proteger token: chmod 600 (nunca deve ser world-readable)
if [ -f /usr/local/etc/cloudflared/token ]; then
    chmod 600 /usr/local/etc/cloudflared/token
    log_msg "Token file permissions set to 600."
fi

# Apply sysctl tunables se existirem
if [ -f /usr/local/etc/sysctl.conf.d/cloudflared.conf ]; then
    log_msg "Applying sysctl tunables..."
    while IFS= read -r line; do
        case "$line" in
            \#*|'') continue ;;
        esac
        key=$(echo "$line" | cut -d'=' -f1)
        val=$(echo "$line" | cut -d'=' -f2-)
        if sysctl -w "${key}=${val}" > /dev/null 2>&1; then
            log_msg "sysctl ${key}=${val} applied."
        else
            log_msg "WARNING: sysctl ${key}=${val} failed (may require reboot)."
        fi
    done < /usr/local/etc/sysctl.conf.d/cloudflared.conf
fi

# Verificar se o binário existe antes de tentar iniciar
if [ ! -x /usr/local/bin/cloudflared ]; then
    log_msg "WARNING: /usr/local/bin/cloudflared not found. Use 'Install/Update Binary' first."
    exit 0
fi

# Reiniciar serviço
log_msg "Restarting cloudflared service..."
service cloudflared restart

log_msg "Reconfiguration complete."
