#!/bin/sh

# Verifica se o processo está rodando via PID file
if [ ! -f /var/run/cloudflared.pid ]; then
    echo '{"tunnel":"stopped"}'
    exit 0
fi

PID=$(cat /var/run/cloudflared.pid 2>/dev/null)
if [ -z "${PID}" ] || ! kill -0 "${PID}" 2>/dev/null; then
    echo '{"tunnel":"stopped"}'
    exit 0
fi

# Consulta o health check local do cloudflared (padrão: localhost:2000)
# HTTP 200 = tunnel conectado ao Cloudflare
# HTTP 503 / falha = processo rodando mas ainda conectando
fetch -T 3 -qo /dev/null "http://localhost:2000/healthcheck" 2>/dev/null
if [ $? -eq 0 ]; then
    echo '{"tunnel":"healthy"}'
else
    echo '{"tunnel":"connecting"}'
fi
