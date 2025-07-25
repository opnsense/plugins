{#
 # Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 # Copyright (C) 2025 squared GmbH
 # Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
 # Copyright (C) 2025 NetBird GmbH
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    $(document).ready(() =>{
        function getElapsedTime(date) {
            if (!(date instanceof Date) || isNaN(date) || date.getMonth() === 0) return "-";

            const now = new Date();
            const diff = now - date;
            if (diff < 1000) return "Now";

            const units = [{
                    label: "day",
                    ms: 86400000
                },
                {
                    label: "hour",
                    ms: 3600000
                },
                {
                    label: "minute",
                    ms: 60000
                },
                {
                    label: "second",
                    ms: 1000
                },
            ];

            const parts = [];
            let remaining = diff;

            for (const {
                    label,
                    ms
                }
                of units) {
                const val = Math.floor(remaining / ms);
                if (val > 0) {
                    parts.push(`${val} ${label}${val > 1 ? "s" : ""}`);
                    remaining %= ms;
                }
                if (parts.length === 2) break;
            }

            return parts.join(", ") + " ago";
        }

        function formatBytes(bytes) {
            const unit = 1024;

            if (bytes < unit) {
                return `${bytes} B`;
            }

            const units = ['Ki', 'Mi', 'Gi', 'Ti', 'Pi', 'Ei'];
            const exp = Math.floor(Math.log(bytes) / Math.log(unit));
            const prefix = units[exp - 1];
            const value = bytes / Math.pow(unit, exp);

            return `${value.toFixed(1)} ${prefix}B`;
        }

        function getPeerConnectionStatus(status) {
            if (!status) return 'No status available.';

            const fmtConn = ({
                    connected,
                    url,
                    error
                }) =>
                connected ? `Connected${url ? ` to ${url}` : ''}` : `Disconnected${error ? `, reason: ${error}` : ''}`;

            const fmtList = (items, fmtFn, fallback) =>
                items?.length ? `\n${items.map(fmtFn).join("\n")}` : fallback;

            const managementStr = fmtConn(status.management || {});
            const signalStr = fmtConn(status.signal || {});

            const interfaceType = status.kernelInterface ? "Kernel" : status.netbirdIp ? "Userspace" : "N/A";
            const interfaceIp = status.netbirdIp || "N/A";

            const relaysStr = fmtList(
                status.relays?.details,
                r => `  [${r.uri}] is ${r.available ? "Available" : "Unavailable"}${r.error ? `, reason: ${r.error}` : ""}`,
                `${status.relays?.available || 0}/${status.relays?.total || 0} Available`
            );

            const dnsStr = fmtList(
                status.dnsServers,
                g => `  [${g.servers?.join(", ") || "N/A"}] for [${g.domains?.join(", ") || "."}] is ${g.enabled ? "Available" : "Unavailable"}${g.error ? `, reason: ${g.error}` : ""}`,
                `${(status.dnsServers || []).filter(g => g.enabled).length}/${(status.dnsServers || []).length} Available`
            );

            const info = {
                "Daemon version": status.daemonVersion,
                "CLI version": status.cliVersion,
                "Management": managementStr,
                "Signal": signalStr,
                "Relays": relaysStr,
                "Nameservers": dnsStr,
                "FQDN": status.fqdn,
                "NetBird IP": interfaceIp,
                "Interface type": interfaceType,
                "Quantum resistance": status.rosenpassEnabled ? `true${status.rosenpassPermissive ? " (permissive)" : ""}` : "false",
                "Lazy connection": status.lazyConnectionEnabled ? "true" : "false",
                "Networks": status.networks?.join(", ") || "-",
                "Forwarding rules": status.forwardingRules,
                "Peers count": `${status.peers?.connected || 0}/${status.peers?.total || 0} Connected`
            };
            return Object.entries(info).map(([k, v]) => `${k}: ${v}`).join("\n");
        }

        function getPeersDetail(status) {
            const {
                peers,
                rosenpassEnabled,
                rosenpassPermissive
            } = status;
            const details = peers?.details || [];

            return details.map(peer => {
                const getOrDefault = (val, def = '-') => val ?? def;
                const localIce = getOrDefault(peer.iceCandidateType?.local);
                const remoteIce = getOrDefault(peer.iceCandidateType?.remote);

                const quantumStatus = peer.quantumResistance ?
                    (rosenpassEnabled ? 'true' : 'false (connection might not work without a remote permissive mode)') :
                    rosenpassEnabled ?
                    (rosenpassPermissive ?
                        "false (remote didn't enable quantum resistance)" :
                        "false (connection won't work without a permissive mode)") :
                    'false';

                const networks = Array.isArray(peer.networks) && peer.networks.length ?
                    peer.networks.sort().join(', ') :
                    '-';

                const lastUpdate = new Date(peer.lastStatusUpdate || 0);
                const handshake = new Date(peer.lastWireguardHandshake || 0);

                const latency = typeof peer.latency === 'number' ?
                    `${(peer.latency / 1_000_000).toFixed(2)} ms` :
                    '-';

                const indent = (line) => `  ${line}`; // 2-space indent

                return [
                    `${peer.fqdn}:`,
                    indent(`NetBird IP: ${peer.netbirdIp}`),
                    indent(`Public key: ${peer.publicKey}`),
                    indent(`Status: ${peer.status}`),
                    indent(`-- detail --`),
                    indent(`Connection type: ${getOrDefault(peer.connectionType)}`),
                    indent(`ICE candidate (Local/Remote): ${localIce}/${remoteIce}`),
                    indent(`ICE candidate endpoints (Local/Remote): ${localIce}/${remoteIce}`),
                    indent(`Relay server address: ${getOrDefault(peer.relayAddress)}`),
                    indent(`Last connection update: ${getElapsedTime(lastUpdate)}`),
                    indent(`Last WireGuard handshake: ${getElapsedTime(handshake)}`),
                    indent(`Transfer status (received/sent): ${formatBytes(peer.transferReceived || 0)}/${formatBytes(peer.transferSent || 0)}`),
                    indent(`Quantum resistance: ${quantumStatus}`),
                    indent(`Networks: ${networks}`),
                    indent(`Latency: ${latency}`)
                ].join('\n');
            }).join('\n\n');
        }


        function loadConnectionStatus() {
            const $connStatus = $("#connStatus");
            const $peersDetails = $("#peersDetail");
            const $peersDetailContainer = $("#peersDetailContainer");

            ajaxGet('/api/netbird/status/status', {}, (data) => {
                const status = getPeerConnectionStatus(data);
                const details = getPeersDetail(data);
                
                const isConnected = data.management?.connected === true;
                $peersDetailContainer.toggleClass("hidden", !isConnected);
                const renderPreTable = (content, maxHeight = null) => {
                    const style = `padding: 10px;${maxHeight ? ` max-height: ${maxHeight}; overflow-y: auto;` : ''}`;
                    return `
                      <table class="table table-hover table-striped table-condensed">
                        <tbody>
                          <tr>
                            <td><pre style="${style}">${content}</pre></td>
                          </tr>
                        </tbody>
                      </table>
                    `;
                };

                $connStatus.html(renderPreTable(status));
                $peersDetails.html(renderPreTable(details, '500px'));
            });
        }

        function loadVersionData() {
            const $packages = $("#packages");

            ajaxGet('/api/core/firmware/info', {}, (data) => {
                const pkgs = data.package?.filter(pkg =>
                    pkg.name?.toLowerCase().includes("netbird")
                ) || [];

                const rows = pkgs.map(pkg => `
                <tr>
                    <td>${pkg.name}</td>
                    <td>${pkg.version}</td>
                    <td>${pkg.comment}</td>
                </tr>
            `).join("");

                const table = `
                <table class="table table-hover table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Version</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            `;
                $packages.html(table);
            });
        }

        loadConnectionStatus();
        loadVersionData();
    });
</script>
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <h2>{{ lang._('Connection Status') }}</h2>
            <div class="table-responsive" id="connStatus"></div>
        </div>
        <div class="col-md-12 hidden" id="peersDetailContainer">
            <h2>{{ lang._('Peers Detail') }}</h2>
            <div class="table-responsive" id="peersDetail"></div>
        </div>
    </div>
    <br>
    <div class="content-box">
        <div class="col-md-12">
            <h2>{{ lang._('Package Versions') }}</h2>
            <div class="table-responsive" id="packages"></div>
        </div>
    </div>
</section>