{#
 # Copyright (C) 2026 pvols79
 # All rights reserved.
 # SPDX-License-Identifier: BSD-2-Clause
 #}

<script>
    $(document).ready(function () {

        {# Populate form from API on page load #}
        mapDataToFormUI({
            'frm_GeneralSettings': '/api/splunkhec/service/get'
        }).done(function () {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            
            // Poll backend to check if log files physically exist
            $.ajax({
                url: '/api/splunkhec/service/checklogs',
                type: 'GET',
                success: function(data) {
                    for (const [key, exists] of Object.entries(data)) {
                        var td = $('#logs\\.' + key).closest('tr').find('td:nth-child(3)');
                        if (exists) {
                            td.append(' <span class="label label-success" style="margin-left:10px;"><i class="fa fa-check"></i> Found</span>');
                        } else {
                            td.append(' <span class="label label-warning" style="margin-left:10px;"><i class="fa fa-warning"></i> Not Found</span>');
                        }
                    }
                }
            });
        });

        {#
         # Apply button — single-step: saveFormToEndpoint POSTs to /set,
         # which saves config AND restarts the daemon server-side.
         # We stop the spinner in both .done() and .fail() so it always
         # resolves regardless of server response.
         #}
        $('#saveAct').on('click', function () {
            var btn  = $(this);
            var icon = $('#saveAct_progress');

            btn.prop('disabled', true);
            icon.addClass('fa fa-spinner fa-spin');

            // Failsafe: ensure spinner stops even if JS encounters a hidden error 
            // or the server crashes silently (HTTP 500).
            setTimeout(function() {
                btn.prop('disabled', false);
                icon.removeClass('fa fa-spinner fa-spin');
            }, 3000);

            // Use explicit callbacks instead of .always() promise chain
            saveFormToEndpoint(
                '/api/splunkhec/service/set',
                'frm_GeneralSettings',
                function () {
                    // Success callback
                    btn.prop('disabled', false);
                    icon.removeClass('fa fa-spinner fa-spin');
                },
                true,
                function () {
                    // Error callback
                    btn.prop('disabled', false);
                    icon.removeClass('fa fa-spinner fa-spin');
                }
            );
        });

    });
</script>

<div class="content-box" style="padding-bottom: 1.5em;">
    <div class="content-box-main">
        <div class="table-responsive">
            <div class="col-md-12">
                <form id="frm_GeneralSettings">

                    {# General Settings #}
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th colspan="2">{{ lang._('General') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="width:30%">
                                    <label for="general.enabled">{{ lang._('Enable Exporter') }}</label>
                                </td>
                                <td>
                                    <input type="checkbox" id="general.enabled" name="general.enabled">
                                    <small class="text-muted">
                                        {{ lang._('Enable or disable the Splunk HEC log forwarder daemon.') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.verify_ssl">{{ lang._('Verify SSL Certificates') }}</label>
                                </td>
                                <td>
                                    <input type="checkbox" id="general.verify_ssl" name="general.verify_ssl" checked="checked">
                                    <small class="text-muted">
                                        {{ lang._('Disable only if using self-signed Splunk certificates with hostname mismatches (e.g. SplunkServerDefaultCert).') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.use_gzip">{{ lang._('Enable GZIP Compression') }}</label>
                                </td>
                                <td>
                                    <input type="checkbox" id="general.use_gzip" name="general.use_gzip" checked="checked">
                                    <small class="text-muted">
                                        {{ lang._('Compress payload batches before sending. Drastically reduces bandwidth usage.') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.enable_telemetry">{{ lang._('Enable System Telemetry') }}</label>
                                </td>
                                <td>
                                    <input type="checkbox" id="general.enable_telemetry" name="general.enable_telemetry">
                                    <small class="text-muted">
                                        {{ lang._('Sample CPU, Memory, Disk, and Firewall state metrics every 60 seconds and ship them as a JSON event.') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.endpoint">{{ lang._('HEC Endpoint URL') }}</label>
                                </td>
                                <td>
                                    <input class="form-control" type="text"
                                           id="general.endpoint" name="general.endpoint"
                                           placeholder="https://splunk.example.com:8088/services/collector/event">
                                    <small class="text-muted">
                                        {{ lang._('Full URL to the Splunk HTTP Event Collector.') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.token">{{ lang._('HEC Token') }}</label>
                                </td>
                                <td>
                                    <input class="form-control" type="password"
                                           id="general.token" name="general.token"
                                           autocomplete="new-password">
                                    <small class="text-muted">
                                        {{ lang._('Authentication token (UUID) for the Splunk HEC input.') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.cache_size">{{ lang._('Cache Size (MB)') }}</label>
                                </td>
                                <td>
                                    <input class="form-control" type="number"
                                           id="general.cache_size" name="general.cache_size"
                                           min="1" max="10000">
                                    <small class="text-muted">
                                        {{ lang._('Maximum on-disk cache size before old payloads are purged.') }}
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="general.cache_time">{{ lang._('Cache Retention (hours)') }}</label>
                                </td>
                                <td>
                                    <input class="form-control" type="number"
                                           id="general.cache_time" name="general.cache_time"
                                           min="1" max="720">
                                    <small class="text-muted">
                                        {{ lang._('Maximum age of cached payloads before purging.') }}
                                    </small>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {# Log Sources #}
                    <table class="table table-striped table-condensed" style="margin-top:1.5em;">
                        <thead>
                            <tr>
                                <th style="width:30%">{{ lang._('Log Source') }}</th>
                                <th style="width:10%">{{ lang._('Enable') }}</th>
                                <th>{{ lang._('Path') }}</th>
                                <th>{{ lang._('Splunk Sourcetype') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>{{ lang._('System Log') }}</strong></td>
                                <td><input type="checkbox" id="logs.system" name="logs.system"></td>
                                <td><code>/var/log/system/latest.log</code></td>
                                <td><code>opnsense:syslog</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Firewall Filter Log') }}</strong></td>
                                <td><input type="checkbox" id="logs.filter" name="logs.filter"></td>
                                <td><code>/var/log/filter/latest.log</code></td>
                                <td><code>opnsense:filterlog</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Config Audit / GUI') }}</strong></td>
                                <td><input type="checkbox" id="logs.audit" name="logs.audit"></td>
                                <td><code>/var/log/audit/latest.log</code></td>
                                <td><code>opnsense:audit</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('DHCP (IPv4/IPv6)') }}</strong></td>
                                <td><input type="checkbox" id="logs.dhcpd" name="logs.dhcpd"></td>
                                <td><code>/var/log/dhcpd/latest.log</code></td>
                                <td><code>opnsense:dhcpd</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('DHCP (Kea)') }}</strong></td>
                                <td><input type="checkbox" id="logs.kea" name="logs.kea"></td>
                                <td><code>/var/log/kea/latest.log</code></td>
                                <td><code>opnsense:kea</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('DNS/DHCP (Dnsmasq)') }}</strong></td>
                                <td><input type="checkbox" id="logs.dnsmasq" name="logs.dnsmasq"></td>
                                <td><code>/var/log/dnsmasq/latest.log</code></td>
                                <td><code>opnsense:dnsmasq</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Web GUI (Lighttpd)') }}</strong></td>
                                <td><input type="checkbox" id="logs.lighttpd" name="logs.lighttpd"></td>
                                <td><code>/var/log/lighttpd/latest.log</code></td>
                                <td><code>opnsense:lighttpd</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('NTP Daemon') }}</strong></td>
                                <td><input type="checkbox" id="logs.ntpd" name="logs.ntpd"></td>
                                <td><code>/var/log/ntpd/latest.log</code></td>
                                <td><code>opnsense:ntpd</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('VPN - OpenVPN') }}</strong></td>
                                <td><input type="checkbox" id="logs.openvpn" name="logs.openvpn"></td>
                                <td><code>/var/log/openvpn/latest.log</code></td>
                                <td><code>opnsense:openvpn</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('VPN - WireGuard') }}</strong></td>
                                <td><input type="checkbox" id="logs.wireguard" name="logs.wireguard"></td>
                                <td><code>/var/log/wireguard/latest.log</code></td>
                                <td><code>opnsense:wireguard</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Routing') }}</strong></td>
                                <td><input type="checkbox" id="logs.routing" name="logs.routing"></td>
                                <td><code>/var/log/routing/latest.log</code></td>
                                <td><code>opnsense:routing</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Suricata (Syslog)') }}</strong></td>
                                <td><input type="checkbox" id="logs.suricata" name="logs.suricata"></td>
                                <td><code>/var/log/suricata/latest.log</code></td>
                                <td><code>opnsense:suricata</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Suricata (EVE JSON)') }}</strong></td>
                                <td><input type="checkbox" id="logs.suricata_eve" name="logs.suricata_eve"></td>
                                <td><code>/var/log/suricata/eve.json</code></td>
                                <td><code>opnsense:suricata:eve</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Unbound DNS') }}</strong></td>
                                <td><input type="checkbox" id="logs.unbound" name="logs.unbound"></td>
                                <td><code>/var/log/unbound/latest.log</code></td>
                                <td><code>opnsense:unbound</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Captive Portal') }}</strong></td>
                                <td><input type="checkbox" id="logs.portalauth" name="logs.portalauth"></td>
                                <td><code>/var/log/portalauth/latest.log</code></td>
                                <td><code>opnsense:portalauth</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('CrowdSec') }}</strong></td>
                                <td><input type="checkbox" id="logs.crowdsec" name="logs.crowdsec"></td>
                                <td><code>/var/log/crowdsec/latest.log</code></td>
                                <td><code>opnsense:crowdsec</code></td>
                            </tr>
                            <tr>
                                <td><strong>{{ lang._('Elasticsearch (Zenarmor Engine)') }}</strong></td>
                                <td><input type="checkbox" id="logs.elasticsearch" name="logs.elasticsearch"></td>
                                <td><code>/var/log/elasticsearch/latest.log</code></td>
                                <td><code>opnsense:elasticsearch</code></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>{{ lang._('Zenarmor (Traffic & Alerts)') }}</strong>
                                    <br>
                                    <small class="text-muted">
                                        <em>Experimental best-effort parser. Not affiliated with Zenarmor or Sunny Valley Networks.</em>
                                    </small>
                                </td>
                                <td><input type="checkbox" id="logs.zenarmor" name="logs.zenarmor"></td>
                                <td><code>/usr/local/zenarmor/.../*.ipdr</code></td>
                                <td><code>opnsense:zenarmor:*</code></td>
                            </tr>
                        </tbody>
                    </table>

                </form>
            </div>
        </div>
    </div>
</div>

<section class="grid-bottom-reserve __mt">
    <div class="alert content-box" style="display: flex; align-items: center; margin-bottom: 0;">
        <button class="btn btn-primary __mr" id="saveAct" type="button">
            <b>{{ lang._('Apply') }}</b>
            <i id="saveAct_progress" class="fa"></i>
        </button>
    </div>
</section>
