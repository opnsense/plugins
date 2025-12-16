{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Hetzner Cloud DNS - Full DNS Zone Management
#}

<style>
    .zone-panel { margin-bottom: 15px; }
    .zone-header { cursor: pointer; padding: 12px 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
    .zone-header:hover { background: #e9ecef; }
    .zone-header.expanded { border-radius: 4px 4px 0 0; border-bottom: none; }
    .zone-name { font-weight: 600; font-size: 16px; }
    .zone-meta { color: #666; font-size: 13px; }
    .zone-records { border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; display: none; }
    .zone-records.show { display: block; }
    .record-type-badge { display: inline-block; min-width: 50px; text-align: center; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
    .record-type-A { background: #d4edda; color: #155724; }
    .record-type-AAAA { background: #cce5ff; color: #004085; }
    .record-type-CNAME { background: #fff3cd; color: #856404; }
    .record-type-MX { background: #f8d7da; color: #721c24; }
    .record-type-TXT { background: #e2e3e5; color: #383d41; }
    /* TXT subtypes */
    .record-type-SPF { background: #d4edda; color: #155724; }
    .record-type-DKIM { background: #fff3cd; color: #856404; }
    .record-type-DMARC { background: #cce5ff; color: #004085; }
    .record-type-GOOGLE { background: #fce8e6; color: #c5221f; }
    .record-type-MSVERIFY { background: #e8f0fe; color: #1a73e8; }
    .record-type-NS { background: #d1ecf1; color: #0c5460; }
    .record-type-SRV { background: #e7d4f2; color: #5a2d82; }
    .record-type-CAA { background: #ffeeba; color: #856404; }
    .record-type-SOA { background: #6c757d; color: #fff; }
    .record-value { font-family: monospace; font-size: 12px; word-break: break-all; }
    .txt-formatted { font-family: inherit; font-size: 12px; }
    .txt-formatted .txt-param { display: inline-block; background: #f0f0f0; padding: 1px 6px; border-radius: 3px; margin: 1px 2px; }
    .txt-formatted .txt-param-key { color: #666; }
    .txt-formatted .txt-param-value { color: #333; font-weight: 500; }
    .txt-formatted .txt-key-short { color: #999; font-family: monospace; font-size: 11px; }
    .zone-records table tbody tr:hover { background-color: #b8d4e8 !important; transition: background-color 0.15s ease; }
    .record-actions { white-space: nowrap; }
    .account-selector { margin-bottom: 20px; }
    .wizard-section { background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
    .wizard-section h5 { margin-top: 0; margin-bottom: 15px; }
    .wizard-preview { background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 13px; margin-top: 10px; }
    .help-text { font-size: 12px; color: #666; margin-top: 5px; }
    /* Record Modal Styling */
    #recordModal .modal-dialog { width: 70%; max-width: 900px; }
    #recordModal .modal-body { padding: 25px 40px; }
    /* Zone header actions */
    .zone-header-actions { display: flex; align-items: center; gap: 10px; }
    .add-zone-record-btn { margin-right: 5px; }
    /* History Modal Styling */
    #historyModal .modal-dialog { width: 80%; max-width: 1100px; }
    .history-item { padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; }
    .history-item:hover { background: #f8f9fa; }
    .history-item.reverted { opacity: 0.6; background: #f5f5f5; }
    .history-action { display: inline-block; min-width: 60px; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-align: center; }
    .history-action-create { background: #d4edda; color: #155724; }
    .history-action-update { background: #fff3cd; color: #856404; }
    .history-action-delete { background: #f8d7da; color: #721c24; }
    .history-time { color: #666; font-size: 12px; }
    .history-record { font-family: monospace; }
    .history-detail-row { padding: 10px 0; border-bottom: 1px solid #eee; }
    .history-detail-label { font-weight: 600; min-width: 120px; display: inline-block; color: #555; }
    .history-value-old { background: #ffeef0; padding: 4px 8px; border-radius: 3px; font-family: monospace; word-break: break-all; }
    .history-value-new { background: #e6ffec; padding: 4px 8px; border-radius: 3px; font-family: monospace; word-break: break-all; }
    /* History Detail Modal */
    #historyDetailModal .modal-dialog { width: 500px; }
    #historyDetailModal .modal-body { padding: 25px 30px; }
    #historyDetailModal .history-detail-row:last-of-type { border-bottom: none; }
    /* Record grouping styles */
    .records-toolbar { padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
    .records-toolbar .search-box { flex: 1; min-width: 200px; max-width: 300px; }
    .records-toolbar .search-box input { width: 100%; }
    .record-type-group { border-bottom: 1px solid #eee; }
    .record-type-group:last-child { border-bottom: none; }
    .record-type-header { padding: 10px 15px; background: #f8f9fa; cursor: pointer; display: flex; align-items: center; justify-content: space-between; }
    .record-type-header:hover { background: #e9ecef; }
    .record-type-header .type-info { display: flex; align-items: center; gap: 10px; }
    .record-type-header .type-badge { font-size: 13px; font-weight: 600; min-width: 55px; }
    .record-type-header .type-count { background: #6c757d; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
    .record-type-header .type-description { color: #666; font-size: 12px; }
    .record-type-body { display: none; }
    .record-type-body.show { display: block; }
    .record-type-group.expanded .record-type-header { background: #e2e6ea; }
    .no-records-match { padding: 20px; text-align: center; color: #666; }
</style>

<!-- No Accounts Warning -->
<div id="noAccountsWarning" class="alert alert-warning" style="display: none;">
    <h4><i class="fa fa-exclamation-triangle"></i> {{ lang._('No API Accounts Configured') }}</h4>
    <p>{{ lang._('You need to add at least one Hetzner DNS API account before you can manage DNS zones.') }}</p>
    <a href="/ui/hclouddns/settings" class="btn btn-warning"><i class="fa fa-cog"></i> {{ lang._('Go to Settings') }}</a>
</div>

<div id="mainContent">
<div class="alert alert-info">
    <i class="fa fa-info-circle"></i> {{ lang._('Full DNS zone management for all your Hetzner DNS zones. Create, edit, and delete any DNS record type.') }}
</div>

<!-- Toolbar -->
<div class="dns-toolbar" style="margin-bottom: 15px;">
    <button class="btn btn-default" id="refreshZonesBtn" disabled><i class="fa fa-refresh"></i> {{ lang._('Refresh Zones') }}</button>
    <button class="btn btn-default" id="historyBtn" disabled><i class="fa fa-history"></i> {{ lang._('Undo / History') }}</button>
</div>

<!-- Account Selector -->
<div class="account-selector" style="margin-bottom: 20px;">
    <div class="row">
        <div class="col-md-4">
            <label>{{ lang._('Select Account') }}</label>
            <select class="form-control selectpicker" id="dnsAccountSelect" data-live-search="true">
                <option value="">{{ lang._('-- Select an account --') }}</option>
            </select>
        </div>
    </div>
</div>

<!-- Zones Container -->
<div id="zonesContainer">
    <div class="text-center text-muted" style="padding: 40px;">
        <i class="fa fa-cloud fa-3x"></i>
        <p style="margin-top: 15px;">{{ lang._('Select an account to view DNS zones') }}</p>
    </div>
</div>

<!-- Add/Edit Record Modal -->
<div class="modal fade" id="recordModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="recordModalTitle">{{ lang._('Add DNS Record') }}</h4>
            </div>
            <div class="modal-body">
                <form id="recordForm">
                    <input type="hidden" id="recordId" value="">
                    <input type="hidden" id="recordZoneId" value="">
                    <input type="hidden" id="recordOldValue" value="">
                    <input type="hidden" id="recordOldTtl" value="">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ lang._('Zone') }}</label>
                                <input type="text" class="form-control" id="recordZoneDisplay" readonly>
                                <input type="hidden" id="recordZone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ lang._('Record Type') }}</label>
                                <select class="form-control" id="recordType" required>
                                    <option value="A">A (IPv4 Address)</option>
                                    <option value="AAAA">AAAA (IPv6 Address)</option>
                                    <option value="CNAME">CNAME (Alias)</option>
                                    <option value="MX">MX (Mail Exchange)</option>
                                    <option value="TXT">TXT (Text Record)</option>
                                    <option value="NS">NS (Name Server)</option>
                                    <option value="SRV">SRV (Service)</option>
                                    <option value="CAA">CAA (Certificate Authority)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ lang._('Name') }}</label>
                                <input type="text" class="form-control" id="recordName" placeholder="@ for root, www, mail, etc." required>
                                <p class="help-text">{{ lang._('Use @ for the root domain') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ lang._('TTL (seconds)') }}</label>
                                <input type="number" class="form-control" id="recordTtl" value="300" min="60" max="86400">
                            </div>
                        </div>
                    </div>

                    <!-- Standard Value Input -->
                    <div class="form-group" id="standardValueGroup">
                        <label>{{ lang._('Value') }}</label>
                        <input type="text" class="form-control" id="recordValue" placeholder="">
                    </div>

                    <!-- MX Priority -->
                    <div class="form-group" id="mxPriorityGroup" style="display: none;">
                        <label>{{ lang._('Priority') }}</label>
                        <input type="number" class="form-control" id="mxPriority" value="10" min="0" max="65535">
                        <p class="help-text">{{ lang._('Lower values = higher priority. Common: 10 (primary), 20 (secondary)') }}</p>
                    </div>

                    <!-- TXT Wizard Section -->
                    <div id="txtWizardSection" style="display: none;">
                        <div class="form-group">
                            <label>{{ lang._('TXT Record Type') }}</label>
                            <select class="form-control" id="txtType">
                                <option value="custom">{{ lang._('Custom TXT Record') }}</option>
                                <option value="spf">SPF (Sender Policy Framework)</option>
                                <option value="dkim">DKIM (DomainKeys Identified Mail)</option>
                                <option value="dmarc">DMARC (Domain-based Message Authentication)</option>
                                <option value="google-site">Google Site Verification</option>
                                <option value="ms-site">Microsoft Domain Verification</option>
                            </select>
                        </div>

                        <!-- SPF Wizard -->
                        <div id="spfWizard" class="wizard-section" style="display: none;">
                            <h5><i class="fa fa-shield"></i> {{ lang._('SPF Record Builder') }}</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="checkbox">
                                        <label><input type="checkbox" id="spfIncludeMx" checked> {{ lang._('Allow MX servers to send mail') }} (mx)</label>
                                    </div>
                                    <div class="checkbox">
                                        <label><input type="checkbox" id="spfIncludeA" checked> {{ lang._('Allow A record IP to send mail') }} (a)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ lang._('Include other domains (one per line)') }}</label>
                                        <textarea class="form-control" id="spfIncludes" rows="3" placeholder="e.g. _spf.google.com"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ lang._('Additional IPs (one per line)') }}</label>
                                        <textarea class="form-control" id="spfIps" rows="2" placeholder="e.g. 192.168.1.1"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ lang._('Policy for non-matching senders') }}</label>
                                        <select class="form-control" id="spfPolicy">
                                            <option value="~all">~all (Soft Fail - Recommended)</option>
                                            <option value="-all">-all (Hard Fail - Strict)</option>
                                            <option value="?all">?all (Neutral)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-preview" id="spfPreview">v=spf1 mx a ~all</div>
                        </div>

                        <!-- DKIM Wizard -->
                        <div id="dkimWizard" class="wizard-section" style="display: none;">
                            <h5><i class="fa fa-key"></i> {{ lang._('DKIM Record') }}</h5>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> {{ lang._('DKIM records are usually provided by your email service. Paste the public key below.') }}
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ lang._('Selector') }}</label>
                                        <input type="text" class="form-control" id="dkimSelector" placeholder="e.g. default, google, mail">
                                        <p class="help-text">{{ lang._('The name will be: selector._domainkey') }}</p>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>{{ lang._('Public Key (p=...)') }}</label>
                                        <textarea class="form-control" id="dkimKey" rows="3" placeholder="MIGfMA0GCSqGSIb3DQEBAQUAA4..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-preview" id="dkimPreview">v=DKIM1; k=rsa; p=YOUR_KEY_HERE</div>
                        </div>

                        <!-- DMARC Wizard -->
                        <div id="dmarcWizard" class="wizard-section" style="display: none;">
                            <h5><i class="fa fa-check-circle"></i> {{ lang._('DMARC Record Builder') }}</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ lang._('Policy') }}</label>
                                        <select class="form-control" id="dmarcPolicy">
                                            <option value="none">none (Monitor only - Start here)</option>
                                            <option value="quarantine">quarantine (Send to spam)</option>
                                            <option value="reject">reject (Block completely)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ lang._('Report Email (rua)') }}</label>
                                        <input type="email" class="form-control" id="dmarcRua" placeholder="dmarc@example.com">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ lang._('Percentage') }}</label>
                                        <input type="number" class="form-control" id="dmarcPct" value="100" min="0" max="100">
                                        <p class="help-text">{{ lang._('% of messages to apply policy') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-preview" id="dmarcPreview">v=DMARC1; p=none; pct=100;</div>
                        </div>
                    </div>

                    <!-- SRV Fields -->
                    <div id="srvFieldsGroup" style="display: none;">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ lang._('Priority') }}</label>
                                    <input type="number" class="form-control" id="srvPriority" value="10" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ lang._('Weight') }}</label>
                                    <input type="number" class="form-control" id="srvWeight" value="100" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ lang._('Port') }}</label>
                                    <input type="number" class="form-control" id="srvPort" min="1" max="65535">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ lang._('Target') }}</label>
                                    <input type="text" class="form-control" id="srvTarget" placeholder="server.example.com">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CAA Fields -->
                    <div id="caaFieldsGroup" style="display: none;">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ lang._('Flags') }}</label>
                                    <input type="number" class="form-control" id="caaFlags" value="0" min="0" max="255">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ lang._('Tag') }}</label>
                                    <select class="form-control" id="caaTag">
                                        <option value="issue">issue (Allow CA)</option>
                                        <option value="issuewild">issuewild (Wildcard)</option>
                                        <option value="iodef">iodef (Report URL)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ lang._('Value') }}</label>
                                    <input type="text" class="form-control" id="caaValue" placeholder="letsencrypt.org">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="deleteRecordBtn" style="display: none;"><i class="fa fa-trash"></i> {{ lang._('Delete') }}</button>
                <button type="button" class="btn btn-primary" id="saveRecordBtn"><i class="fa fa-save"></i> {{ lang._('Save Record') }}</button>
            </div>
        </div>
    </div>
</div>
</div><!-- /mainContent -->

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-history"></i> {{ lang._('DNS Change History') }}</h4>
            </div>
            <div class="modal-body">
                <div id="historyList">
                    <div class="text-center text-muted" style="padding: 40px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p style="margin-top: 15px;">{{ lang._('Loading history...') }}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- History Detail Modal -->
<div class="modal fade" id="historyDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="historyDetailTitle">{{ lang._('Change Details') }}</h4>
            </div>
            <div class="modal-body" id="historyDetailBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-warning" id="revertBtn"><i class="fa fa-undo"></i> {{ lang._('Revert This Change') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Create DynDNS Entry Modal -->
<div class="modal fade" id="createDynDnsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-bolt"></i> {{ lang._('Create DynDNS Entry') }}</h4>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <input type="hidden" id="dynDnsAccountUuid">
                <input type="hidden" id="dynDnsZoneId">
                <input type="hidden" id="dynDnsZoneName">
                <input type="hidden" id="dynDnsRecordName">
                <input type="hidden" id="dynDnsRecordType">
                <input type="hidden" id="dynDnsTtl">

                <table class="table table-condensed" style="margin-bottom: 20px;">
                    <tr><td style="width: 100px;"><strong>{{ lang._('Zone') }}:</strong></td><td id="dynDnsZoneDisplay"></td></tr>
                    <tr><td><strong>{{ lang._('Record') }}:</strong></td><td id="dynDnsRecordDisplay"></td></tr>
                    <tr><td><strong>{{ lang._('Type') }}:</strong></td><td id="dynDnsTypeDisplay"></td></tr>
                </table>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ lang._('Primary Gateway') }}</label>
                            <select class="form-control selectpicker" id="dynDnsPrimaryGw" data-live-search="true" data-container="body"></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ lang._('Failover Gateway') }} <small class="text-muted">({{ lang._('optional') }})</small></label>
                            <select class="form-control selectpicker" id="dynDnsFailoverGw" data-live-search="true" data-container="body"></select>
                        </div>
                    </div>
                </div>

                <p class="text-muted" style="margin-top: 15px; margin-bottom: 0;">
                    <small><i class="fa fa-info-circle"></i> {{ lang._('The record will be managed by DynDNS and updated automatically when your gateway IP changes.') }}</small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="createDynDnsBtn"><i class="fa fa-bolt"></i> {{ lang._('Create') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var currentAccountUuid = '';
    var zonesData = {};
    var isEditMode = false; // Track whether we're editing an existing record

    // Load accounts and check if any exist
    ajaxCall('/api/hclouddns/accounts/searchItem', {}, function(data) {
        var $select = $('#dnsAccountSelect');
        var enabledAccounts = [];
        if (data && data.rows && data.rows.length > 0) {
            $.each(data.rows, function(i, acc) {
                if (acc.enabled === '1') {
                    enabledAccounts.push(acc);
                    $select.append('<option value="' + acc.uuid + '">' + acc.name + ' (' + (acc['%apiType'] || acc.apiType) + ')</option>');
                }
            });
            $select.selectpicker('refresh');
        }

        // Show/hide based on accounts
        if (enabledAccounts.length === 0) {
            $('#noAccountsWarning').show();
            $('#mainContent').hide();
        } else {
            $('#noAccountsWarning').hide();
            $('#mainContent').show();

            // Auto-select if only one account
            if (enabledAccounts.length === 1) {
                $('.account-selector').hide();
                currentAccountUuid = enabledAccounts[0].uuid;
                $select.val(currentAccountUuid);
                $select.selectpicker('refresh');
                $('#refreshZonesBtn').prop('disabled', false);
                $('#historyBtn').prop('disabled', false);
                loadZones();
            }
        }
    });

    // Account selection change
    $('#dnsAccountSelect').on('change', function() {
        currentAccountUuid = $(this).val();
        if (currentAccountUuid) {
            $('#refreshZonesBtn').prop('disabled', false);
            $('#historyBtn').prop('disabled', false);
            loadZones();
        } else {
            $('#refreshZonesBtn').prop('disabled', true);
            $('#historyBtn').prop('disabled', true);
            $('#zonesContainer').html('<div class="text-center text-muted" style="padding: 40px;"><i class="fa fa-cloud fa-3x"></i><p style="margin-top: 15px;">{{ lang._("Select an account to view DNS zones") }}</p></div>');
        }
    });

    $('#refreshZonesBtn').on('click', function() {
        loadZones();
    });

    function loadRecordCount(zoneId) {
        ajaxCall('/api/hclouddns/hetzner/listRecordsForAccount', {account_uuid: currentAccountUuid, zone_id: zoneId, all_types: '1'}, function(data) {
            var count = (data && data.status === 'ok' && data.records) ? data.records.length : 0;
            $('[data-zone-id="' + zoneId + '"] .zone-record-count').text('(' + count + ' records)');
        });
    }

    function loadZones() {
        $('#zonesContainer').html('<div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">{{ lang._("Loading zones...") }}</p></div>');

        // Load DynDNS entries first, then load zones
        loadDynDnsEntries(function() {
            ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: currentAccountUuid}, function(data) {
            if (data && data.status === 'ok' && data.zones) {
                zonesData = {};
                var html = '';
                $.each(data.zones, function(i, zone) {
                    zonesData[zone.id] = zone;
                    html += '<div class="zone-panel" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '">' +
                        '<div class="zone-header">' +
                            '<div><span class="zone-name">' + zone.name + '</span> <span class="zone-meta zone-record-count">(<i class="fa fa-spinner fa-spin"></i>)</span></div>' +
                            '<div class="zone-header-actions">' +
                                '<button class="btn btn-xs btn-success add-zone-record-btn" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '" title="{{ lang._("Add Record") }}"><i class="fa fa-plus"></i></button> ' +
                                '<i class="fa fa-chevron-right zone-toggle"></i>' +
                            '</div>' +
                        '</div>' +
                        '<div class="zone-records" id="zone-records-' + zone.id + '"><div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div></div>' +
                    '</div>';
                });
                $('#zonesContainer').html(html || '<div class="alert alert-warning">{{ lang._("No zones found for this account.") }}</div>');

                // Load record counts for all zones
                $.each(data.zones, function(i, zone) {
                    loadRecordCount(zone.id);
                });
            } else {
                $('#zonesContainer').html('<div class="alert alert-danger">{{ lang._("Failed to load zones:") }} ' + (data.message || 'Unknown error') + '</div>');
            }
            });
        });
    }

    // Zone expand/collapse
    $(document).on('click', '.zone-header', function() {
        var $panel = $(this).closest('.zone-panel');
        var $records = $panel.find('.zone-records');
        var $icon = $(this).find('.zone-toggle');
        var zoneId = $panel.data('zone-id');

        if ($records.hasClass('show')) {
            $records.removeClass('show');
            $(this).removeClass('expanded');
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            $records.addClass('show');
            $(this).addClass('expanded');
            $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            loadRecords(zoneId);
        }
    });

    // Type descriptions for UI
    var typeDescriptions = {
        'A': 'IPv4 Address',
        'AAAA': 'IPv6 Address',
        'CNAME': 'Alias',
        'MX': 'Mail Exchange',
        'TXT': 'Text Record',
        'NS': 'Name Server',
        'SRV': 'Service',
        'CAA': 'Certificate Authority',
        'SOA': 'Start of Authority'
    };

    // Store records data per zone for filtering
    var zoneRecordsCache = {};

    // Store existing DynDNS entries for marking
    var dynDnsEntriesCache = {};

    // Load DynDNS entries for the current account
    function loadDynDnsEntries(callback) {
        ajaxCall('/api/hclouddns/entries/searchItem', {}, function(data) {
            dynDnsEntriesCache = {};
            if (data && data.rows) {
                $.each(data.rows, function(i, entry) {
                    // Key: zoneId + recordName + recordType
                    var key = entry.zoneId + ':' + entry.recordName + ':' + entry.recordType;
                    dynDnsEntriesCache[key] = true;
                });
            }
            if (callback) callback();
        });
    }

    // Check if a record is already configured as DynDNS
    function isDynDnsConfigured(zoneId, recordName, recordType) {
        var key = zoneId + ':' + recordName + ':' + recordType;
        return dynDnsEntriesCache[key] === true;
    }

    // Get display info for TXT record subtypes
    function getTxtDisplayType(value) {
        if (!value) return { type: 'TXT', label: 'TXT', cssClass: 'record-type-TXT' };
        var v = value.trim();
        if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
            v = v.slice(1, -1);
        }
        if (v.toLowerCase().startsWith('v=spf1')) return { type: 'TXT', label: 'SPF', cssClass: 'record-type-SPF', subtype: 'spf' };
        if (v.toLowerCase().startsWith('v=dkim1')) return { type: 'TXT', label: 'DKIM', cssClass: 'record-type-DKIM', subtype: 'dkim' };
        if (v.toLowerCase().startsWith('v=dmarc1')) return { type: 'TXT', label: 'DMARC', cssClass: 'record-type-DMARC', subtype: 'dmarc' };
        if (v.toLowerCase().startsWith('google-site-verification=')) return { type: 'TXT', label: 'Google', cssClass: 'record-type-GOOGLE', subtype: 'google' };
        if (v.toUpperCase().startsWith('MS=')) return { type: 'TXT', label: 'MS', cssClass: 'record-type-MSVERIFY', subtype: 'ms' };
        return { type: 'TXT', label: 'TXT', cssClass: 'record-type-TXT', subtype: 'custom' };
    }

    // Format TXT value for nice display
    function formatTxtValue(value, subtype) {
        if (!value) return '';
        var v = value.trim();
        if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
            v = v.slice(1, -1);
        }

        if (subtype === 'spf') {
            // SPF: show mechanisms as badges
            var parts = v.split(/\s+/);
            var html = '<span class="txt-formatted">';
            $.each(parts, function(i, part) {
                if (part.toLowerCase() === 'v=spf1') return;
                var cls = 'txt-param';
                if (part === '-all') cls += ' label-danger';
                else if (part === '~all') cls += ' label-warning';
                html += '<span class="' + cls + '">' + escapeHtml(part) + '</span>';
            });
            return html + '</span>';
        }

        if (subtype === 'dkim') {
            // DKIM: show key type and shortened key
            var keyMatch = v.match(/p=([A-Za-z0-9+\/=]+)/);
            var keyType = v.match(/k=(\w+)/);
            var html = '<span class="txt-formatted">';
            if (keyType) html += '<span class="txt-param"><span class="txt-param-key">k=</span><span class="txt-param-value">' + keyType[1] + '</span></span>';
            if (keyMatch && keyMatch[1]) {
                var key = keyMatch[1];
                var shortKey = key.length > 20 ? key.substring(0, 10) + '...' + key.substring(key.length - 10) : key;
                html += '<span class="txt-param"><span class="txt-param-key">p=</span><span class="txt-key-short">' + shortKey + '</span></span>';
                html += '<span class="text-muted" style="font-size: 11px; margin-left: 5px;">(' + key.length + ' chars)</span>';
            }
            return html + '</span>';
        }

        if (subtype === 'dmarc') {
            // DMARC: show policy params as badges
            var html = '<span class="txt-formatted">';
            var params = v.split(/;\s*/);
            $.each(params, function(i, param) {
                param = param.trim();
                if (!param || param.toLowerCase() === 'v=dmarc1') return;
                var kv = param.split('=');
                if (kv.length === 2) {
                    var cls = 'txt-param';
                    if (kv[0].toLowerCase() === 'p') {
                        if (kv[1].toLowerCase() === 'reject') cls += ' label-danger';
                        else if (kv[1].toLowerCase() === 'quarantine') cls += ' label-warning';
                    }
                    html += '<span class="' + cls + '"><span class="txt-param-key">' + kv[0] + '=</span><span class="txt-param-value">' + escapeHtml(kv[1]) + '</span></span>';
                }
            });
            return html + '</span>';
        }

        if (subtype === 'google') {
            var code = v.replace(/^google-site-verification=/i, '');
            return '<span class="txt-formatted"><span class="txt-param"><span class="txt-param-value">' + escapeHtml(code) + '</span></span></span>';
        }

        if (subtype === 'ms') {
            var code = v.replace(/^MS=/i, '');
            return '<span class="txt-formatted"><span class="txt-param"><span class="txt-param-value">' + escapeHtml(code) + '</span></span></span>';
        }

        // Default: truncate if too long
        if (v.length > 80) {
            return '<span class="record-value" title="' + escapeHtml(value) + '">' + escapeHtml(v.substring(0, 80)) + '...</span>';
        }
        return '<span class="record-value">' + escapeHtml(v) + '</span>';
    }

    function loadRecords(zoneId) {
        var $container = $('#zone-records-' + zoneId);
        $container.html('<div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

        ajaxCall('/api/hclouddns/hetzner/listRecordsForAccount', {account_uuid: currentAccountUuid, zone_id: zoneId, all_types: '1'}, function(data) {
            if (data && data.status === 'ok' && data.records) {
                // Cache records for filtering
                zoneRecordsCache[zoneId] = data.records;
                renderRecordsGrouped(zoneId, data.records, '', '');
            } else {
                $container.html('<div class="alert alert-danger m-2">Failed to load records</div>');
            }
        });
    }

    function renderRecordsGrouped(zoneId, records, filterType, searchText) {
        var $container = $('#zone-records-' + zoneId);

        // Group records by type
        var grouped = {};
        var typeOrder = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'SOA'];

        $.each(records, function(i, rec) {
            if (!grouped[rec.type]) grouped[rec.type] = [];
            grouped[rec.type].push(rec);
        });

        // Build toolbar HTML
        var html = '<div class="records-toolbar" data-zone-id="' + zoneId + '">' +
            '<div class="filter-box">' +
                '<select class="form-control input-sm record-type-filter" data-zone-id="' + zoneId + '">' +
                    '<option value="">{{ lang._("All Types") }}</option>';

        // Add type options based on what exists
        $.each(typeOrder, function(i, t) {
            if (grouped[t]) {
                html += '<option value="' + t + '"' + (filterType === t ? ' selected' : '') + '>' + t + ' (' + grouped[t].length + ')</option>';
            }
        });
        // Add any other types not in the standard order
        $.each(grouped, function(t, recs) {
            if (typeOrder.indexOf(t) === -1) {
                html += '<option value="' + t + '"' + (filterType === t ? ' selected' : '') + '>' + t + ' (' + recs.length + ')</option>';
            }
        });

        html += '</select></div>' +
            '<div class="search-box">' +
                '<input type="text" class="form-control input-sm record-search" data-zone-id="' + zoneId + '" placeholder="{{ lang._("Search records...") }}" value="' + (searchText || '') + '">' +
            '</div>' +
            '<div class="records-summary text-muted"></div>' +
        '</div>';

        // Render grouped records
        html += '<div class="records-grouped-container">';

        var totalShown = 0;
        var totalRecords = records.length;

        $.each(typeOrder, function(i, type) {
            if (!grouped[type]) return;
            var typeRecords = grouped[type];

            // Filter by type if specified
            if (filterType && filterType !== type) return;

            // Filter by search text
            if (searchText) {
                typeRecords = typeRecords.filter(function(rec) {
                    return rec.name.toLowerCase().indexOf(searchText.toLowerCase()) !== -1 ||
                           rec.value.toLowerCase().indexOf(searchText.toLowerCase()) !== -1;
                });
            }

            if (typeRecords.length === 0) return;
            totalShown += typeRecords.length;

            var typeClass = 'record-type-' + type;
            var isExpanded = true; // Default expanded

            html += '<div class="record-type-group' + (isExpanded ? ' expanded' : '') + '" data-type="' + type + '">' +
                '<div class="record-type-header">' +
                    '<div class="type-info">' +
                        '<span class="record-type-badge type-badge ' + typeClass + '">' + type + '</span>' +
                        '<span class="type-count">' + typeRecords.length + '</span>' +
                        '<span class="type-description">' + (typeDescriptions[type] || '') + '</span>' +
                    '</div>' +
                    '<i class="fa fa-chevron-' + (isExpanded ? 'down' : 'right') + ' type-toggle"></i>' +
                '</div>' +
                '<div class="record-type-body' + (isExpanded ? ' show' : '') + '">' +
                    '<table class="table table-condensed table-hover" style="margin: 0;">' +
                        '<thead><tr><th style="width: 200px;">{{ lang._("Name") }}</th><th>{{ lang._("Value") }}</th><th style="width: 70px;">{{ lang._("TTL") }}</th><th style="width: 100px;">{{ lang._("Actions") }}</th></tr></thead>' +
                        '<tbody>';

            $.each(typeRecords, function(j, rec) {
                var isSystemRecord = type === 'SOA' || type === 'NS';
                var isDynDnsCompatible = type === 'A' || type === 'AAAA';
                var isAlreadyDynDns = isDynDnsConfigured(zoneId, rec.name, type);
                var dynDnsBtn = '';
                if (isDynDnsCompatible) {
                    if (isAlreadyDynDns) {
                        dynDnsBtn = '<button class="btn btn-xs btn-success" title="{{ lang._("Managed by DynDNS") }}" disabled><i class="fa fa-bolt"></i></button> ';
                    } else {
                        dynDnsBtn = '<button class="btn btn-xs btn-info create-dyndns-btn" title="{{ lang._("Create DynDNS Entry") }}" data-record-name="' + rec.name + '" data-record-type="' + type + '" data-ttl="' + (rec.ttl || 300) + '"><i class="fa fa-bolt"></i></button> ';
                    }
                }
                var actionButtons = isSystemRecord ?
                    '<span class="text-muted" title="{{ lang._("SOA/NS records are managed by Hetzner") }}"><i class="fa fa-lock"></i></span>' :
                    dynDnsBtn +
                    '<button class="btn btn-xs btn-default edit-record-btn" title="{{ lang._("Edit") }}"><i class="fa fa-pencil"></i></button> ' +
                    '<button class="btn btn-xs btn-danger delete-record-btn" title="{{ lang._("Delete") }}"><i class="fa fa-trash"></i></button>';

                // For TXT records, show subtype badge and formatted value
                var nameBadge = '';
                var valueDisplay = '';
                if (type === 'TXT') {
                    var txtInfo = getTxtDisplayType(rec.value);
                    if (txtInfo.label !== 'TXT') {
                        nameBadge = '<span class="record-type-badge ' + txtInfo.cssClass + '" style="margin-right: 6px; min-width: 45px;">' + txtInfo.label + '</span>';
                    }
                    valueDisplay = formatTxtValue(rec.value, txtInfo.subtype);
                } else {
                    valueDisplay = '<span class="record-value" title="' + escapeHtml(rec.value) + '">' + escapeHtml(rec.value) + '</span>';
                }

                html += '<tr data-record-id="' + (rec.id || '') + '" data-zone-id="' + zoneId + '">' +
                    '<td>' + nameBadge + rec.name + '</td>' +
                    '<td title="' + escapeHtml(rec.value) + '">' + valueDisplay + '</td>' +
                    '<td>' + (rec.ttl || 300) + '</td>' +
                    '<td class="record-actions">' + actionButtons + '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div></div>';
        });

        // Handle any types not in the standard order
        $.each(grouped, function(type, typeRecords) {
            if (typeOrder.indexOf(type) !== -1) return;

            if (filterType && filterType !== type) return;

            if (searchText) {
                typeRecords = typeRecords.filter(function(rec) {
                    return rec.name.toLowerCase().indexOf(searchText.toLowerCase()) !== -1 ||
                           rec.value.toLowerCase().indexOf(searchText.toLowerCase()) !== -1;
                });
            }

            if (typeRecords.length === 0) return;
            totalShown += typeRecords.length;

            var typeClass = 'record-type-' + type;

            html += '<div class="record-type-group expanded" data-type="' + type + '">' +
                '<div class="record-type-header">' +
                    '<div class="type-info">' +
                        '<span class="record-type-badge type-badge ' + typeClass + '">' + type + '</span>' +
                        '<span class="type-count">' + typeRecords.length + '</span>' +
                    '</div>' +
                    '<i class="fa fa-chevron-down type-toggle"></i>' +
                '</div>' +
                '<div class="record-type-body show">' +
                    '<table class="table table-condensed table-hover" style="margin: 0;">' +
                        '<thead><tr><th style="width: 200px;">{{ lang._("Name") }}</th><th>{{ lang._("Value") }}</th><th style="width: 70px;">{{ lang._("TTL") }}</th><th style="width: 100px;">{{ lang._("Actions") }}</th></tr></thead>' +
                        '<tbody>';

            $.each(typeRecords, function(j, rec) {
                var isSystemRecord = type === 'SOA' || type === 'NS';
                var isDynDnsCompatible = type === 'A' || type === 'AAAA';
                var isAlreadyDynDns = isDynDnsConfigured(zoneId, rec.name, type);
                var dynDnsBtn = '';
                if (isDynDnsCompatible) {
                    if (isAlreadyDynDns) {
                        dynDnsBtn = '<button class="btn btn-xs btn-success" title="{{ lang._("Managed by DynDNS") }}" disabled><i class="fa fa-bolt"></i></button> ';
                    } else {
                        dynDnsBtn = '<button class="btn btn-xs btn-info create-dyndns-btn" title="{{ lang._("Create DynDNS Entry") }}" data-record-name="' + rec.name + '" data-record-type="' + type + '" data-ttl="' + (rec.ttl || 300) + '"><i class="fa fa-bolt"></i></button> ';
                    }
                }
                var actionButtons = isSystemRecord ?
                    '<span class="text-muted" title="{{ lang._("SOA/NS records are managed by Hetzner") }}"><i class="fa fa-lock"></i></span>' :
                    dynDnsBtn +
                    '<button class="btn btn-xs btn-default edit-record-btn" title="{{ lang._("Edit") }}"><i class="fa fa-pencil"></i></button> ' +
                    '<button class="btn btn-xs btn-danger delete-record-btn" title="{{ lang._("Delete") }}"><i class="fa fa-trash"></i></button>';

                // For TXT records, show subtype badge and formatted value
                var nameBadge = '';
                var valueDisplay = '';
                if (type === 'TXT') {
                    var txtInfo = getTxtDisplayType(rec.value);
                    if (txtInfo.label !== 'TXT') {
                        nameBadge = '<span class="record-type-badge ' + txtInfo.cssClass + '" style="margin-right: 6px; min-width: 45px;">' + txtInfo.label + '</span>';
                    }
                    valueDisplay = formatTxtValue(rec.value, txtInfo.subtype);
                } else {
                    valueDisplay = '<span class="record-value" title="' + escapeHtml(rec.value) + '">' + escapeHtml(rec.value) + '</span>';
                }

                html += '<tr data-record-id="' + (rec.id || '') + '" data-zone-id="' + zoneId + '">' +
                    '<td>' + nameBadge + rec.name + '</td>' +
                    '<td title="' + escapeHtml(rec.value) + '">' + valueDisplay + '</td>' +
                    '<td>' + (rec.ttl || 300) + '</td>' +
                    '<td class="record-actions">' + actionButtons + '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div></div>';
        });

        html += '</div>';

        if (totalShown === 0) {
            html = html.replace('<div class="records-grouped-container">', '<div class="records-grouped-container"><div class="no-records-match"><i class="fa fa-search"></i> {{ lang._("No records match your filter") }}</div>');
        }

        $container.html(html);

        // Update summary
        var summaryText = totalShown + ' {{ lang._("of") }} ' + totalRecords + ' {{ lang._("records") }}';
        if (filterType || searchText) {
            summaryText += ' ({{ lang._("filtered") }})';
        }
        $container.find('.records-summary').text(summaryText);

        // Update record count in zone header
        $('[data-zone-id="' + zoneId + '"] .zone-record-count').text('(' + totalRecords + ' records)');
    }

    // Event handlers for filter and search
    $(document).on('change', '.record-type-filter', function() {
        var zoneId = $(this).data('zone-id');
        var filterType = $(this).val();
        var searchText = $('.record-search[data-zone-id="' + zoneId + '"]').val();
        var records = zoneRecordsCache[zoneId] || [];
        renderRecordsGrouped(zoneId, records, filterType, searchText);
    });

    $(document).on('keyup', '.record-search', function() {
        var zoneId = $(this).data('zone-id');
        var searchText = $(this).val();
        var filterType = $('.record-type-filter[data-zone-id="' + zoneId + '"]').val();
        var records = zoneRecordsCache[zoneId] || [];
        renderRecordsGrouped(zoneId, records, filterType, searchText);
    });

    // Type group expand/collapse
    $(document).on('click', '.record-type-header', function(e) {
        if ($(e.target).closest('button').length) return; // Ignore button clicks
        var $group = $(this).closest('.record-type-group');
        var $body = $group.find('.record-type-body');
        var $icon = $(this).find('.type-toggle');

        if ($body.hasClass('show')) {
            $body.removeClass('show');
            $group.removeClass('expanded');
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            $body.addClass('show');
            $group.addClass('expanded');
            $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
    });

    // Add Record button (per zone)
    $(document).on('click', '.add-zone-record-btn', function(e) {
        e.stopPropagation();
        var zoneId = $(this).data('zone-id');
        var zoneName = $(this).data('zone-name');
        resetRecordForm();
        isEditMode = false;
        $('#recordZone').val(zoneId);
        $('#recordZoneDisplay').val(zoneName);
        $('#recordModalTitle').text('{{ lang._("Add DNS Record") }} - ' + zoneName);
        $('#deleteRecordBtn').hide();
        $('#recordModal').modal('show');
    });

    // Edit record
    $(document).on('click', '.edit-record-btn', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var zoneId = $row.data('zone-id');
        var $zonePanel = $row.closest('.zone-panel');
        var zoneName = $zonePanel.data('zone-name');

        isEditMode = true; // We're editing an existing record
        $('#recordModalTitle').text('{{ lang._("Edit DNS Record") }} - ' + zoneName);
        $('#recordZoneId').val(zoneId);
        $('#recordZone').val(zoneId);
        $('#recordZoneDisplay').val(zoneName);
        $('#deleteRecordBtn').show();

        // Get record data from row
        var type = $row.find('.record-type-badge').text();
        var name = $row.find('td:eq(1)').text();
        var value = $row.find('.record-value').attr('title');
        var ttl = $row.find('td:eq(3)').text();

        // Store old values for history
        $('#recordOldValue').val(value);
        $('#recordOldTtl').val(ttl);

        $('#recordName').val(name);
        $('#recordTtl').val(ttl);
        $('#recordType').val(type).trigger('change');

        // For TXT records, auto-detect and populate the appropriate wizard
        if (type === 'TXT') {
            populateTxtWizard(value, name);
        } else if (type === 'MX') {
            // Parse MX value: "priority target" format
            var mxParts = value.match(/^(\d+)\s+(.+)$/);
            if (mxParts) {
                $('#mxPriority').val(mxParts[1]);
                $('#recordValue').val(mxParts[2]);
            } else {
                $('#recordValue').val(value);
            }
        } else if (type === 'SRV') {
            // Parse SRV value: "priority weight port target" format
            var srvParts = value.match(/^(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/);
            if (srvParts) {
                $('#srvPriority').val(srvParts[1]);
                $('#srvWeight').val(srvParts[2]);
                $('#srvPort').val(srvParts[3]);
                $('#srvTarget').val(srvParts[4]);
            }
        } else if (type === 'CAA') {
            // Parse CAA value: "flags tag value" format
            var caaParts = value.match(/^(\d+)\s+(\w+)\s+"?([^"]+)"?$/);
            if (caaParts) {
                $('#caaFlags').val(caaParts[1]);
                $('#caaTag').val(caaParts[2]);
                $('#caaValue').val(caaParts[3]);
            }
        } else {
            $('#recordValue').val(value);
        }

        $('#recordModal').modal('show');
    });

    // Delete record button in table
    $(document).on('click', '.delete-record-btn', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var zoneId = $row.data('zone-id');
        var $zonePanel = $row.closest('.zone-panel');
        var zoneName = $zonePanel.data('zone-name');
        var name = $row.find('td:eq(1)').text();
        var type = $row.find('.record-type-badge').text();
        var value = $row.find('.record-value').attr('title');
        var ttl = $row.find('td:eq(3)').text();

        BootstrapDialog.confirm({
            title: '{{ lang._("Confirm Delete") }}',
            message: '{{ lang._("Delete record") }} <strong>' + name + '</strong> (' + type + ')?',
            type: BootstrapDialog.TYPE_DANGER,
            btnOKLabel: '{{ lang._("Delete") }}',
            btnOKClass: 'btn-danger',
            callback: function(result) {
                if (result) {
                    ajaxCall('/api/hclouddns/hetzner/deleteRecord', {
                        account_uuid: currentAccountUuid,
                        zone_id: zoneId,
                        zone_name: zoneName,
                        record_name: name,
                        record_type: type,
                        old_value: value,
                        old_ttl: ttl
                    }, function(data) {
                        if (data && data.status === 'ok') {
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_SUCCESS,
                                message: data.message || '{{ lang._("Record deleted successfully") }}'
                            });
                            loadRecords(zoneId);
                        } else {
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_DANGER,
                                title: '{{ lang._("Error") }}',
                                message: '{{ lang._("Failed to delete record") }}: ' + name + ' (' + type + ')<br/><br/><strong>{{ lang._("Details") }}:</strong> ' + (data.message || '{{ lang._("Unknown error") }}')
                            });
                        }
                    });
                }
            }
        });
    });

    // Create DynDNS Entry from A/AAAA record
    $(document).on('click', '.create-dyndns-btn', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var $zonePanel = $row.closest('.zone-panel');
        var zoneId = $row.data('zone-id');
        var zoneName = $zonePanel.data('zone-name');
        var recordName = $(this).data('record-name');
        var recordType = $(this).data('record-type');
        var ttl = $(this).data('ttl') || 60;

        // Populate modal fields
        $('#dynDnsAccountUuid').val(currentAccountUuid);
        $('#dynDnsZoneId').val(zoneId);
        $('#dynDnsZoneName').val(zoneName);
        $('#dynDnsRecordName').val(recordName);
        $('#dynDnsRecordType').val(recordType);
        $('#dynDnsTtl').val(ttl);
        $('#dynDnsZoneDisplay').text(zoneName);
        $('#dynDnsRecordDisplay').text(recordName);
        $('#dynDnsTypeDisplay').text(recordType);

        // Populate gateway selectors
        var $primaryGw = $('#dynDnsPrimaryGw').empty().append('<option value="">Default Gateway (auto-detect)</option>');
        var $failoverGw = $('#dynDnsFailoverGw').empty().append('<option value="">-- None --</option>');

        ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(gwData) {
            if (gwData && gwData.rows && gwData.rows.length > 0) {
                var sorted = gwData.rows.filter(function(gw) { return gw.enabled === '1'; })
                    .sort(function(a, b) { return parseInt(a.priority) - parseInt(b.priority); });

                $.each(sorted, function(i, gw) {
                    $primaryGw.append('<option value="' + gw.uuid + '">' + gw.name + ' (Prio ' + gw.priority + ')</option>');
                    $failoverGw.append('<option value="' + gw.uuid + '">' + gw.name + ' (Prio ' + gw.priority + ')</option>');
                });

                // Auto-select: first sorted = primary, second sorted = failover
                if (sorted.length > 0) $primaryGw.selectpicker('val', sorted[0].uuid);
                if (sorted.length > 1) $failoverGw.selectpicker('val', sorted[1].uuid);
            }
            $primaryGw.selectpicker('refresh');
            $failoverGw.selectpicker('refresh');
        });

        $('#createDynDnsModal').modal('show');
    });

    // Create DynDNS Entry - submit
    $('#createDynDnsBtn').on('click', function() {
        var primaryGw = $('#dynDnsPrimaryGw').val();
        var failoverGw = $('#dynDnsFailoverGw').val();

        if (primaryGw && primaryGw === failoverGw) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, title: 'Invalid Selection', message: 'Failover gateway must differ from primary gateway.'});
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

        var entry = {
            account: $('#dynDnsAccountUuid').val(),
            zoneId: $('#dynDnsZoneId').val(),
            zoneName: $('#dynDnsZoneName').val(),
            recordName: $('#dynDnsRecordName').val(),
            recordType: $('#dynDnsRecordType').val(),
            ttl: $('#dynDnsTtl').val()
        };

        ajaxCall('/api/hclouddns/entries/batchAdd', {entries: [entry], primaryGateway: primaryGw, failoverGateway: failoverGw}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> Create');
            if (data && data.status === 'ok') {
                $('#createDynDnsModal').modal('hide');
                if (data.added > 0) {
                    // Update cache and refresh records display
                    var key = entry.zoneId + ':' + entry.recordName + ':' + entry.recordType;
                    dynDnsEntriesCache[key] = true;
                    var cachedRecords = zoneRecordsCache[entry.zoneId];
                    if (cachedRecords) {
                        var filterType = $('.record-type-filter[data-zone-id="' + entry.zoneId + '"]').val() || '';
                        var searchText = $('.record-search[data-zone-id="' + entry.zoneId + '"]').val() || '';
                        renderRecordsGrouped(entry.zoneId, cachedRecords, filterType, searchText);
                    }
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Success', message: 'DynDNS entry created successfully! The record will now be managed automatically.'});
                } else if (data.skipped > 0) {
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_INFO, title: 'Already Exists', message: 'This record is already managed as a DynDNS entry.'});
                }
            } else {
                var errorMsg = 'Failed to create DynDNS entry.';
                if (data && data.message) errorMsg += '<br/><br/>' + data.message;
                if (data && data.errors && data.errors.length > 0) {
                    errorMsg += '<br/><br/><strong>Errors:</strong><ul>';
                    $.each(data.errors, function(i, err) { errorMsg += '<li>' + err + '</li>'; });
                    errorMsg += '</ul>';
                }
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Error', message: errorMsg});
            }
        });
    });

    function resetRecordForm() {
        $('#recordForm')[0].reset();
        $('#recordId').val('');
        $('#recordZoneId').val('');
        $('#recordOldValue').val('');
        $('#recordOldTtl').val('');
        $('#recordType').val('A').trigger('change');
        $('#txtType').val('custom').trigger('change');
        isEditMode = false;
    }

    // TXT Record Auto-Detection Functions
    function detectTxtType(value) {
        if (!value) return 'custom';
        value = value.trim();
        // Strip leading/trailing quotes (TXT records often come quoted from API)
        if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
            value = value.slice(1, -1);
        }
        if (value.toLowerCase().startsWith('v=spf1')) return 'spf';
        if (value.toLowerCase().startsWith('v=dkim1')) return 'dkim';
        if (value.toLowerCase().startsWith('v=dmarc1')) return 'dmarc';
        if (value.toLowerCase().startsWith('google-site-verification=')) return 'google-site';
        if (value.toUpperCase().startsWith('MS=')) return 'ms-site';
        return 'custom';
    }

    function stripQuotes(val) {
        if (!val) return val;
        val = val.trim();
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
            return val.slice(1, -1);
        }
        return val;
    }

    function parseSPF(value) {
        // Reset SPF wizard
        $('#spfIncludeMx').prop('checked', false);
        $('#spfIncludeA').prop('checked', false);
        $('#spfIncludes').val('');
        $('#spfIps').val('');
        $('#spfPolicy').val('~all');

        value = stripQuotes(value);
        var includes = [];
        var ips = [];
        var parts = value.split(/\s+/);

        $.each(parts, function(i, part) {
            part = part.toLowerCase();
            if (part === 'mx') {
                $('#spfIncludeMx').prop('checked', true);
            } else if (part === 'a') {
                $('#spfIncludeA').prop('checked', true);
            } else if (part.startsWith('include:')) {
                includes.push(part.substring(8));
            } else if (part.startsWith('ip4:')) {
                ips.push(part.substring(4));
            } else if (part.startsWith('ip6:')) {
                ips.push(part.substring(4));
            } else if (part === '-all' || part === '~all' || part === '?all' || part === '+all') {
                $('#spfPolicy').val(part);
            }
        });

        $('#spfIncludes').val(includes.join('\n'));
        $('#spfIps').val(ips.join('\n'));
        updateSpfPreview();
    }

    function parseDKIM(value, recordName) {
        // Extract selector from record name (format: selector._domainkey)
        var selector = '';
        if (recordName && recordName.includes('._domainkey')) {
            selector = recordName.split('._domainkey')[0];
        }
        $('#dkimSelector').val(selector);

        value = stripQuotes(value);
        // Extract public key from value
        var keyMatch = value.match(/p=([^;\s]+)/i);
        if (keyMatch) {
            $('#dkimKey').val(keyMatch[1]);
        } else {
            $('#dkimKey').val('');
        }
        updateDkimPreview();
    }

    function parseDMARC(value) {
        // Reset DMARC wizard
        $('#dmarcPolicy').val('none');
        $('#dmarcRua').val('');
        $('#dmarcPct').val('100');

        value = stripQuotes(value);

        // Parse policy
        var policyMatch = value.match(/p=([^;\s]+)/i);
        if (policyMatch) {
            $('#dmarcPolicy').val(policyMatch[1].toLowerCase());
        }

        // Parse rua (report email)
        var ruaMatch = value.match(/rua=mailto:([^;\s]+)/i);
        if (ruaMatch) {
            $('#dmarcRua').val(ruaMatch[1]);
        }

        // Parse pct (percentage)
        var pctMatch = value.match(/pct=(\d+)/i);
        if (pctMatch) {
            $('#dmarcPct').val(pctMatch[1]);
        }
        updateDmarcPreview();
    }

    function populateTxtWizard(value, recordName) {
        var txtType = detectTxtType(value);
        $('#txtType').val(txtType);

        // Trigger the change to show the appropriate wizard
        $('#txtType').trigger('change');

        // Now populate the wizard fields
        if (txtType === 'spf') {
            parseSPF(value);
        } else if (txtType === 'dkim') {
            parseDKIM(value, recordName);
        } else if (txtType === 'dmarc') {
            parseDMARC(value);
        } else {
            // For custom, google-site, ms-site - just put value in the standard input
            $('#recordValue').val(value);
        }
    }

    // Record type change - show/hide relevant fields
    $('#recordType').on('change', function() {
        var type = $(this).val();

        // Hide all special field groups
        $('#mxPriorityGroup, #txtWizardSection, #srvFieldsGroup, #caaFieldsGroup').hide();
        $('#standardValueGroup').show();

        // Update placeholder
        var placeholders = {
            'A': '192.168.1.1',
            'AAAA': '2001:db8::1',
            'CNAME': 'target.example.com',
            'MX': 'mail.example.com',
            'TXT': 'Your text value',
            'NS': 'ns1.example.com',
            'SRV': '',
            'CAA': ''
        };
        $('#recordValue').attr('placeholder', placeholders[type] || '');

        if (type === 'MX') {
            $('#mxPriorityGroup').show();
        } else if (type === 'TXT') {
            $('#txtWizardSection').show();
            $('#txtType').trigger('change');
        } else if (type === 'SRV') {
            $('#standardValueGroup').hide();
            $('#srvFieldsGroup').show();
        } else if (type === 'CAA') {
            $('#standardValueGroup').hide();
            $('#caaFieldsGroup').show();
        }
    });

    // TXT type change
    $('#txtType').on('change', function() {
        var type = $(this).val();
        $('#spfWizard, #dkimWizard, #dmarcWizard').hide();
        $('#standardValueGroup').show();

        if (type === 'spf') {
            $('#standardValueGroup').hide();
            $('#spfWizard').show();
            $('#recordName').val('@');
            updateSpfPreview();
        } else if (type === 'dkim') {
            $('#standardValueGroup').hide();
            $('#dkimWizard').show();
            updateDkimPreview();
        } else if (type === 'dmarc') {
            $('#standardValueGroup').hide();
            $('#dmarcWizard').show();
            $('#recordName').val('_dmarc');
            updateDmarcPreview();
        } else if (type === 'google-site') {
            $('#recordName').val('@');
            $('#recordValue').attr('placeholder', 'google-site-verification=xxx');
        } else if (type === 'ms-site') {
            $('#recordName').val('@');
            $('#recordValue').attr('placeholder', 'MS=ms12345678');
        }
    });

    // SPF preview updates
    $('#spfIncludeMx, #spfIncludeA, #spfIncludes, #spfIps, #spfPolicy').on('change keyup', updateSpfPreview);

    function updateSpfPreview() {
        var parts = ['v=spf1'];
        if ($('#spfIncludeMx').is(':checked')) parts.push('mx');
        if ($('#spfIncludeA').is(':checked')) parts.push('a');

        var includes = $('#spfIncludes').val().trim().split('\n').filter(function(s) { return s.trim(); });
        $.each(includes, function(i, inc) { parts.push('include:' + inc.trim()); });

        var ips = $('#spfIps').val().trim().split('\n').filter(function(s) { return s.trim(); });
        $.each(ips, function(i, ip) {
            if (ip.indexOf(':') > -1) parts.push('ip6:' + ip.trim());
            else parts.push('ip4:' + ip.trim());
        });

        parts.push($('#spfPolicy').val());
        $('#spfPreview').text(parts.join(' '));
    }

    // DKIM preview updates
    $('#dkimSelector, #dkimKey').on('change keyup', updateDkimPreview);

    function updateDkimPreview() {
        var selector = $('#dkimSelector').val() || 'selector';
        var key = $('#dkimKey').val().replace(/\s/g, '') || 'YOUR_KEY_HERE';
        $('#recordName').val(selector + '._domainkey');
        $('#dkimPreview').text('v=DKIM1; k=rsa; p=' + key);
    }

    // DMARC preview updates
    $('#dmarcPolicy, #dmarcRua, #dmarcPct').on('change keyup', updateDmarcPreview);

    function updateDmarcPreview() {
        var parts = ['v=DMARC1', 'p=' + $('#dmarcPolicy').val()];
        var pct = $('#dmarcPct').val();
        if (pct && pct !== '100') parts.push('pct=' + pct);
        var rua = $('#dmarcRua').val();
        if (rua) parts.push('rua=mailto:' + rua);
        $('#dmarcPreview').text(parts.join('; ') + ';');
    }

    // Save record
    $('#saveRecordBtn').on('click', function() {
        var zoneId = $('#recordZone').val();
        var recordType = $('#recordType').val();
        var recordName = $('#recordName').val();
        var ttl = $('#recordTtl').val() || 300;
        var value = '';
        var zoneName = $('#recordZoneDisplay').val();

        // Build value based on record type
        if (recordType === 'TXT') {
            var txtType = $('#txtType').val();
            if (txtType === 'spf') {
                value = $('#spfPreview').text();
            } else if (txtType === 'dkim') {
                value = $('#dkimPreview').text();
            } else if (txtType === 'dmarc') {
                value = $('#dmarcPreview').text();
            } else {
                value = $('#recordValue').val();
            }
        } else if (recordType === 'MX') {
            value = $('#mxPriority').val() + ' ' + $('#recordValue').val();
        } else if (recordType === 'SRV') {
            value = $('#srvPriority').val() + ' ' + $('#srvWeight').val() + ' ' + $('#srvPort').val() + ' ' + $('#srvTarget').val();
        } else if (recordType === 'CAA') {
            value = $('#caaFlags').val() + ' ' + $('#caaTag').val() + ' "' + $('#caaValue').val() + '"';
        } else {
            value = $('#recordValue').val();
        }

        if (!zoneId || !recordName || !value) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, title: '{{ lang._("Warning") }}', message: '{{ lang._("Please fill in all required fields") }}'});
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Saving...") }}');

        // Use updateRecord for edits (handles both update and create via Hetzner API)
        // Use createRecord only for new records
        var apiEndpoint = isEditMode ? '/api/hclouddns/hetzner/updateRecord' : '/api/hclouddns/hetzner/createRecord';
        var postData = {
            account_uuid: currentAccountUuid,
            zone_id: zoneId,
            zone_name: zoneName,
            record_name: recordName,
            record_type: recordType,
            value: value,
            ttl: ttl,
            old_value: $('#recordOldValue').val(),
            old_ttl: $('#recordOldTtl').val()
        };

        ajaxCall(apiEndpoint, postData, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> {{ lang._("Save Record") }}');

            if (data && data.status === 'ok') {
                $('#recordModal').modal('hide');
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_SUCCESS,
                    title: '{{ lang._("Success") }}',
                    message: data.message || '{{ lang._("Record saved successfully") }}'
                });
                // Refresh records for this zone
                loadRecords(zoneId);
            } else {
                var errorDetails = '';
                if (data && data.message) {
                    errorDetails = data.message;
                } else {
                    errorDetails = '{{ lang._("Unknown error") }}';
                }
                var recordInfo = recordName + '.' + zoneName + ' (' + recordType + ')';
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._("Error") }}',
                    message: '{{ lang._("Failed to save record") }}: ' + recordInfo + '<br/><br/><strong>{{ lang._("Details") }}:</strong> ' + errorDetails
                });
            }
        });
    });

    // History functionality
    var currentHistoryItem = null;

    $('#historyBtn').on('click', function() {
        loadHistory();
        $('#historyModal').modal('show');
    });

    function loadHistory() {
        $('#historyList').html('<div class="text-center text-muted" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">{{ lang._("Loading history...") }}</p></div>');

        ajaxCall('/api/hclouddns/history/searchItem', {}, function(data) {
            if (data && data.rows && data.rows.length > 0) {
                var html = '';
                $.each(data.rows, function(i, item) {
                    var actionClass = 'history-action-' + item.action;
                    var actionLabel = item.action.charAt(0).toUpperCase() + item.action.slice(1);
                    var revertedClass = item.reverted === '1' ? 'reverted' : '';
                    var revertedBadge = item.reverted === '1' ? ' <span class="label label-default">{{ lang._("Reverted") }}</span>' : '';

                    html += '<div class="history-item ' + revertedClass + '" data-uuid="' + item.uuid + '">' +
                        '<div class="row">' +
                            '<div class="col-md-2">' +
                                '<span class="history-action ' + actionClass + '">' + actionLabel + '</span>' +
                            '</div>' +
                            '<div class="col-md-4">' +
                                '<span class="history-record">' + item.recordName + '.' + item.zoneName + '</span>' +
                                '<br><span class="record-type-badge record-type-' + item.recordType + '">' + item.recordType + '</span>' +
                                revertedBadge +
                            '</div>' +
                            '<div class="col-md-3">' +
                                '<span class="history-time">' + item.timestampFormatted + '</span>' +
                            '</div>' +
                            '<div class="col-md-3 text-right">' +
                                '<span class="text-muted">' + (item.accountName || '{{ lang._("Unknown account") }}') + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                });
                $('#historyList').html(html);
            } else {
                $('#historyList').html('<div class="text-center text-muted" style="padding: 40px;"><i class="fa fa-history fa-3x"></i><p style="margin-top: 15px;">{{ lang._("No changes recorded yet") }}</p></div>');
            }
        });
    }

    // Click on history item to show details
    $(document).on('click', '.history-item', function() {
        var uuid = $(this).data('uuid');
        ajaxCall('/api/hclouddns/history/getItem/' + uuid, {}, function(data) {
            if (data && data.status === 'ok' && data.change) {
                currentHistoryItem = data.change;
                showHistoryDetail(data.change);
            }
        });
    });

    function showHistoryDetail(item) {
        var actionLabel = item.action.charAt(0).toUpperCase() + item.action.slice(1);
        $('#historyDetailTitle').text(actionLabel + ': ' + item.recordName + '.' + item.zoneName);

        var html = '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Time") }}:</span> ' + item.timestampFormatted + '</div>';
        html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Account") }}:</span> ' + (item.accountName || 'Unknown') + '</div>';
        html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Zone") }}:</span> ' + item.zoneName + '</div>';
        html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Record") }}:</span> ' + item.recordName + ' <span class="record-type-badge record-type-' + item.recordType + '">' + item.recordType + '</span></div>';

        if (item.action === 'create') {
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Value") }}:</span> <span class="history-value-new">' + escapeHtml(item.newValue) + '</span></div>';
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("TTL") }}:</span> ' + item.newTtl + 's</div>';
            html += '<hr><p class="text-warning"><i class="fa fa-exclamation-triangle"></i> {{ lang._("Reverting will DELETE this record.") }}</p>';
        } else if (item.action === 'delete') {
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Deleted Value") }}:</span> <span class="history-value-old">' + escapeHtml(item.oldValue) + '</span></div>';
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("TTL") }}:</span> ' + item.oldTtl + 's</div>';
            html += '<hr><p class="text-success"><i class="fa fa-plus-circle"></i> {{ lang._("Reverting will RECREATE this record with the original value.") }}</p>';
        } else if (item.action === 'update') {
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("Old Value") }}:</span> <span class="history-value-old">' + escapeHtml(item.oldValue) + '</span></div>';
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("New Value") }}:</span> <span class="history-value-new">' + escapeHtml(item.newValue) + '</span></div>';
            html += '<div class="history-detail-row"><span class="history-detail-label">{{ lang._("TTL") }}:</span> ' + item.oldTtl + 's &rarr; ' + item.newTtl + 's</div>';
            html += '<hr><p class="text-info"><i class="fa fa-undo"></i> {{ lang._("Reverting will restore the old value.") }}</p>';
        }

        $('#historyDetailBody').html(html);

        if (item.reverted === '1') {
            $('#revertBtn').prop('disabled', true).html('<i class="fa fa-check"></i> {{ lang._("Already Reverted") }}');
        } else {
            $('#revertBtn').prop('disabled', false).html('<i class="fa fa-undo"></i> {{ lang._("Revert This Change") }}');
        }

        $('#historyDetailModal').modal('show');
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Revert button click
    $('#revertBtn').on('click', function() {
        if (!currentHistoryItem || currentHistoryItem.reverted === '1') return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Reverting...") }}');

        ajaxCall('/api/hclouddns/history/revert/' + currentHistoryItem.uuid, {}, function(data) {
            if (data && data.status === 'ok') {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_SUCCESS,
                    message: data.message || '{{ lang._("Change reverted successfully") }}'
                });
                $('#historyDetailModal').modal('hide');
                loadHistory();
                // Refresh zones to show updated data
                if (currentAccountUuid) {
                    loadZones();
                }
            } else {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._("Error") }}',
                    message: '{{ lang._("Failed to revert change") }}<br/><br/><strong>{{ lang._("Details") }}:</strong> ' + (data.message || '{{ lang._("Unknown error") }}')
                });
                $btn.prop('disabled', false).html('<i class="fa fa-undo"></i> {{ lang._("Revert This Change") }}');
            }
        });
    });
});
</script>
