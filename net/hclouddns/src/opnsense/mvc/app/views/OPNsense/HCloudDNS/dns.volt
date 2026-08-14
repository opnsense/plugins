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
    .wizard-preview { background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 13px; margin-top: 10px; word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap; }
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
    /* Zone Groups */
    .zone-group-section { margin-bottom: 20px; }
    .zone-group-header { padding: 10px 15px; background: #e9ecef; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .zone-group-header:hover { background: #dee2e6; }
    .zone-group-header.collapsed { margin-bottom: 0; border-radius: 4px; }
    .zone-group-title { font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .zone-group-title i { color: #666; }
    .zone-group-count { background: #6c757d; color: #fff; padding: 2px 10px; border-radius: 10px; font-size: 12px; }
    .zone-group-body { display: block; }
    .zone-group-body.collapsed { display: none; }
    .zone-group-selector { width: auto; min-width: 120px; font-size: 12px; padding: 2px 8px; height: 26px; }
    .zone-group-actions { display: flex; align-items: center; gap: 8px; }
    .new-group-input { width: 120px; font-size: 12px; padding: 2px 8px; height: 26px; display: none; }
    .new-group-input.show { display: inline-block; }
    /* Pinned Zones */
    .zone-pin-btn { background: none; border: none; cursor: pointer; font-size: 16px; padding: 2px 6px; opacity: 0.3; transition: opacity 0.2s, color 0.2s; }
    .zone-pin-btn:hover { opacity: 0.7; }
    .zone-pin-btn.pinned { opacity: 1; color: #f0ad4e; }
    .zone-group-section.pinned .zone-group-header { background: #fff8e1; border-color: #f0ad4e; }
    .zone-group-section.pinned .zone-group-title { color: #8a6d3b; }
    /* CAA Wizard */
    #caaWizard { margin-top: 10px; }
    #caaWizard .wizard-preview { margin-top: 15px; }
    /* Health Check Modal */
    #healthCheckModal .modal-dialog { width: 600px; }
    .health-score { text-align: center; padding: 20px; margin-bottom: 15px; }
    .health-score .score-number { font-size: 36px; font-weight: 700; }
    .health-score .score-label { font-size: 14px; color: #666; }
    .health-score-bar { height: 8px; background: #e9ecef; border-radius: 4px; margin-top: 10px; overflow: hidden; }
    .health-score-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
    .health-check-item { padding: 10px 15px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
    .health-check-item:last-child { border-bottom: none; }
    .health-check-icon { font-size: 16px; min-width: 20px; text-align: center; }
    .health-check-icon.pass { color: #28a745; }
    .health-check-icon.warn { color: #ffc107; }
    .health-check-icon.fail { color: #dc3545; }
    .health-check-name { font-weight: 600; min-width: 130px; }
    .health-check-message { color: #555; font-size: 13px; }
    .health-fix-btn { margin-left: auto; }
    /* Propagation Modal */
    #propagationModal .modal-dialog { width: 80%; max-width: 1000px; }
    .propagation-progress { margin-bottom: 15px; }
    .propagation-summary { padding: 10px 15px; background: #f8f9fa; border-radius: 4px; margin-top: 15px; text-align: center; }
    .prop-status-icon { font-size: 14px; }
    .prop-status-icon.ok { color: #28a745; }
    .prop-status-icon.pending { color: #ffc107; }
    .prop-status-icon.fail { color: #dc3545; }
    .prop-record-row { transition: background-color 0.3s ease; }
    .prop-record-row.success { background-color: #d4edda !important; }
    .prop-record-row.warning { background-color: #fff3cd !important; }
    /* Import/Template Modals */
    #importModal .modal-dialog { width: 80%; max-width: 1000px; }
    #importModal .modal-body { padding: 25px 30px; }
    #importModal .import-step h5 { margin-bottom: 15px; }
    #importModal .form-group { margin-bottom: 18px; }
    #importModal #importPreviewTable { margin-top: 10px; }
    #importModal #importLog { padding: 12px; background: #f8f9fa; border: 1px solid #eee; border-radius: 4px; }
    .import-create-zone-row { display: flex; gap: 8px; align-items: center; margin-top: 8px; }
    .import-create-zone-row .form-control { flex: 1; }
    #templateModal .modal-dialog { width: 70%; max-width: 800px; }
    #templateModal .modal-body { padding: 25px 30px; }
    .template-card .template-delete-btn { float: right; color: #999; opacity: 0; transition: opacity 0.2s; }
    .template-card:hover .template-delete-btn { opacity: 1; }
    .template-card .template-delete-btn:hover { color: #dc3545; }
    .template-card.create-custom { border-style: dashed; text-align: center; color: #999; }
    .template-card.create-custom:hover { color: #337ab7; border-color: #337ab7; }
    .custom-record-row { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
    .custom-record-row .form-control { font-size: 12px; padding: 4px 8px; height: 30px; }
    .custom-record-row .btn { height: 30px; padding: 4px 8px; }
    .template-card { border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
    .template-card:hover { border-color: #337ab7; background: #f8f9fa; }
    .template-card.selected { border-color: #337ab7; background: #e8f0fe; }
    .template-card h5 { margin: 0 0 5px 0; }
    .template-card p { margin: 0; color: #666; font-size: 13px; }
    .import-step { display: none; }
    .import-step.active { display: block; }
    .import-record-exists { background: #fff3cd !important; }
    .import-record-new { background: #d4edda !important; }
    .import-record-skip { opacity: 0.5; }
    /* Keyboard Shortcuts */
    .shortcuts-table { width: 100%; }
    .shortcuts-table td { padding: 6px 10px; border-bottom: 1px solid #eee; }
    .shortcuts-table td:first-child { width: 80px; text-align: center; }
    .shortcuts-table kbd { display: inline-block; padding: 3px 8px; font-size: 12px; color: #555; background: #f7f7f7; border: 1px solid #ccc; border-radius: 3px; box-shadow: 0 1px 0 rgba(0,0,0,.2); font-family: monospace; }
    .shortcuts-hint { font-size: 11px; color: #999; margin-left: 10px; cursor: pointer; }
    .shortcuts-hint:hover { color: #337ab7; }
    /* Global Search */
    #globalSearchModal .modal-dialog { width: 80%; max-width: 1100px; }
    #globalSearchModal .modal-body { padding: 20px 30px; }
    .global-search-input { font-size: 16px; padding: 10px 15px; height: auto; }
    .global-search-filters { display: flex; gap: 10px; align-items: center; margin: 10px 0; }
    .global-search-results { max-height: 500px; overflow-y: auto; }
    .global-search-results table { margin: 0; }
    .global-search-info { padding: 8px 12px; background: #f8f9fa; border-radius: 4px; margin-bottom: 10px; font-size: 12px; color: #666; }
    /* Clone Button */
    .clone-record-btn { margin-right: 2px; }
    /* DNSSEC Badge */
    .dnssec-badge { margin-left: 8px; font-size: 13px; cursor: help; }
    .dnssec-badge .fa-shield { margin-right: 2px; }
    .dnssec-signed-delegated { color: #28a745; }
    .dnssec-signed-only { color: #ffc107; }
    .dnssec-not-signed { color: #ccc; }
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
    <button class="btn btn-default" id="importZoneBtn" disabled><i class="fa fa-upload"></i> {{ lang._('Import Zone') }}</button>
    <button class="btn btn-default" id="templatesBtn" disabled><i class="fa fa-clipboard"></i> {{ lang._('Templates') }}</button>
    <button class="btn btn-default" id="historyBtn" disabled><i class="fa fa-history"></i> {{ lang._('Undo / History') }}</button>
    <button class="btn btn-default" id="globalSearchBtn" disabled><i class="fa fa-search"></i> {{ lang._('Global Search') }}</button>
    <span class="shortcuts-hint" id="shortcutsHint" title="{{ lang._('Keyboard Shortcuts') }}"><i class="fa fa-keyboard-o"></i> {{ lang._('Press ? for shortcuts') }}</span>
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

<!-- Zone Search (always visible) -->
<div class="zone-search-container" style="margin-bottom: 15px; display: none;">
    <div class="row">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                <input type="text" class="form-control" id="zoneSearchInput" placeholder="{{ lang._('Filter zones...') }}">
            </div>
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
                                        <textarea class="form-control" id="dkimKey" rows="5" style="font-family: monospace; font-size: 12px;" placeholder="MIGfMA0GCSqGSIb3DQEBAQUAA4..."></textarea>
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
                        <div id="caaWizard" class="wizard-section">
                            <h5><i class="fa fa-shield"></i> {{ lang._('CAA Record Builder') }}</h5>
                            <div class="alert alert-info" style="font-size: 12px; padding: 8px 12px;">
                                <i class="fa fa-info-circle"></i> {{ lang._('CAA records specify which Certificate Authorities (CAs) are allowed to issue SSL/TLS certificates for your domain. This helps prevent unauthorized certificate issuance.') }}
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ lang._('CA Preset') }}</label>
                                        <select class="form-control" id="caaPreset">
                                            <option value="" disabled>── {{ lang._('Free / ACME') }} ──</option>
                                            <option value="letsencrypt.org" selected>Let's Encrypt</option>
                                            <option value="zerossl.com">ZeroSSL</option>
                                            <option value="buypass.com">Buypass</option>
                                            <option value="ssl.com">SSL.com</option>
                                            <option value="" disabled>── {{ lang._('Commercial') }} ──</option>
                                            <option value="digicert.com">DigiCert</option>
                                            <option value="sectigo.com">Sectigo (Comodo)</option>
                                            <option value="globalsign.com">GlobalSign</option>
                                            <option value="entrust.net">Entrust</option>
                                            <option value="godaddy.com">GoDaddy</option>
                                            <option value="trust-provider.com">HARICA</option>
                                            <option value="" disabled>── {{ lang._('Cloud Providers') }} ──</option>
                                            <option value="pki.goog">Google Trust Services</option>
                                            <option value="amazonaws.com">Amazon (AWS ACM)</option>
                                            <option value="comodoca.com">Cloudflare (via Comodo)</option>
                                            <option value="" disabled>──</option>
                                            <option value="custom">{{ lang._('Custom CA...') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>{{ lang._('Flags') }}</label>
                                        <input type="number" class="form-control" id="caaFlags" value="0" min="0" max="255">
                                        <p class="help-text">{{ lang._('0 = non-critical') }}</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ lang._('Tag') }}</label>
                                        <select class="form-control" id="caaTag">
                                            <option value="issue">issue ({{ lang._('Allow CA') }})</option>
                                            <option value="issuewild">issuewild ({{ lang._('Wildcard') }})</option>
                                            <option value="iodef">iodef ({{ lang._('Report URL') }})</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ lang._('CA Domain') }}</label>
                                        <input type="text" class="form-control" id="caaValue" placeholder="letsencrypt.org">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="checkbox">
                                        <label><input type="checkbox" id="caaAddWildcard"> {{ lang._('Also add wildcard record (issuewild) for the same CA') }}</label>
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-preview" id="caaPreview">0 issue "letsencrypt.org"</div>
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
<!-- Import Zone Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-upload"></i> {{ lang._('Import Zone') }}</h4>
            </div>
            <div class="modal-body">
                <!-- Step 1: Upload -->
                <div class="import-step active" id="importStep1">
                    <h5>{{ lang._('Step 1: Paste or upload BIND zonefile') }}</h5>
                    <div class="form-group">
                        <textarea class="form-control" id="importContent" rows="12" style="font-family: monospace; font-size: 12px;" placeholder="{{ lang._('Paste your BIND zonefile content here...') }}"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="btn btn-default btn-file">
                            <i class="fa fa-folder-open"></i> {{ lang._('Upload .zone file') }}
                            <input type="file" id="importFileInput" accept=".zone,.txt,.bind" style="display: none;">
                        </label>
                    </div>
                </div>
                <!-- Step 2: Preview -->
                <div class="import-step" id="importStep2">
                    <h5>{{ lang._('Step 2: Select records to import') }}</h5>
                    <div class="form-group">
                        <label>{{ lang._('Target Zone') }}</label>
                        <select class="form-control" id="importTargetZone"></select>
                        <div id="importCreateZoneRow" class="import-create-zone-row" style="display:none;">
                            <input type="text" class="form-control" id="importNewZoneName" placeholder="{{ lang._('example.com') }}">
                            <button class="btn btn-success btn-sm" id="importCreateZoneBtn" style="white-space:nowrap;"><i class="fa fa-plus"></i> {{ lang._('Create') }}</button>
                            <button class="btn btn-default btn-sm" id="importCancelCreateZone"><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <button class="btn btn-xs btn-default" id="importSelectAll">{{ lang._('Select All') }}</button>
                        <button class="btn btn-xs btn-default" id="importDeselectAll">{{ lang._('Deselect All') }}</button>
                    </div>
                    <table class="table table-condensed table-hover" id="importPreviewTable">
                        <thead><tr><th style="width:30px;"></th><th>{{ lang._('Name') }}</th><th>{{ lang._('Type') }}</th><th>{{ lang._('Value') }}</th><th>{{ lang._('TTL') }}</th><th>{{ lang._('Status') }}</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <!-- Step 3: Progress -->
                <div class="import-step" id="importStep3">
                    <h5>{{ lang._('Step 3: Importing records...') }}</h5>
                    <div class="progress"><div class="progress-bar progress-bar-striped active" id="importProgressBar" style="width: 0%"></div></div>
                    <div id="importLog" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="importParseBtn"><i class="fa fa-search"></i> {{ lang._('Parse') }}</button>
                <button type="button" class="btn btn-success" id="importApplyBtn" style="display:none;"><i class="fa fa-check"></i> {{ lang._('Import Selected') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Templates Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-clipboard"></i> {{ lang._('Record Templates') }}</h4>
            </div>
            <div class="modal-body">
                <!-- Step 1: Select template -->
                <div class="template-step active" id="templateStep1">
                    <div class="form-group">
                        <label>{{ lang._('Target Zone') }}</label>
                        <select class="form-control" id="templateTargetZone"></select>
                    </div>
                    <div id="templateList"></div>
                </div>
                <!-- Step Custom: Create custom template -->
                <div class="template-step" id="templateStepCustom" style="display:none;">
                    <h5><i class="fa fa-plus-circle"></i> {{ lang._('Create Custom Template') }}</h5>
                    <div class="form-group">
                        <label>{{ lang._('Template Name') }}</label>
                        <input type="text" class="form-control" id="customTplName" placeholder="{{ lang._('My Template') }}">
                    </div>
                    <div class="form-group">
                        <label>{{ lang._('Description') }}</label>
                        <input type="text" class="form-control" id="customTplDesc" placeholder="{{ lang._('Short description of what this template creates') }}">
                    </div>
                    <div class="form-group">
                        <label>{{ lang._('Records') }}</label>
                        <div id="customTplRecords"></div>
                        <button class="btn btn-xs btn-default" id="customTplAddRecord" style="margin-top: 6px;"><i class="fa fa-plus"></i> {{ lang._('Add Record') }}</button>
                    </div>
                </div>
                <!-- Step 2: Parameters -->
                <div class="template-step" id="templateStep2" style="display:none;">
                    <h5 id="templateParamsTitle"></h5>
                    <div id="templateParams"></div>
                    <h5 style="margin-top:20px;">{{ lang._('Preview') }}</h5>
                    <table class="table table-condensed" id="templatePreviewTable">
                        <thead><tr><th></th><th>{{ lang._('Name') }}</th><th>{{ lang._('Type') }}</th><th>{{ lang._('Value') }}</th><th>{{ lang._('TTL') }}</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <!-- Step 3: Progress -->
                <div class="template-step" id="templateStep3" style="display:none;">
                    <h5>{{ lang._('Applying template...') }}</h5>
                    <div class="progress"><div class="progress-bar progress-bar-striped active" id="templateProgressBar" style="width: 0%"></div></div>
                    <div id="templateLog" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-default" id="templateBackBtn" style="display:none;"><i class="fa fa-arrow-left"></i> {{ lang._('Back') }}</button>
                <button type="button" class="btn btn-primary" id="customTplSaveBtn" style="display:none;"><i class="fa fa-save"></i> {{ lang._('Save Template') }}</button>
                <button type="button" class="btn btn-success" id="templateApplyBtn" style="display:none;"><i class="fa fa-check"></i> {{ lang._('Apply Template') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Health Check Modal -->
<div class="modal fade" id="healthCheckModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-heartbeat"></i> {{ lang._('DNS Health Check') }} - <span id="healthCheckZone"></span></h4>
            </div>
            <div class="modal-body" id="healthCheckBody">
                <div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">{{ lang._('Running health checks...') }}</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Propagation Monitor Modal -->
<div class="modal fade" id="propagationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-globe"></i> {{ lang._('DNS Propagation Check') }} - <span id="propagationZone"></span></h4>
            </div>
            <div class="modal-body" id="propagationBody">
                <div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">{{ lang._('Checking propagation...') }}</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="propagationRefreshBtn"><i class="fa fa-refresh"></i> {{ lang._('Check Again') }}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Global Search Modal -->
<div class="modal fade" id="globalSearchModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-search"></i> {{ lang._('Global Record Search') }}</h4>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control global-search-input" id="globalSearchInput" placeholder="{{ lang._('Search records across all zones...') }}" autofocus>
                <div class="global-search-filters">
                    <select class="form-control input-sm" id="globalSearchType" style="width: 150px;">
                        <option value="">{{ lang._('All Types') }}</option>
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="MX">MX</option>
                        <option value="TXT">TXT</option>
                        <option value="NS">NS</option>
                        <option value="SRV">SRV</option>
                        <option value="CAA">CAA</option>
                    </select>
                    <span id="globalSearchCount" class="text-muted" style="font-size: 12px;"></span>
                    <button class="btn btn-xs btn-default" id="globalSearchRefreshAll" title="{{ lang._('Load all zone records for complete search') }}"><i class="fa fa-refresh"></i> {{ lang._('Refresh All') }}</button>
                </div>
                <div id="globalSearchInfo" class="global-search-info" style="display:none;"></div>
                <div class="global-search-results" id="globalSearchResults">
                    <div class="text-center text-muted" style="padding: 40px;">
                        <i class="fa fa-search fa-3x"></i>
                        <p style="margin-top: 15px;">{{ lang._('Start typing to search across all zones') }}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="modal fade" id="shortcutsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="width: 400px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-keyboard-o"></i> {{ lang._('Keyboard Shortcuts') }}</h4>
            </div>
            <div class="modal-body" style="padding: 15px 25px;">
                <table class="shortcuts-table">
                    <tr><td><kbd>/</kbd></td><td>{{ lang._('Focus zone search') }}</td></tr>
                    <tr><td><kbd>s</kbd></td><td>{{ lang._('Open Global Search') }}</td></tr>
                    <tr><td><kbd>r</kbd></td><td>{{ lang._('Refresh zones') }}</td></tr>
                    <tr><td><kbd>i</kbd></td><td>{{ lang._('Open Import') }}</td></tr>
                    <tr><td><kbd>t</kbd></td><td>{{ lang._('Open Templates') }}</td></tr>
                    <tr><td><kbd>h</kbd></td><td>{{ lang._('Open History') }}</td></tr>
                    <tr><td><kbd>?</kbd></td><td>{{ lang._('Show this help') }}</td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
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
    updateServiceControlUI('hclouddns');

    var currentAccountUuid = '';
    var zonesData = {};
    var isEditMode = false; // Track whether we're editing an existing record
    var defaultDynDnsTtl = '60'; // Default TTL for DynDNS entries (loaded from settings)
    var zoneGroups = []; // Available group names
    var zoneAssignments = {}; // zone_id -> group_name mapping
    var collapsedGroups = {}; // Track which groups are collapsed

    var pinnedZones = {}; // zone_id -> true mapping

    // Load collapsed state from localStorage
    try {
        var saved = localStorage.getItem('hclouddns_collapsedGroups');
        if (saved) collapsedGroups = JSON.parse(saved);
    } catch(e) {}

    // Load pinned zones from localStorage
    try {
        var savedPins = localStorage.getItem('hclouddns_pinnedZones');
        if (savedPins) pinnedZones = JSON.parse(savedPins);
    } catch(e) {}

    function saveCollapsedState() {
        try {
            localStorage.setItem('hclouddns_collapsedGroups', JSON.stringify(collapsedGroups));
        } catch(e) {}
    }

    function savePinnedZones() {
        try {
            localStorage.setItem('hclouddns_pinnedZones', JSON.stringify(pinnedZones));
        } catch(e) {}
    }

    // Load zone groups from settings
    function loadZoneGroups(callback) {
        ajaxCall('/api/hclouddns/settings/getZoneGroups', {}, function(data) {
            if (data && data.status === 'ok') {
                zoneGroups = data.groups || [];
                zoneAssignments = data.assignments || {};
            }
            if (callback) callback();
        });
    }

    // Load default TTL from settings
    ajaxCall('/api/hclouddns/settings/get', {}, function(data) {
        if (data && data.hclouddns && data.hclouddns.general && data.hclouddns.general.defaultTtl) {
            // Find the selected TTL option (API returns object with selected: 1/0 for each option)
            var ttlOptions = data.hclouddns.general.defaultTtl;
            for (var key in ttlOptions) {
                if (ttlOptions[key].selected == 1) {
                    // Remove underscore prefix (e.g. "_60" -> "60")
                    defaultDynDnsTtl = key.charAt(0) === '_' ? key.substring(1) : key;
                    break;
                }
            }
        }
    });

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
                $('#refreshZonesBtn, #historyBtn, #importZoneBtn, #templatesBtn, #globalSearchBtn').prop('disabled', false);
                loadZones();
            }
        }
    });

    // Account selection change
    $('#dnsAccountSelect').on('change', function() {
        currentAccountUuid = $(this).val();
        if (currentAccountUuid) {
            $('#refreshZonesBtn, #historyBtn, #importZoneBtn, #templatesBtn, #globalSearchBtn').prop('disabled', false);
            loadZones();
        } else {
            $('#refreshZonesBtn, #historyBtn, #importZoneBtn, #templatesBtn, #globalSearchBtn').prop('disabled', true);
            $('.zone-search-container').hide();
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

    function buildGroupSelector(zoneId, currentGroup) {
        var html = '<select class="form-control zone-group-selector" data-zone-id="' + zoneId + '">';
        html += '<option value="">{{ lang._("No Group") }}</option>';
        var sortedGroups = zoneGroups.slice().sort(function(a, b) { return a.localeCompare(b); });
        $.each(sortedGroups, function(i, g) {
            html += '<option value="' + g + '"' + (currentGroup === g ? ' selected' : '') + '>' + g + '</option>';
        });
        html += '<option value="__new__">+ {{ lang._("New Group...") }}</option>';
        html += '</select>';
        html += '<input type="text" class="form-control new-group-input" data-zone-id="' + zoneId + '" placeholder="{{ lang._("Group name") }}">';
        return html;
    }

    function renderZonePanel(zone) {
        var currentGroup = zoneAssignments[zone.id] || '';
        var isPinned = pinnedZones[zone.id] === true;
        var pinClass = isPinned ? 'pinned' : '';
        return '<div class="zone-panel" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '">' +
            '<div class="zone-header">' +
                '<div><span class="zone-name">' + zone.name + '</span> <span class="zone-meta zone-record-count">(<i class="fa fa-spinner fa-spin"></i>)</span></div>' +
                '<div class="zone-header-actions">' +
                    '<button class="zone-pin-btn ' + pinClass + '" data-zone-id="' + zone.id + '" title="' + (isPinned ? '{{ lang._("Unpin zone") }}' : '{{ lang._("Pin zone") }}') + '"><i class="fa fa-star"></i></button>' +
                    '<div class="zone-group-actions" onclick="event.stopPropagation();">' + buildGroupSelector(zone.id, currentGroup) + '</div>' +
                    '<button class="btn btn-xs btn-default zone-export-btn" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '" title="{{ lang._("Export Zone") }}"><i class="fa fa-download"></i></button> ' +
                    '<button class="btn btn-xs btn-default zone-health-btn" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '" title="{{ lang._("DNS Health Check") }}"><i class="fa fa-heartbeat"></i></button> ' +
                    '<button class="btn btn-xs btn-default zone-propagation-btn" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '" title="{{ lang._("Propagation Check") }}"><i class="fa fa-globe"></i></button> ' +
                    '<button class="btn btn-xs btn-success add-zone-record-btn" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '" title="{{ lang._("Add Record") }}"><i class="fa fa-plus"></i></button> ' +
                    '<i class="fa fa-chevron-right zone-toggle"></i>' +
                '</div>' +
            '</div>' +
            '<div class="zone-records" id="zone-records-' + zone.id + '"><div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div></div>' +
        '</div>';
    }

    function renderZonesGrouped(zones) {
        // Sort zones alphabetically
        zones.sort(function(a, b) { return a.name.localeCompare(b.name); });

        // Separate pinned zones
        var pinned = [];
        var unpinned = [];
        $.each(zones, function(i, zone) {
            if (pinnedZones[zone.id]) {
                pinned.push(zone);
            } else {
                unpinned.push(zone);
            }
        });

        var html = '';

        // Render pinned zones first as a special group
        if (pinned.length > 0) {
            var isPinnedCollapsed = collapsedGroups['__pinned__'] === true;
            html += '<div class="zone-group-section pinned" data-group="__pinned__">' +
                '<div class="zone-group-header' + (isPinnedCollapsed ? ' collapsed' : '') + '">' +
                    '<div class="zone-group-title"><i class="fa fa-star" style="color: #f0ad4e;"></i> {{ lang._("Favorites") }}</div>' +
                    '<span class="zone-group-count">' + pinned.length + ' {{ lang._("zones") }}</span>' +
                '</div>' +
                '<div class="zone-group-body' + (isPinnedCollapsed ? ' collapsed' : '') + '">';
            $.each(pinned, function(j, zone) {
                html += renderZonePanel(zone);
            });
            html += '</div></div>';
        }

        // Group remaining zones by their assigned group
        var grouped = {};
        var ungrouped = [];
        $.each(unpinned, function(i, zone) {
            var group = zoneAssignments[zone.id];
            if (group) {
                if (!grouped[group]) grouped[group] = [];
                grouped[group].push(zone);
            } else {
                ungrouped.push(zone);
            }
        });

        // Sort groups alphabetically
        var sortedGroups = zoneGroups.slice().sort(function(a, b) { return a.localeCompare(b); });

        // Render grouped zones
        $.each(sortedGroups, function(i, groupName) {
            if (grouped[groupName] && grouped[groupName].length > 0) {
                var isCollapsed = collapsedGroups[groupName] === true;
                var folderIcon = isCollapsed ? 'fa-folder' : 'fa-folder-open';
                html += '<div class="zone-group-section" data-group="' + groupName + '">' +
                    '<div class="zone-group-header' + (isCollapsed ? ' collapsed' : '') + '">' +
                        '<div class="zone-group-title"><i class="fa ' + folderIcon + '"></i> ' + groupName + '</div>' +
                        '<span class="zone-group-count">' + grouped[groupName].length + ' {{ lang._("zones") }}</span>' +
                    '</div>' +
                    '<div class="zone-group-body' + (isCollapsed ? ' collapsed' : '') + '">';
                $.each(grouped[groupName], function(j, zone) {
                    html += renderZonePanel(zone);
                });
                html += '</div></div>';
            }
        });

        // Render ungrouped zones
        if (ungrouped.length > 0) {
            if (zoneGroups.length > 0 || pinned.length > 0) {
                var isUngroupedCollapsed = collapsedGroups['__ungrouped__'] === true;
                var ungroupedIcon = isUngroupedCollapsed ? 'fa-folder' : 'fa-folder-o';
                html += '<div class="zone-group-section" data-group="">' +
                    '<div class="zone-group-header' + (isUngroupedCollapsed ? ' collapsed' : '') + '">' +
                        '<div class="zone-group-title"><i class="fa ' + ungroupedIcon + '"></i> {{ lang._("Ungrouped") }}</div>' +
                        '<span class="zone-group-count">' + ungrouped.length + ' {{ lang._("zones") }}</span>' +
                    '</div>' +
                    '<div class="zone-group-body' + (isUngroupedCollapsed ? ' collapsed' : '') + '">';
            }
            $.each(ungrouped, function(j, zone) {
                html += renderZonePanel(zone);
            });
            if (zoneGroups.length > 0 || pinned.length > 0) {
                html += '</div></div>';
            }
        }

        return html;
    }

    function loadZones() {
        $('#zonesContainer').html('<div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">{{ lang._("Loading zones...") }}</p></div>');

        // Load zone groups, then DynDNS entries, then zones
        loadZoneGroups(function() {
            loadDynDnsEntries(function() {
                ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: currentAccountUuid}, function(data) {
                if (data && data.status === 'ok' && data.zones) {
                    zonesData = {};
                    $.each(data.zones, function(i, zone) {
                        zonesData[zone.id] = zone;
                    });
                    var html = renderZonesGrouped(data.zones);
                    $('#zonesContainer').html(html || '<div class="alert alert-warning">{{ lang._("No zones found for this account.") }}</div>');

                    // Show zone search if there are zones
                    if (data.zones.length > 0) {
                        $('.zone-search-container').show();
                    } else {
                        $('.zone-search-container').hide();
                    }

                    // Load record counts for all zones
                    $.each(data.zones, function(i, zone) {
                        loadRecordCount(zone.id);
                    });
                } else {
                    $('#zonesContainer').html('<div class="alert alert-danger">{{ lang._("Failed to load zones:") }} ' + (data.message || 'Unknown error') + '</div>');
                }
                });
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
            // Load DNSSEC status lazily
            var zoneName = $panel.data('zone-name');
            if (zoneName) loadDnssecStatus(zoneId, zoneName);
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

            // Sort records alphabetically by name
            typeRecords.sort(function(a, b) { return a.name.localeCompare(b.name); });

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
                        '<thead><tr><th style="width: 200px;">{{ lang._("Name") }}</th><th>{{ lang._("Value") }}</th><th style="width: 70px;">{{ lang._("TTL") }}</th><th style="width: 130px;">{{ lang._("Actions") }}</th></tr></thead>' +
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
                    '<button class="btn btn-xs btn-info clone-record-btn" title="{{ lang._("Clone to Zone") }}"><i class="fa fa-copy"></i></button> ' +
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
                        '<thead><tr><th style="width: 200px;">{{ lang._("Name") }}</th><th>{{ lang._("Value") }}</th><th style="width: 70px;">{{ lang._("TTL") }}</th><th style="width: 130px;">{{ lang._("Actions") }}</th></tr></thead>' +
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
                    '<button class="btn btn-xs btn-info clone-record-btn" title="{{ lang._("Clone to Zone") }}"><i class="fa fa-copy"></i></button> ' +
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

    // Zone search/filter
    $('#zoneSearchInput').on('keyup', function() {
        var searchText = $(this).val().toLowerCase();
        $('.zone-panel').each(function() {
            var zoneName = $(this).data('zone-name').toLowerCase();
            if (searchText === '' || zoneName.indexOf(searchText) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // Hide empty group sections
        $('.zone-group-section').each(function() {
            var visibleZones = $(this).find('.zone-panel:visible').length;
            if (visibleZones === 0) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    });

    // Zone group header collapse/expand
    $(document).on('click', '.zone-group-header', function() {
        var $section = $(this).closest('.zone-group-section');
        var $body = $section.find('.zone-group-body');
        var $icon = $(this).find('.zone-group-title i');
        var groupName = $section.data('group') || '__ungrouped__';
        if ($body.hasClass('collapsed')) {
            $body.removeClass('collapsed');
            $(this).removeClass('collapsed');
            $icon.removeClass('fa-folder').addClass('fa-folder-open');
            collapsedGroups[groupName] = false;
        } else {
            $body.addClass('collapsed');
            $(this).addClass('collapsed');
            $icon.removeClass('fa-folder-open').addClass('fa-folder');
            collapsedGroups[groupName] = true;
        }
        saveCollapsedState();
    });

    // Zone group selector change
    $(document).on('change', '.zone-group-selector', function(e) {
        e.stopPropagation();
        var $select = $(this);
        var zoneId = $select.data('zone-id');
        var groupName = $select.val();

        if (groupName === '__new__') {
            // Show new group input
            $select.hide();
            $select.siblings('.new-group-input').addClass('show').focus();
            return;
        }

        // Save the group assignment
        ajaxCall('/api/hclouddns/settings/setZoneGroup', {zone_id: zoneId, group_name: groupName}, function(data) {
            if (data && data.status === 'ok') {
                zoneGroups = data.groups || [];
                zoneAssignments = data.assignments || {};
                // Reload zones to re-render with new grouping
                loadZones();
            }
        }, 'POST');
    });

    // New group input handler
    $(document).on('keypress', '.new-group-input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            var $input = $(this);
            var zoneId = $input.data('zone-id');
            var groupName = $input.val().trim();

            if (groupName) {
                ajaxCall('/api/hclouddns/settings/setZoneGroup', {zone_id: zoneId, group_name: groupName}, function(data) {
                    if (data && data.status === 'ok') {
                        zoneGroups = data.groups || [];
                        zoneAssignments = data.assignments || {};
                        loadZones();
                    }
                }, 'POST');
            } else {
                // Cancel - show select again
                $input.removeClass('show').val('');
                $input.siblings('.zone-group-selector').show().val('');
            }
        } else if (e.which === 27) { // Escape key
            var $input = $(this);
            $input.removeClass('show').val('');
            $input.siblings('.zone-group-selector').show().val('');
        }
    });

    // Cancel new group input on blur
    $(document).on('blur', '.new-group-input', function() {
        var $input = $(this);
        setTimeout(function() {
            if ($input.hasClass('show') && !$input.val().trim()) {
                $input.removeClass('show').val('');
                $input.siblings('.zone-group-selector').show().val('');
            }
        }, 200);
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

    // Edit record - fetch fresh data from API
    $(document).on('click', '.edit-record-btn', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var recordId = $row.data('record-id');
        var zoneId = $row.data('zone-id');
        var $zonePanel = $row.closest('.zone-panel');
        var zoneName = $zonePanel.data('zone-name');

        // Show loading state
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        // Fetch fresh record data from API
        ajaxCall('/api/hclouddns/hetzner/listRecordsForAccount', {
            account_uuid: currentAccountUuid,
            zone_id: zoneId,
            all_types: '1'
        }, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-pencil"></i>');

            if (!data || data.status !== 'ok' || !data.records) {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: '{{ lang._("Failed to load record data.") }}'});
                return;
            }

            // Find record by ID
            var record = null;
            for (var i = 0; i < data.records.length; i++) {
                if (data.records[i].id == recordId) {
                    record = data.records[i];
                    break;
                }
            }

            if (!record) {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Record not found. It may have been deleted.") }}'});
                loadRecords(zoneId);
                return;
            }

            var type = record.type;
            var name = record.name;
            var value = record.value;
            var ttl = record.ttl || 300;

            isEditMode = true;
            $('#recordModalTitle').text('{{ lang._("Edit DNS Record") }} - ' + zoneName);
            $('#recordZoneId').val(zoneId);
            $('#recordZone').val(zoneId);
            $('#recordZoneDisplay').val(zoneName);
            $('#deleteRecordBtn').show();

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
                var mxParts = value.match(/^(\d+)\s+(.+)$/);
                if (mxParts) {
                    $('#mxPriority').val(mxParts[1]);
                    $('#recordValue').val(mxParts[2]);
                } else {
                    $('#recordValue').val(value);
                }
            } else if (type === 'SRV') {
                var srvParts = value.match(/^(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/);
                if (srvParts) {
                    $('#srvPriority').val(srvParts[1]);
                    $('#srvWeight').val(srvParts[2]);
                    $('#srvPort').val(srvParts[3]);
                    $('#srvTarget').val(srvParts[4]);
                }
            } else if (type === 'CAA') {
                var caaParts = value.match(/^(\d+)\s+(\w+)\s+"?([^"]+)"?$/);
                if (caaParts) {
                    $('#caaFlags').val(caaParts[1]);
                    $('#caaTag').val(caaParts[2]);
                    $('#caaValue').val(caaParts[3]);
                    // Try to match preset
                    var knownPresets = ['letsencrypt.org', 'digicert.com', 'sectigo.com', 'pki.goog', 'amazonaws.com'];
                    if (knownPresets.indexOf(caaParts[3]) !== -1) {
                        $('#caaPreset').val(caaParts[3]);
                    } else {
                        $('#caaPreset').val('custom');
                    }
                    $('#caaAddWildcard').prop('checked', false);
                    updateCaaPreview();
                }
            } else {
                $('#recordValue').val(value);
            }

            $('#recordModal').modal('show');
        });
    });

    // Delete record button in table - fetch fresh data from API
    $(document).on('click', '.delete-record-btn', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var recordId = $row.data('record-id');
        var zoneId = $row.data('zone-id');
        var $zonePanel = $row.closest('.zone-panel');
        var zoneName = $zonePanel.data('zone-name');

        // Show loading state
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        // Fetch fresh record data from API
        ajaxCall('/api/hclouddns/hetzner/listRecordsForAccount', {
            account_uuid: currentAccountUuid,
            zone_id: zoneId,
            all_types: '1'
        }, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');

            if (!data || data.status !== 'ok' || !data.records) {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: '{{ lang._("Failed to load record data.") }}'});
                return;
            }

            // Find record by ID
            var record = null;
            for (var i = 0; i < data.records.length; i++) {
                if (data.records[i].id == recordId) {
                    record = data.records[i];
                    break;
                }
            }

            if (!record) {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Record not found. It may have been deleted.") }}'});
                loadRecords(zoneId);
                return;
            }

            var name = record.name;
            var type = record.type;
            var value = record.value;
            var ttl = record.ttl || 300;

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
        }); // close ajaxCall
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

        // Populate modal fields - use default DynDNS TTL from settings
        $('#dynDnsAccountUuid').val(currentAccountUuid);
        $('#dynDnsZoneId').val(zoneId);
        $('#dynDnsZoneName').val(zoneName);
        $('#dynDnsRecordName').val(recordName);
        $('#dynDnsRecordType').val(recordType);
        $('#dynDnsTtl').val(defaultDynDnsTtl);
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

    // CAA preview updates
    $('#caaPreset, #caaFlags, #caaTag, #caaValue, #caaAddWildcard').on('change keyup', updateCaaPreview);

    $('#caaPreset').on('change', function() {
        var preset = $(this).val();
        if (preset !== 'custom') {
            $('#caaValue').val(preset);
        } else {
            $('#caaValue').val('');
        }
        updateCaaPreview();
    });

    function updateCaaPreview() {
        var flags = $('#caaFlags').val() || '0';
        var tag = $('#caaTag').val();
        var value = $('#caaValue').val() || '';
        var preview = flags + ' ' + tag + ' "' + value + '"';
        if ($('#caaAddWildcard').is(':checked') && tag === 'issue' && value) {
            preview += '\n' + flags + ' issuewild "' + value + '"';
        }
        $('#caaPreview').text(preview);
    }

    // Pin zone toggle
    $(document).on('click', '.zone-pin-btn', function(e) {
        e.stopPropagation();
        var zoneId = $(this).data('zone-id');
        if (pinnedZones[zoneId]) {
            delete pinnedZones[zoneId];
        } else {
            pinnedZones[zoneId] = true;
        }
        savePinnedZones();
        // Re-render zones from cache
        var allZones = [];
        for (var id in zonesData) {
            allZones.push(zonesData[id]);
        }
        var html = renderZonesGrouped(allZones);
        $('#zonesContainer').html(html || '<div class="alert alert-warning">{{ lang._("No zones found for this account.") }}</div>');
        // Re-load record counts
        $.each(allZones, function(i, zone) {
            loadRecordCount(zone.id);
        });
    });

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
            // If wildcard checkbox is checked, save the issuewild record after the main save
            if ($('#caaAddWildcard').is(':checked') && $('#caaTag').val() === 'issue' && $('#caaValue').val() && !isEditMode) {
                var wildcardValue = $('#caaFlags').val() + ' issuewild "' + $('#caaValue').val() + '"';
                var wildcardZoneId = zoneId;
                var wildcardZoneName = zoneName;
                var wildcardName = recordName;
                // Schedule wildcard creation after main save completes
                setTimeout(function() {
                    ajaxCall('/api/hclouddns/hetzner/createRecord', {
                        account_uuid: currentAccountUuid,
                        zone_id: wildcardZoneId,
                        zone_name: wildcardZoneName,
                        record_name: wildcardName,
                        record_type: 'CAA',
                        value: wildcardValue,
                        ttl: ttl
                    }, function(data) {
                        if (data && data.status === 'ok') {
                            loadRecords(wildcardZoneId);
                        }
                    });
                }, 500);
            }
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

    // Health Check
    $(document).on('click', '.zone-health-btn', function(e) {
        e.stopPropagation();
        var zoneId = $(this).data('zone-id');
        var zoneName = $(this).data('zone-name');
        $('#healthCheckZone').text(zoneName);
        $('#healthCheckBody').html('<div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">{{ lang._("Running health checks...") }}</p></div>');
        $('#healthCheckModal').modal('show');

        ajaxCall('/api/hclouddns/hetzner/dnsHealthCheck', {
            account_uuid: currentAccountUuid,
            zone_id: zoneId,
            zone_name: zoneName
        }, function(data) {
            if (data && data.status === 'ok') {
                var score = data.score || 0;
                var maxScore = data.maxScore || 7;
                var pct = Math.round((score / maxScore) * 100);
                var barColor = pct >= 80 ? '#28a745' : (pct >= 50 ? '#ffc107' : '#dc3545');
                var scoreColor = pct >= 80 ? '#28a745' : (pct >= 50 ? '#856404' : '#dc3545');

                var html = '<div class="health-score">' +
                    '<div class="score-number" style="color:' + scoreColor + '">' + score + '/' + maxScore + '</div>' +
                    '<div class="score-label">{{ lang._("Checks passed") }}</div>' +
                    '<div class="health-score-bar"><div class="health-score-fill" style="width:' + pct + '%;background:' + barColor + '"></div></div>' +
                '</div>';

                html += '<div class="health-check-list">';
                $.each(data.checks, function(i, check) {
                    var icon = check.status === 'pass' ? 'fa-check-circle' : (check.status === 'warn' ? 'fa-exclamation-triangle' : 'fa-times-circle');
                    var fixBtn = '';
                    if (check.status === 'warn') {
                        if (check.name === 'SPF Record') fixBtn = '<button class="btn btn-xs btn-info health-fix-btn" data-zone-id="' + zoneId + '" data-zone-name="' + zoneName + '" data-fix="spf">{{ lang._("Add SPF") }}</button>';
                        else if (check.name === 'DMARC Record') fixBtn = '<button class="btn btn-xs btn-info health-fix-btn" data-zone-id="' + zoneId + '" data-zone-name="' + zoneName + '" data-fix="dmarc">{{ lang._("Add DMARC") }}</button>';
                        else if (check.name === 'CAA Record') fixBtn = '<button class="btn btn-xs btn-info health-fix-btn" data-zone-id="' + zoneId + '" data-zone-name="' + zoneName + '" data-fix="caa">{{ lang._("Add CAA") }}</button>';
                    }
                    html += '<div class="health-check-item">' +
                        '<span class="health-check-icon ' + check.status + '"><i class="fa ' + icon + '"></i></span>' +
                        '<span class="health-check-name">' + check.name + '</span>' +
                        '<span class="health-check-message">' + escapeHtml(check.message) + '</span>' +
                        fixBtn +
                    '</div>';
                });
                html += '</div>';
                $('#healthCheckBody').html(html);
            } else {
                $('#healthCheckBody').html('<div class="alert alert-danger">' + escapeHtml(data.message || '{{ lang._("Health check failed") }}') + '</div>');
            }
        });
    });

    // Health Check Fix buttons
    $(document).on('click', '.health-fix-btn', function() {
        var zoneId = $(this).data('zone-id');
        var zoneName = $(this).data('zone-name');
        var fix = $(this).data('fix');
        $('#healthCheckModal').modal('hide');
        resetRecordForm();
        isEditMode = false;
        $('#recordZone').val(zoneId);
        $('#recordZoneDisplay').val(zoneName);
        $('#recordModalTitle').text('{{ lang._("Add DNS Record") }} - ' + zoneName);
        $('#deleteRecordBtn').hide();

        if (fix === 'spf') {
            $('#recordType').val('TXT').trigger('change');
            $('#txtType').val('spf').trigger('change');
        } else if (fix === 'dmarc') {
            $('#recordType').val('TXT').trigger('change');
            $('#txtType').val('dmarc').trigger('change');
        } else if (fix === 'caa') {
            $('#recordType').val('CAA').trigger('change');
            $('#recordName').val('@');
        }
        $('#recordModal').modal('show');
    });

    // Propagation Monitor
    var propagationZoneId = '';
    var propagationZoneName = '';

    $(document).on('click', '.zone-propagation-btn', function(e) {
        e.stopPropagation();
        propagationZoneId = $(this).data('zone-id');
        propagationZoneName = $(this).data('zone-name');
        $('#propagationZone').text(propagationZoneName);
        runPropagationCheck();
        $('#propagationModal').modal('show');
    });

    $('#propagationRefreshBtn').on('click', function() {
        runPropagationCheck();
    });

    function runPropagationCheck() {
        $('#propagationBody').html(
            '<div class="propagation-progress"><div class="progress"><div class="progress-bar progress-bar-striped active" style="width: 100%">{{ lang._("Checking...") }}</div></div></div>' +
            '<div class="text-center text-muted" style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>'
        );

        ajaxCall('/api/hclouddns/hetzner/zonePropagationCheck', {
            account_uuid: currentAccountUuid,
            zone_id: propagationZoneId
        }, function(data) {
            if (data && data.status === 'ok') {
                var total = data.total || 0;
                var propagated = data.propagated || 0;
                var pct = total > 0 ? Math.round((propagated / total) * 100) : 100;
                var barClass = pct >= 90 ? 'progress-bar-success' : (pct >= 50 ? 'progress-bar-warning' : 'progress-bar-danger');

                var html = '<div class="propagation-progress"><div class="progress"><div class="progress-bar ' + barClass + '" style="width:' + pct + '%">' + pct + '%</div></div></div>';
                html += '<table class="table table-condensed table-hover">';
                html += '<thead><tr><th>{{ lang._("Record") }}</th><th>{{ lang._("Type") }}</th><th>{{ lang._("Expected") }}</th>';

                // NS column headers
                var nsNames = {'213.133.100.98': 'hydrogen', '88.198.229.192': 'oxygen', '193.47.99.3': 'helium'};
                $.each(nsNames, function(ip, name) {
                    html += '<th style="text-align:center;">' + name + '</th>';
                });
                html += '<th style="text-align:center;">{{ lang._("Status") }}</th></tr></thead><tbody>';

                $.each(data.records, function(i, rec) {
                    var rowClass = rec.propagated ? 'success' : 'warning';
                    html += '<tr class="prop-record-row ' + rowClass + '">';
                    html += '<td>' + escapeHtml(rec.name) + '</td>';
                    html += '<td><span class="record-type-badge record-type-' + rec.type + '">' + rec.type + '</span></td>';
                    html += '<td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escapeHtml(rec.expected) + '"><small>' + escapeHtml(rec.expected) + '</small></td>';

                    $.each(nsNames, function(ip, name) {
                        var val = rec.nsResults[ip];
                        if (val) {
                            html += '<td style="text-align:center;"><span class="prop-status-icon ok"><i class="fa fa-check"></i></span></td>';
                        } else {
                            html += '<td style="text-align:center;"><span class="prop-status-icon fail"><i class="fa fa-times"></i></span></td>';
                        }
                    });

                    html += '<td style="text-align:center;">' + (rec.propagated ?
                        '<span class="label label-success">{{ lang._("OK") }}</span>' :
                        '<span class="label label-warning">{{ lang._("Pending") }}</span>') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '<div class="propagation-summary"><strong>' + propagated + '/' + total + '</strong> {{ lang._("records propagated") }}</div>';

                $('#propagationBody').html(html);
            } else {
                $('#propagationBody').html('<div class="alert alert-danger">' + escapeHtml(data.message || '{{ lang._("Propagation check failed") }}') + '</div>');
            }
        });
    }

    // Zone Export
    $(document).on('click', '.zone-export-btn', function(e) {
        e.stopPropagation();
        var zoneId = $(this).data('zone-id');
        var zoneName = $(this).data('zone-name');
        var $btn = $(this).prop('disabled', true);
        $btn.html('<i class="fa fa-spinner fa-spin"></i>');

        ajaxCall('/api/hclouddns/hetzner/zoneExport', {
            account_uuid: currentAccountUuid,
            zone_id: zoneId
        }, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-download"></i>');
            if (data && data.status === 'ok' && data.content) {
                var blob = new Blob([data.content], {type: 'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = data.filename || (zoneName + '.zone');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } else {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: data.message || '{{ lang._("Export failed") }}'});
            }
        });
    });

    // Zone Import
    var importParsedRecords = [];

    $('#importZoneBtn').on('click', function() {
        importParsedRecords = [];
        $('#importContent').val('');
        $('.import-step').removeClass('active');
        $('#importStep1').addClass('active');
        $('#importParseBtn').show();
        $('#importApplyBtn').hide();

        // Populate zone selector
        var $select = $('#importTargetZone').empty();
        for (var id in zonesData) {
            $select.append('<option value="' + id + '">' + zonesData[id].name + '</option>');
        }
        $select.append('<option value="__new__">── {{ lang._("+ Create new zone...") }} ──</option>');
        $('#importCreateZoneRow').hide();

        $('#importModal').modal('show');
    });

    // File upload handler
    $('#importFileInput').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(evt) {
                $('#importContent').val(evt.target.result);
            };
            reader.readAsText(file);
        }
    });

    // Parse button
    $('#importParseBtn').on('click', function() {
        var content = $('#importContent').val().trim();
        if (!content) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Please paste or upload a zonefile first") }}'});
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Parsing...") }}');

        ajaxCall('/api/hclouddns/hetzner/zoneImportParse', {content: content}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-search"></i> {{ lang._("Parse") }}');
            if (data && data.status === 'ok' && data.records) {
                importParsedRecords = data.records;
                renderImportPreview();
                $('.import-step').removeClass('active');
                $('#importStep2').addClass('active');
                $('#importParseBtn').hide();
                $('#importApplyBtn').show();
            } else {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: data.message || '{{ lang._("Parse failed") }}'});
            }
        });
    });

    function renderImportPreview() {
        var $tbody = $('#importPreviewTable tbody').empty();
        $.each(importParsedRecords, function(i, rec) {
            var isSystem = rec.type === 'SOA' || rec.type === 'NS';
            var checked = isSystem ? '' : 'checked';
            var rowClass = isSystem ? 'import-record-skip' : 'import-record-new';
            var status = isSystem ? '<span class="label label-default">{{ lang._("Skip (managed)") }}</span>' : '<span class="label label-success">{{ lang._("New") }}</span>';

            $tbody.append(
                '<tr class="' + rowClass + '">' +
                '<td><input type="checkbox" class="import-check" data-idx="' + i + '" ' + checked + ' ' + (isSystem ? 'disabled' : '') + '></td>' +
                '<td>' + escapeHtml(rec.name) + '</td>' +
                '<td><span class="record-type-badge record-type-' + rec.type + '">' + rec.type + '</span></td>' +
                '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escapeHtml(rec.value) + '"><small>' + escapeHtml(rec.value) + '</small></td>' +
                '<td>' + rec.ttl + '</td>' +
                '<td>' + status + '</td>' +
                '</tr>'
            );
        });
    }

    $('#importSelectAll').on('click', function() {
        $('#importPreviewTable .import-check:not(:disabled)').prop('checked', true);
    });
    $('#importDeselectAll').on('click', function() {
        $('#importPreviewTable .import-check:not(:disabled)').prop('checked', false);
    });

    // Show/hide create zone form
    $('#importTargetZone').on('change', function() {
        if ($(this).val() === '__new__') {
            $('#importCreateZoneRow').show();
            $('#importNewZoneName').focus();
        } else {
            $('#importCreateZoneRow').hide();
        }
    });

    $('#importCancelCreateZone').on('click', function() {
        $('#importTargetZone').val($('#importTargetZone option:first').val()).trigger('change');
    });

    // Create zone handler
    $('#importCreateZoneBtn').on('click', function() {
        var zoneName = $('#importNewZoneName').val().trim().toLowerCase();
        if (!zoneName || zoneName.indexOf('.') === -1) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Please enter a valid domain name (e.g. example.com)") }}'});
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        ajaxCall('/api/hclouddns/hetzner/createZone', {
            account_uuid: currentAccountUuid,
            zone_name: zoneName
        }, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-plus"></i> {{ lang._("Create") }}');
            if (data && data.status === 'ok' && data.zone_id) {
                // Add to zonesData cache
                zonesData[data.zone_id] = {id: data.zone_id, name: data.zone_name || zoneName, records_count: 0};

                // Add to dropdown before the __new__ option and select it
                var $option = $('<option>').val(data.zone_id).text(data.zone_name || zoneName);
                $('#importTargetZone option[value="__new__"]').before($option);
                $('#importTargetZone').val(data.zone_id).trigger('change');
                $('#importNewZoneName').val('');

                // Also add to template zone selector if present
                var $tplSelect = $('#templateTargetZone');
                if ($tplSelect.length) {
                    $tplSelect.append($('<option>').val(data.zone_id).text(data.zone_name || zoneName));
                }

                // Refresh zone display
                renderZones();

                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: '{{ lang._("Zone created successfully:") }} ' + (data.zone_name || zoneName)});
            } else {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: data.message || '{{ lang._("Failed to create zone") }}'});
            }
        });
    });

    // Allow Enter key in zone name input
    $('#importNewZoneName').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#importCreateZoneBtn').click();
        }
    });

    // Apply import
    $('#importApplyBtn').on('click', function() {
        var selected = [];
        $('#importPreviewTable .import-check:checked').each(function() {
            selected.push(importParsedRecords[$(this).data('idx')]);
        });

        if (selected.length === 0) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("No records selected for import") }}'});
            return;
        }

        var targetZoneId = $('#importTargetZone').val();
        if (targetZoneId === '__new__') {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Please select or create a target zone first") }}'});
            return;
        }
        var targetZoneName = $('#importTargetZone option:selected').text();

        $('.import-step').removeClass('active');
        $('#importStep3').addClass('active');
        $('#importApplyBtn, #importParseBtn').hide();

        var $log = $('#importLog').empty();
        var $bar = $('#importProgressBar').css('width', '0%');
        var created = 0, errors = 0, total = selected.length;

        function importNext(idx) {
            if (idx >= total) {
                $bar.removeClass('active').css('width', '100%');
                $log.append('<div style="margin-top:10px;"><strong>{{ lang._("Done") }}: ' + created + ' {{ lang._("created") }}, ' + errors + ' {{ lang._("errors") }}</strong></div>');
                // Reload records for target zone
                loadRecords(targetZoneId);
                return;
            }

            var rec = selected[idx];
            var pct = Math.round(((idx + 1) / total) * 100);
            $bar.css('width', pct + '%').text(pct + '%');

            ajaxCall('/api/hclouddns/hetzner/createRecord', {
                account_uuid: currentAccountUuid,
                zone_id: targetZoneId,
                zone_name: targetZoneName,
                record_name: rec.name,
                record_type: rec.type,
                value: rec.value,
                ttl: rec.ttl
            }, function(data) {
                if (data && data.status === 'ok') {
                    created++;
                    $log.append('<div><i class="fa fa-check text-success"></i> ' + rec.name + ' ' + rec.type + '</div>');
                } else {
                    errors++;
                    $log.append('<div><i class="fa fa-times text-danger"></i> ' + rec.name + ' ' + rec.type + ': ' + (data.message || 'Error') + '</div>');
                }
                $log.scrollTop($log[0].scrollHeight);
                setTimeout(function() { importNext(idx + 1); }, 300);
            });
        }

        importNext(0);
    });

    // Record Templates
    var recordTemplates = {
        'mail-basic': {
            name: '{{ lang._("Mail Server Setup") }}',
            icon: 'fa-envelope',
            description: '{{ lang._("Complete mail server DNS setup: MX, A/AAAA, SPF, DKIM, DMARC, and autodiscover") }}',
            help: '{{ lang._("Creates all DNS records needed to run your own mail server: MX for mail routing, A/AAAA for the mail hostname, SPF to authorize sending servers, DKIM for message signing, DMARC for authentication policy and reporting, and autoconfig/autodiscover CNAMEs for automatic client configuration. Start with Soft Fail and Monitor mode, then tighten policies once everything works.") }}',
            params: [
                {id: 'mailHost', label: '{{ lang._("Mail Server Hostname") }}', placeholder: 'mail', default: 'mail', col: 1, help: '{{ lang._("The hostname of your mail server, e.g. mail. An MX record will point to mail.yourdomain.com, and an A record will be created for it.") }}'},
                {id: 'mailIp', label: '{{ lang._("Mail Server IPv4") }}', placeholder: '1.2.3.4', col: 1, help: '{{ lang._("The public IPv4 address of your mail server. Creates an A record for the mail hostname.") }}'},
                {id: 'mailIpv6', label: '{{ lang._("Mail Server IPv6 (optional)") }}', placeholder: '2001:db8::1', col: 1, help: '{{ lang._("Optional IPv6 address. Recommended for better deliverability.") }}'},
                {id: 'dkimSelector', label: '{{ lang._("DKIM Selector") }}', placeholder: 'default', default: 'default', col: 1, help: '{{ lang._("The DKIM selector name from your mail server (e.g. default, mail). Record will be selector._domainkey.") }}'},
                {id: 'dkimKey', label: '{{ lang._("DKIM Public Key (p=...)") }}', placeholder: 'MIGfMA0GCSqGSIb3DQEBAQUAA4...', col: 1, help: '{{ lang._("The DKIM public key (p= value). Leave empty to skip DKIM.") }}'},
                {id: 'spfPolicy', label: '{{ lang._("SPF Policy") }}', type: 'select', col: 2, options: [
                    {value: '~all', label: '~all (Soft Fail)'},
                    {value: '-all', label: '-all (Hard Fail)'}
                ], default: '~all', help: '{{ lang._("Soft Fail (~all) marks unauthorized mail as suspicious. Hard Fail (-all) rejects it. Use Soft Fail initially.") }}'},
                {id: 'dmarcPolicy', label: '{{ lang._("DMARC Policy") }}', type: 'select', col: 2, options: [
                    {value: 'none', label: 'none (Monitor)'},
                    {value: 'quarantine', label: 'quarantine'},
                    {value: 'reject', label: 'reject'}
                ], default: 'none', help: '{{ lang._("Monitor (none) collects reports only. Quarantine sends suspicious mail to spam. Reject blocks it. Start with Monitor.") }}'},
                {id: 'reportEmail', label: '{{ lang._("DMARC Report Email") }}', placeholder: 'postmaster@example.com', col: 2, help: '{{ lang._("Email address for DMARC aggregate reports about who sends email for your domain.") }}'},
                {id: 'autoconfig', label: '{{ lang._("Autoconfig / Autodiscover") }}', type: 'select', col: 2, options: [
                    {value: 'yes', label: '{{ lang._("Yes — create CNAMEs") }}'},
                    {value: 'no', label: '{{ lang._("No") }}'}
                ], default: 'yes', help: '{{ lang._("Thunderbird (autoconfig) and Outlook (autodiscover) CNAMEs for automatic client setup.") }}'}
            ],
            generate: function(params, zone) {
                var records = [
                    {name: '@', type: 'MX', value: '10 ' + params.mailHost + '.' + zone + '.', ttl: 300}
                ];
                // A record for mail hostname
                if (params.mailIp) {
                    records.push({name: params.mailHost, type: 'A', value: params.mailIp, ttl: 300});
                }
                // AAAA record for mail hostname
                if (params.mailIpv6) {
                    records.push({name: params.mailHost, type: 'AAAA', value: params.mailIpv6, ttl: 300});
                }
                // SPF
                records.push({name: '@', type: 'TXT', value: 'v=spf1 mx a ' + params.spfPolicy, ttl: 300});
                // DKIM
                if (params.dkimKey) {
                    var dkimValue = 'v=DKIM1; k=rsa; p=' + params.dkimKey.replace(/\s+/g, '');
                    records.push({name: (params.dkimSelector || 'default') + '._domainkey', type: 'TXT', value: dkimValue, ttl: 300});
                }
                // DMARC
                var dmarc = 'v=DMARC1; p=' + params.dmarcPolicy + ';';
                if (params.reportEmail) dmarc += ' rua=mailto:' + params.reportEmail + ';';
                records.push({name: '_dmarc', type: 'TXT', value: dmarc, ttl: 300});
                // Autoconfig / Autodiscover
                if (params.autoconfig === 'yes') {
                    records.push({name: 'autoconfig', type: 'CNAME', value: params.mailHost + '.' + zone + '.', ttl: 300});
                    records.push({name: 'autodiscover', type: 'CNAME', value: params.mailHost + '.' + zone + '.', ttl: 300});
                }
                return records;
            }
        },
        'google-workspace': {
            name: 'Google Workspace',
            icon: 'fa-google',
            description: '{{ lang._("Complete Google Workspace setup: MX, SPF, DKIM, DMARC, and verification") }}',
            help: '{{ lang._("Sets up all DNS records for Google Workspace: 5 MX records with correct priorities for mail routing, SPF to authorize Google servers, DKIM for message signing (get the key from Admin Console > Apps > Google Workspace > Gmail > Authenticate email), DMARC for policy enforcement, and the domain verification TXT record. Google recommends starting DMARC in Monitor mode.") }}',
            params: [
                {id: 'dkimKey', label: '{{ lang._("DKIM Public Key (optional)") }}', placeholder: 'MIGfMA0GCSqGSIb3DQEBAQUAA4...', help: '{{ lang._("From Google Admin Console: Apps > Google Workspace > Gmail > Authenticate email > Generate new record. Copy the TXT record value. Leave empty to skip — you can add it later via the DKIM wizard.") }}'},
                {id: 'dkimSelector', label: '{{ lang._("DKIM Selector") }}', placeholder: 'google', default: 'google', help: '{{ lang._("The DKIM selector shown in Google Admin Console. Default is google but can be customized during DKIM setup.") }}'},
                {id: 'verificationTxt', label: '{{ lang._("Domain Verification TXT (optional)") }}', placeholder: 'google-site-verification=...', help: '{{ lang._("The domain verification string from Google Admin Console > Setup > Verify domain. Paste the full value including google-site-verification=. Leave empty to skip.") }}'},
                {id: 'dmarcPolicy', label: '{{ lang._("DMARC Policy") }}', type: 'select', options: [
                    {value: 'none', label: 'none (Monitor)'},
                    {value: 'quarantine', label: 'quarantine'},
                    {value: 'reject', label: 'reject'}
                ], default: 'none', help: '{{ lang._("Monitor (none) only collects reports. Quarantine sends suspicious mail to spam. Reject blocks it. Google recommends starting with Monitor.") }}'},
                {id: 'reportEmail', label: '{{ lang._("DMARC Report Email") }}', placeholder: 'postmaster@example.com', help: '{{ lang._("Email address for DMARC aggregate reports. Use your admin address to monitor email authentication.") }}'}
            ],
            generate: function(params, zone) {
                var records = [
                    {name: '@', type: 'MX', value: '1 ASPMX.L.GOOGLE.COM.', ttl: 300},
                    {name: '@', type: 'MX', value: '5 ALT1.ASPMX.L.GOOGLE.COM.', ttl: 300},
                    {name: '@', type: 'MX', value: '5 ALT2.ASPMX.L.GOOGLE.COM.', ttl: 300},
                    {name: '@', type: 'MX', value: '10 ALT3.ASPMX.L.GOOGLE.COM.', ttl: 300},
                    {name: '@', type: 'MX', value: '10 ALT4.ASPMX.L.GOOGLE.COM.', ttl: 300},
                    {name: '@', type: 'TXT', value: 'v=spf1 include:_spf.google.com ~all', ttl: 300}
                ];
                // DKIM
                if (params.dkimKey) {
                    var dkimValue = 'v=DKIM1; k=rsa; p=' + params.dkimKey.replace(/\s+/g, '');
                    records.push({name: (params.dkimSelector || 'google') + '._domainkey', type: 'TXT', value: dkimValue, ttl: 300});
                }
                // DMARC
                var dmarc = 'v=DMARC1; p=' + params.dmarcPolicy + ';';
                if (params.reportEmail) dmarc += ' rua=mailto:' + params.reportEmail + ';';
                records.push({name: '_dmarc', type: 'TXT', value: dmarc, ttl: 300});
                // Domain verification
                if (params.verificationTxt) {
                    records.push({name: '@', type: 'TXT', value: params.verificationTxt, ttl: 300});
                }
                return records;
            }
        },
        'microsoft-365': {
            name: 'Microsoft 365',
            icon: 'fa-windows',
            description: '{{ lang._("Complete M365 setup: MX, SPF, DKIM, DMARC, autodiscover, and Teams/SIP") }}',
            help: '{{ lang._("Creates all DNS records recommended by Microsoft for a full Microsoft 365 deployment: MX for Exchange Online mail routing, SPF and DKIM for email authentication, DMARC for policy, autodiscover for Outlook auto-configuration, and SIP/Teams records for voice and federation. The DKIM CNAMEs point to Microsoft-managed signing keys — enable DKIM in the Defender portal after creating the records.") }}',
            params: [
                {id: 'tenant', label: '{{ lang._("Tenant Name") }}', placeholder: 'contoso', help: '{{ lang._("Your Microsoft 365 tenant name — the part before .onmicrosoft.com. Find it in the Microsoft 365 admin center under Setup > Domains.") }}'},
                {id: 'dmarcPolicy', label: '{{ lang._("DMARC Policy") }}', type: 'select', options: [
                    {value: 'none', label: 'none (Monitor)'},
                    {value: 'quarantine', label: 'quarantine'},
                    {value: 'reject', label: 'reject'}
                ], default: 'none', help: '{{ lang._("Monitor (none) only collects reports. Quarantine marks unauthorized mail as spam. Reject blocks it. Microsoft recommends starting with Monitor.") }}'},
                {id: 'reportEmail', label: '{{ lang._("DMARC Report Email") }}', placeholder: 'postmaster@example.com', help: '{{ lang._("Email address for DMARC aggregate reports. Use your admin address to track authentication results.") }}'},
                {id: 'teams', label: '{{ lang._("Teams / SIP Records") }}', type: 'select', options: [
                    {value: 'yes', label: '{{ lang._("Yes — create SIP + Teams SRV/CNAME records") }}'},
                    {value: 'no', label: '{{ lang._("No — email only") }}'}
                ], default: 'yes', help: '{{ lang._("Creates SIP and federation SRV records plus lyncdiscover and sip CNAMEs required for Microsoft Teams calling, meetings, and federation with external organizations.") }}'},
                {id: 'mdm', label: '{{ lang._("Intune / MDM Enrollment") }}', type: 'select', options: [
                    {value: 'yes', label: '{{ lang._("Yes — create MDM enrollment CNAMEs") }}'},
                    {value: 'no', label: '{{ lang._("No") }}'}
                ], default: 'no', help: '{{ lang._("Creates enterpriseenrollment and enterpriseregistration CNAMEs for automatic Intune/MDM device enrollment. Only needed if you use Microsoft Intune for device management.") }}'}
            ],
            generate: function(params, zone) {
                // Domain without dots for DKIM CNAME
                var domainDashed = zone.replace(/\./g, '-');
                var records = [
                    {name: '@', type: 'MX', value: '0 ' + params.tenant + '.mail.protection.outlook.com.', ttl: 3600},
                    {name: '@', type: 'TXT', value: 'v=spf1 include:spf.protection.outlook.com ~all', ttl: 3600},
                    {name: 'selector1._domainkey', type: 'CNAME', value: 'selector1-' + domainDashed + '._domainkey.' + params.tenant + '.onmicrosoft.com.', ttl: 3600},
                    {name: 'selector2._domainkey', type: 'CNAME', value: 'selector2-' + domainDashed + '._domainkey.' + params.tenant + '.onmicrosoft.com.', ttl: 3600}
                ];
                // DMARC
                var dmarc = 'v=DMARC1; p=' + params.dmarcPolicy + ';';
                if (params.reportEmail) dmarc += ' rua=mailto:' + params.reportEmail + ';';
                records.push({name: '_dmarc', type: 'TXT', value: dmarc, ttl: 3600});
                // Autodiscover
                records.push({name: 'autodiscover', type: 'CNAME', value: 'autodiscover.outlook.com.', ttl: 3600});
                // Teams / SIP
                if (params.teams === 'yes') {
                    records.push({name: 'sip', type: 'CNAME', value: 'sipdir.online.lync.com.', ttl: 3600});
                    records.push({name: 'lyncdiscover', type: 'CNAME', value: 'webdir.online.lync.com.', ttl: 3600});
                    records.push({name: '_sip._tls', type: 'SRV', value: '100 1 443 sipdir.online.lync.com.', ttl: 3600});
                    records.push({name: '_sipfederationtls._tcp', type: 'SRV', value: '100 1 5061 sipfed.online.lync.com.', ttl: 3600});
                }
                // MDM / Intune
                if (params.mdm === 'yes') {
                    records.push({name: 'enterpriseenrollment', type: 'CNAME', value: 'enterpriseenrollment.manage.microsoft.com.', ttl: 3600});
                    records.push({name: 'enterpriseregistration', type: 'CNAME', value: 'enterpriseregistration.windows.net.', ttl: 3600});
                }
                return records;
            }
        },
        'basic-website': {
            name: '{{ lang._("Basic Website") }}',
            icon: 'fa-globe',
            description: '{{ lang._("A, AAAA, www CNAME, and CAA for a website with SSL") }}',
            help: '{{ lang._("Creates all records for hosting a website with SSL: A record for the domain, optional AAAA for IPv6, a www CNAME, and a CAA record to restrict which Certificate Authorities can issue certificates for your domain. CAA is recommended for any site using HTTPS.") }}',
            params: [
                {id: 'ipv4', label: '{{ lang._("IPv4 Address") }}', placeholder: '1.2.3.4', help: '{{ lang._("The public IPv4 address of your web server. This is the IP that your domain will point to.") }}'},
                {id: 'ipv6', label: '{{ lang._("IPv6 Address (optional)") }}', placeholder: '2001:db8::1', help: '{{ lang._("Optional IPv6 address. If your server supports IPv6, adding an AAAA record allows IPv6-only clients to reach your site.") }}'},
                {id: 'caa', label: '{{ lang._("Certificate Authority (CAA)") }}', type: 'select', options: [
                    {value: 'letsencrypt', label: "Let's Encrypt"},
                    {value: 'zerossl', label: 'ZeroSSL'},
                    {value: 'buypass', label: 'Buypass'},
                    {value: 'sslcom', label: 'SSL.com'},
                    {value: 'digicert', label: 'DigiCert'},
                    {value: 'sectigo', label: 'Sectigo (Comodo)'},
                    {value: 'globalsign', label: 'GlobalSign'},
                    {value: 'entrust', label: 'Entrust'},
                    {value: 'google', label: 'Google Trust Services'},
                    {value: 'amazon', label: 'Amazon (AWS ACM)'},
                    {value: 'none', label: '{{ lang._("No CAA record") }}'}
                ], default: 'letsencrypt', help: '{{ lang._("A CAA record restricts which Certificate Authorities are allowed to issue SSL certificates for your domain. Choose the CA you use, or select No CAA to skip.") }}'},
                {id: 'caaCustom', label: '{{ lang._("Additional CA domain (optional)") }}', placeholder: 'otherca.com', help: '{{ lang._("Add a second CA if you use multiple providers. Enter the CA domain (e.g. otherca.com). Leave empty to skip.") }}'},
                {id: 'caaWild', label: '{{ lang._("Allow wildcard certificates") }}', type: 'select', options: [
                    {value: 'yes', label: '{{ lang._("Yes — also allow *.yourdomain.com") }}'},
                    {value: 'no', label: '{{ lang._("No — only exact domain names") }}'}
                ], default: 'no', help: '{{ lang._("If enabled, issuewild CAA records are added allowing wildcard certificates. Only enable if you actually use wildcard certs.") }}'}
            ],
            generate: function(params, zone) {
                var caaMap = {
                    'letsencrypt': 'letsencrypt.org',
                    'zerossl': 'zerossl.com',
                    'buypass': 'buypass.com',
                    'sslcom': 'ssl.com',
                    'digicert': 'digicert.com',
                    'sectigo': 'sectigo.com',
                    'globalsign': 'globalsign.com',
                    'entrust': 'entrust.net',
                    'google': 'pki.goog',
                    'amazon': 'amazonaws.com'
                };
                var records = [
                    {name: '@', type: 'A', value: params.ipv4, ttl: 300}
                ];
                if (params.ipv6) {
                    records.push({name: '@', type: 'AAAA', value: params.ipv6, ttl: 300});
                }
                records.push({name: 'www', type: 'CNAME', value: zone + '.', ttl: 300});
                if (params.caa !== 'none' && caaMap[params.caa]) {
                    records.push({name: '@', type: 'CAA', value: '0 issue "' + caaMap[params.caa] + '"', ttl: 300});
                    if (params.caaWild === 'yes') {
                        records.push({name: '@', type: 'CAA', value: '0 issuewild "' + caaMap[params.caa] + '"', ttl: 300});
                    }
                }
                // Custom additional CA
                if (params.caaCustom) {
                    var customDomain = params.caaCustom.trim().toLowerCase();
                    records.push({name: '@', type: 'CAA', value: '0 issue "' + customDomain + '"', ttl: 300});
                    if (params.caaWild === 'yes') {
                        records.push({name: '@', type: 'CAA', value: '0 issuewild "' + customDomain + '"', ttl: 300});
                    }
                }
                return records;
            }
        }
    };

    var selectedTemplate = null;

    // Custom templates (localStorage)
    var CUSTOM_TPL_KEY = 'hclouddns_custom_templates';
    function loadCustomTemplates() {
        try { return JSON.parse(localStorage.getItem(CUSTOM_TPL_KEY)) || {}; } catch(e) { return {}; }
    }
    function saveCustomTemplates(data) {
        localStorage.setItem(CUSTOM_TPL_KEY, JSON.stringify(data));
    }

    function getAllTemplates() {
        var all = $.extend({}, recordTemplates);
        var custom = loadCustomTemplates();
        $.each(custom, function(key, tpl) {
            all[key] = {
                name: tpl.name,
                icon: 'fa-user',
                description: tpl.description,
                isCustom: true,
                params: [],
                generate: function() { return tpl.records; }
            };
        });
        return all;
    }

    function renderTemplateList() {
        var templates = getAllTemplates();
        var html = '';
        $.each(templates, function(key, tpl) {
            var deleteBtn = tpl.isCustom ? '<span class="template-delete-btn" data-tpl-key="' + key + '" title="{{ lang._("Delete template") }}"><i class="fa fa-trash"></i></span>' : '';
            var helpSnippet = tpl.help ? '<p style="margin-top:6px;font-size:11px;color:#888;"><i class="fa fa-info-circle"></i> ' + tpl.help + '</p>' : '';
            html += '<div class="template-card" data-template="' + key + '">' +
                deleteBtn +
                '<h5><i class="fa ' + tpl.icon + '"></i> ' + tpl.name + '</h5>' +
                '<p>' + tpl.description + '</p>' +
                helpSnippet +
            '</div>';
        });
        html += '<div class="template-card create-custom" id="createCustomTplCard">' +
            '<h5><i class="fa fa-plus"></i> {{ lang._("Create Custom Template") }}</h5>' +
            '<p>{{ lang._("Save your own record set as a reusable template") }}</p>' +
        '</div>';
        $('#templateList').html(html);
    }

    function addCustomRecordRow(name, type, value, ttl) {
        var types = ['A','AAAA','CNAME','MX','TXT','NS','SRV','CAA','PTR'];
        var typeOpts = '';
        $.each(types, function(i, t) {
            typeOpts += '<option value="' + t + '"' + (t === (type || 'A') ? ' selected' : '') + '>' + t + '</option>';
        });
        var row = '<div class="custom-record-row">' +
            '<input type="text" class="form-control" style="width:20%;" placeholder="{{ lang._("Name") }}" value="' + escapeHtml(name || '') + '">' +
            '<select class="form-control" style="width:15%;">' + typeOpts + '</select>' +
            '<input type="text" class="form-control" style="width:40%;" placeholder="{{ lang._("Value") }}" value="' + escapeHtml(value || '') + '">' +
            '<input type="number" class="form-control" style="width:12%;" placeholder="TTL" value="' + (ttl || 300) + '" min="60" max="86400">' +
            '<button class="btn btn-xs btn-danger custom-record-remove"><i class="fa fa-times"></i></button>' +
        '</div>';
        $('#customTplRecords').append(row);
    }

    $('#templatesBtn').on('click', function() {
        selectedTemplate = null;
        // Populate zone selector
        var $select = $('#templateTargetZone').empty();
        for (var id in zonesData) {
            $select.append('<option value="' + id + '">' + zonesData[id].name + '</option>');
        }

        renderTemplateList();

        // Reset steps
                $('#templateStep1').show();
        $('#templateStep2, #templateStep3, #templateStepCustom').hide();
        $('#templateBackBtn, #templateApplyBtn, #customTplSaveBtn').hide();

        $('#templateModal').modal('show');
    });

    // Delete custom template
    $(document).on('click', '.template-delete-btn', function(e) {
        e.stopPropagation();
        var key = $(this).data('tpl-key');
        var custom = loadCustomTemplates();
        var name = custom[key] ? custom[key].name : key;
        BootstrapDialog.confirm({
            title: '{{ lang._("Delete Template") }}',
            message: '{{ lang._("Delete custom template") }} "' + escapeHtml(name) + '"?',
            type: BootstrapDialog.TYPE_DANGER,
            btnOKLabel: '{{ lang._("Delete") }}',
            btnOKClass: 'btn-danger',
            callback: function(result) {
                if (result) {
                    delete custom[key];
                    saveCustomTemplates(custom);
                    renderTemplateList();
                }
            }
        });
    });

    // Open custom template creator
    $(document).on('click', '#createCustomTplCard', function() {
        $('#customTplName').val('');
        $('#customTplDesc').val('');
        $('#customTplRecords').empty();
        addCustomRecordRow('@', 'A', '', 300);
        addCustomRecordRow('www', 'CNAME', '@', 300);

                $('#templateStep1').hide();
        $('#templateStepCustom').show();
        $('#templateBackBtn, #customTplSaveBtn').show();
        $('#templateApplyBtn').hide();
    });

    // Add record row
    $('#customTplAddRecord').on('click', function() {
        addCustomRecordRow('', 'A', '', 300);
    });

    // Remove record row
    $(document).on('click', '.custom-record-remove', function() {
        $(this).closest('.custom-record-row').remove();
    });

    // Save custom template
    $('#customTplSaveBtn').on('click', function() {
        var name = $('#customTplName').val().trim();
        if (!name) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Please enter a template name") }}'});
            return;
        }

        var records = [];
        $('#customTplRecords .custom-record-row').each(function() {
            var $inputs = $(this).find('.form-control');
            var recName = $inputs.eq(0).val().trim();
            var recType = $inputs.eq(1).val();
            var recValue = $inputs.eq(2).val().trim();
            var recTtl = parseInt($inputs.eq(3).val()) || 300;
            if (recName && recValue) {
                records.push({name: recName, type: recType, value: recValue, ttl: recTtl});
            }
        });

        if (records.length === 0) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Please add at least one record with name and value") }}'});
            return;
        }

        var key = 'custom-' + Date.now();
        var custom = loadCustomTemplates();
        custom[key] = {
            name: name,
            description: $('#customTplDesc').val().trim() || name,
            records: records
        };
        saveCustomTemplates(custom);

        // Back to list
        renderTemplateList();
        $('#templateStepCustom').hide();
                $('#templateStep1').show();
        $('#customTplSaveBtn').hide();
        $('#templateBackBtn').hide();

        BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: '{{ lang._("Template saved:") }} ' + escapeHtml(name)});
    });

    // Template card click
    $(document).on('click', '.template-card:not(.create-custom)', function() {
        var key = $(this).data('template');
        selectedTemplate = key;
        var tpl = getAllTemplates()[key];

        // Build params form with help info — use 2-column layout if template has columns
        var html = '';
        if (tpl.help) {
            html += '<div class="alert alert-info" style="font-size: 12px; padding: 8px 12px;"><i class="fa fa-info-circle"></i> ' + tpl.help + '</div>';
        }

        function buildField(p) {
            var f = '<div class="form-group">';
            f += '<label>' + p.label + '</label>';
            if (p.type === 'select') {
                f += '<select class="form-control template-param" id="tpl_' + p.id + '">';
                $.each(p.options, function(j, opt) {
                    f += '<option value="' + opt.value + '"' + (opt.value === p.default ? ' selected' : '') + '>' + opt.label + '</option>';
                });
                f += '</select>';
            } else {
                f += '<input type="text" class="form-control template-param" id="tpl_' + p.id + '" placeholder="' + (p.placeholder || '') + '" value="' + (p.default || '') + '">';
            }
            if (p.help) {
                f += '<p class="help-text">' + p.help + '</p>';
            }
            f += '</div>';
            return f;
        }

        var hasColumns = tpl.params.some(function(p) { return p.col; });
        if (hasColumns) {
            var leftHtml = '', rightHtml = '';
            $.each(tpl.params, function(i, p) {
                if (p.col === 2) {
                    rightHtml += buildField(p);
                } else {
                    leftHtml += buildField(p);
                }
            });
            html += '<div class="row"><div class="col-md-6">' + leftHtml + '</div><div class="col-md-6">' + rightHtml + '</div></div>';
        } else {
            $.each(tpl.params, function(i, p) {
                html += buildField(p);
            });
        }
        $('#templateParams').html(html);
        $('#templateParamsTitle').text(tpl.name);

        // Show step 2
        $('#templateStep1').hide();
        $('#templateStep2').show();
        $('#templateBackBtn, #templateApplyBtn').show();

        updateTemplatePreview();

        // Bind param change events
        $('.template-param').on('change keyup', updateTemplatePreview);
    });

    function updateTemplatePreview() {
        if (!selectedTemplate) return;
        var tpl = getAllTemplates()[selectedTemplate];
        var params = {};
        $.each(tpl.params, function(i, p) {
            params[p.id] = $('#tpl_' + p.id).val() || '';
        });
        var zone = $('#templateTargetZone option:selected').text();
        var records = tpl.generate(params, zone);

        var $tbody = $('#templatePreviewTable tbody').empty();
        $.each(records, function(i, rec) {
            $tbody.append(
                '<tr>' +
                '<td><input type="checkbox" class="tpl-check" data-idx="' + i + '" checked></td>' +
                '<td>' + escapeHtml(rec.name) + '</td>' +
                '<td><span class="record-type-badge record-type-' + rec.type + '">' + rec.type + '</span></td>' +
                '<td><small>' + escapeHtml(rec.value) + '</small></td>' +
                '<td>' + rec.ttl + '</td>' +
                '</tr>'
            );
        });
    }

    $('#templateTargetZone').on('change', updateTemplatePreview);

    $('#templateBackBtn').on('click', function() {
        $('#templateStep2, #templateStep3, #templateStepCustom').hide();
                $('#templateStep1').show();
        $('#templateBackBtn, #templateApplyBtn, #customTplSaveBtn').hide();
        selectedTemplate = null;
    });

    // Apply template
    $('#templateApplyBtn').on('click', function() {
        if (!selectedTemplate) return;
        var tpl = getAllTemplates()[selectedTemplate];
        var params = {};
        $.each(tpl.params, function(i, p) {
            params[p.id] = $('#tpl_' + p.id).val() || '';
        });
        var targetZoneId = $('#templateTargetZone').val();
        var targetZoneName = $('#templateTargetZone option:selected').text();
        var allRecords = tpl.generate(params, targetZoneName);

        // Get selected records
        var selected = [];
        $('#templatePreviewTable .tpl-check:checked').each(function() {
            selected.push(allRecords[$(this).data('idx')]);
        });

        if (selected.length === 0) return;

                $('#templateStep2').hide();
        $('#templateStep3').show();
        $('#templateApplyBtn, #templateBackBtn').hide();

        var $log = $('#templateLog').empty();
        var $bar = $('#templateProgressBar').css('width', '0%');
        var created = 0, errors = 0, total = selected.length;

        function applyNext(idx) {
            if (idx >= total) {
                $bar.removeClass('active').css('width', '100%');
                $log.append('<div style="margin-top:10px;"><strong>{{ lang._("Done") }}: ' + created + ' {{ lang._("created") }}, ' + errors + ' {{ lang._("errors") }}</strong></div>');
                loadRecords(targetZoneId);
                return;
            }

            var rec = selected[idx];
            var pct = Math.round(((idx + 1) / total) * 100);
            $bar.css('width', pct + '%').text(pct + '%');

            ajaxCall('/api/hclouddns/hetzner/createRecord', {
                account_uuid: currentAccountUuid,
                zone_id: targetZoneId,
                zone_name: targetZoneName,
                record_name: rec.name,
                record_type: rec.type,
                value: rec.value,
                ttl: rec.ttl
            }, function(data) {
                if (data && data.status === 'ok') {
                    created++;
                    $log.append('<div><i class="fa fa-check text-success"></i> ' + rec.name + ' ' + rec.type + '</div>');
                } else {
                    errors++;
                    $log.append('<div><i class="fa fa-times text-danger"></i> ' + rec.name + ' ' + rec.type + ': ' + (data.message || 'Error') + '</div>');
                }
                $log.scrollTop($log[0].scrollHeight);
                setTimeout(function() { applyNext(idx + 1); }, 300);
            });
        }

        applyNext(0);
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

    // ========== Feature: Keyboard Shortcuts ==========
    $(document).on('keydown', function(e) {
        // Skip if input/textarea/select is focused or modal is open
        if ($(e.target).is('input, textarea, select') || $('.modal.in').length > 0) return;

        switch(e.key) {
            case '/':
                e.preventDefault();
                $('#zoneSearchInput').focus();
                break;
            case 's':
                e.preventDefault();
                if (!$('#globalSearchBtn').prop('disabled')) {
                    $('#globalSearchBtn').click();
                }
                break;
            case 'r':
                e.preventDefault();
                if (!$('#refreshZonesBtn').prop('disabled')) {
                    $('#refreshZonesBtn').click();
                }
                break;
            case 'i':
                e.preventDefault();
                if (!$('#importZoneBtn').prop('disabled')) {
                    $('#importZoneBtn').click();
                }
                break;
            case 't':
                e.preventDefault();
                if (!$('#templatesBtn').prop('disabled')) {
                    $('#templatesBtn').click();
                }
                break;
            case 'h':
                e.preventDefault();
                if (!$('#historyBtn').prop('disabled')) {
                    $('#historyBtn').click();
                }
                break;
            case '?':
                e.preventDefault();
                $('#shortcutsModal').modal('show');
                break;
        }
    });

    $('#shortcutsHint').on('click', function() {
        $('#shortcutsModal').modal('show');
    });

    // ========== Feature: Record Cloning ==========
    $(document).on('click', '.clone-record-btn', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var recordId = $row.data('record-id');
        var zoneId = $row.data('zone-id');
        var $zonePanel = $row.closest('.zone-panel');
        var zoneName = $zonePanel.data('zone-name');

        // Get record data from cache
        var records = zoneRecordsCache[zoneId] || [];
        var record = null;
        for (var i = 0; i < records.length; i++) {
            if (records[i].id == recordId) {
                record = records[i];
                break;
            }
        }

        if (!record) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: '{{ lang._("Record not found in cache. Try expanding the zone first.") }}'});
            return;
        }

        // Build zone options
        var zoneOptions = '';
        for (var id in zonesData) {
            if (id !== zoneId) {
                zoneOptions += '<option value="' + id + '">' + zonesData[id].name + '</option>';
            }
        }

        if (!zoneOptions) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_INFO, message: '{{ lang._("No other zones available to clone to.") }}'});
            return;
        }

        var recDisplay = record.name + ' <span class="record-type-badge record-type-' + record.type + '">' + record.type + '</span> ' + escapeHtml(record.value.length > 60 ? record.value.substring(0, 60) + '...' : record.value);

        BootstrapDialog.show({
            title: '<i class="fa fa-copy"></i> {{ lang._("Clone Record") }}',
            message: '<div class="form-group">' +
                '<label>{{ lang._("Record") }}</label>' +
                '<p>' + recDisplay + '</p>' +
                '</div>' +
                '<div class="form-group">' +
                '<label>{{ lang._("Clone to Zone") }}</label>' +
                '<select class="form-control" id="cloneTargetZone">' + zoneOptions + '</select>' +
                '</div>' +
                '<div class="form-group">' +
                '<label>{{ lang._("Record Name") }}</label>' +
                '<input type="text" class="form-control" id="cloneRecordName" value="' + escapeHtml(record.name) + '">' +
                '<p class="help-text">{{ lang._("Optionally change the record name for the target zone") }}</p>' +
                '</div>',
            type: BootstrapDialog.TYPE_INFO,
            buttons: [{
                label: '{{ lang._("Cancel") }}',
                action: function(dlg) { dlg.close(); }
            }, {
                label: '<i class="fa fa-copy"></i> {{ lang._("Clone") }}',
                cssClass: 'btn-primary',
                action: function(dlg) {
                    var targetZoneId = dlg.getModalBody().find('#cloneTargetZone').val();
                    var targetZoneName = dlg.getModalBody().find('#cloneTargetZone option:selected').text();
                    var cloneName = dlg.getModalBody().find('#cloneRecordName').val().trim() || record.name;

                    var $btn = dlg.getButton('btn-primary');
                    if ($btn) $btn.disable();

                    ajaxCall('/api/hclouddns/hetzner/createRecord', {
                        account_uuid: currentAccountUuid,
                        zone_id: targetZoneId,
                        zone_name: targetZoneName,
                        record_name: cloneName,
                        record_type: record.type,
                        value: record.value,
                        ttl: record.ttl || 300
                    }, function(data) {
                        dlg.close();
                        if (data && data.status === 'ok') {
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_SUCCESS,
                                message: '{{ lang._("Record cloned successfully to") }} ' + targetZoneName
                            });
                            // Refresh target zone if it's expanded
                            var $targetRecords = $('#zone-records-' + targetZoneId);
                            if ($targetRecords.hasClass('show')) {
                                loadRecords(targetZoneId);
                            }
                        } else {
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_DANGER,
                                message: '{{ lang._("Failed to clone record") }}: ' + (data.message || '{{ lang._("Unknown error") }}')
                            });
                        }
                    });
                }
            }]
        });
    });

    // ========== Feature: Global Record Search ==========
    var globalSearchDebounce = null;

    $('#globalSearchBtn').on('click', function() {
        $('#globalSearchModal').modal('show');
        setTimeout(function() { $('#globalSearchInput').focus(); }, 300);
    });

    // Auto-focus search input when modal opens
    $('#globalSearchModal').on('shown.bs.modal', function() {
        $('#globalSearchInput').focus();
    });

    function performGlobalSearch() {
        var query = $('#globalSearchInput').val().trim().toLowerCase();
        var typeFilter = $('#globalSearchType').val();
        var $results = $('#globalSearchResults');

        if (!query && !typeFilter) {
            $results.html('<div class="text-center text-muted" style="padding: 40px;"><i class="fa fa-search fa-3x"></i><p style="margin-top: 15px;">{{ lang._("Start typing to search across all zones") }}</p></div>');
            $('#globalSearchCount').text('');
            return;
        }

        var matches = [];
        var loadedZones = 0;
        var totalZones = Object.keys(zonesData).length;

        for (var zoneId in zoneRecordsCache) {
            loadedZones++;
            var zName = zonesData[zoneId] ? zonesData[zoneId].name : zoneId;
            var records = zoneRecordsCache[zoneId];

            for (var i = 0; i < records.length; i++) {
                var rec = records[i];
                // Type filter
                if (typeFilter && rec.type !== typeFilter) continue;
                // Text search
                if (query) {
                    var nameMatch = rec.name.toLowerCase().indexOf(query) !== -1;
                    var valueMatch = rec.value.toLowerCase().indexOf(query) !== -1;
                    var zoneMatch = zName.toLowerCase().indexOf(query) !== -1;
                    if (!nameMatch && !valueMatch && !zoneMatch) continue;
                }
                matches.push({record: rec, zoneId: zoneId, zoneName: zName});
            }
        }

        // Show info about unloaded zones
        var unloaded = totalZones - loadedZones;
        if (unloaded > 0) {
            $('#globalSearchInfo').show().html('<i class="fa fa-info-circle"></i> ' + unloaded + ' {{ lang._("zone(s) not yet loaded. Expand them first or click") }} <strong>{{ lang._("Refresh All") }}</strong> {{ lang._("for a complete search.") }}');
        } else {
            $('#globalSearchInfo').hide();
        }

        $('#globalSearchCount').text(matches.length + ' {{ lang._("result(s)") }}');

        if (matches.length === 0) {
            $results.html('<div class="text-center text-muted" style="padding: 30px;"><i class="fa fa-search"></i> {{ lang._("No records found") }}</div>');
            return;
        }

        // Limit display to 200 results
        var displayMatches = matches.slice(0, 200);

        var html = '<table class="table table-condensed table-hover"><thead><tr>' +
            '<th>{{ lang._("Zone") }}</th><th>{{ lang._("Name") }}</th><th>{{ lang._("Type") }}</th><th>{{ lang._("Value") }}</th><th style="width:60px;">{{ lang._("TTL") }}</th><th style="width:130px;">{{ lang._("Actions") }}</th>' +
            '</tr></thead><tbody>';

        for (var j = 0; j < displayMatches.length; j++) {
            var m = displayMatches[j];
            var rec = m.record;
            var isSystem = rec.type === 'SOA' || rec.type === 'NS';
            var valDisplay = rec.value.length > 50 ? escapeHtml(rec.value.substring(0, 50)) + '...' : escapeHtml(rec.value);

            html += '<tr>' +
                '<td><strong>' + escapeHtml(m.zoneName) + '</strong></td>' +
                '<td>' + escapeHtml(rec.name) + '</td>' +
                '<td><span class="record-type-badge record-type-' + rec.type + '">' + rec.type + '</span></td>' +
                '<td title="' + escapeHtml(rec.value) + '"><span class="record-value">' + valDisplay + '</span></td>' +
                '<td>' + (rec.ttl || 300) + '</td>' +
                '<td>' +
                    (isSystem ? '' :
                    '<button class="btn btn-xs btn-default global-search-goto" data-zone-id="' + m.zoneId + '" title="{{ lang._("Go to zone") }}"><i class="fa fa-arrow-right"></i></button> ' +
                    '<button class="btn btn-xs btn-info global-search-clone" data-zone-id="' + m.zoneId + '" data-record-id="' + (rec.id || '') + '" title="{{ lang._("Clone") }}"><i class="fa fa-copy"></i></button>') +
                '</td>' +
            '</tr>';
        }

        html += '</tbody></table>';
        if (matches.length > 200) {
            html += '<div class="text-center text-muted" style="padding: 10px;">{{ lang._("Showing first 200 of") }} ' + matches.length + ' {{ lang._("results. Refine your search.") }}</div>';
        }
        $results.html(html);
    }

    $('#globalSearchInput').on('keyup', function() {
        clearTimeout(globalSearchDebounce);
        globalSearchDebounce = setTimeout(performGlobalSearch, 300);
    });

    $('#globalSearchType').on('change', performGlobalSearch);

    // Go-to zone from global search
    $(document).on('click', '.global-search-goto', function() {
        var zoneId = $(this).data('zone-id');
        $('#globalSearchModal').modal('hide');

        // Scroll to zone and expand it
        var $panel = $('.zone-panel[data-zone-id="' + zoneId + '"]');
        if ($panel.length) {
            // Expand parent group if collapsed
            var $group = $panel.closest('.zone-group-body');
            if ($group.hasClass('collapsed')) {
                $group.removeClass('collapsed');
                $group.prev('.zone-group-header').removeClass('collapsed');
            }

            // Expand zone if not already
            var $records = $panel.find('.zone-records');
            if (!$records.hasClass('show')) {
                $panel.find('.zone-header').click();
            }

            // Scroll to zone
            $('html, body').animate({scrollTop: $panel.offset().top - 100}, 300);
        }
    });

    // Clone from global search
    $(document).on('click', '.global-search-clone', function() {
        var zoneId = $(this).data('zone-id');
        var recordId = $(this).data('record-id');

        var records = zoneRecordsCache[zoneId] || [];
        var record = null;
        for (var i = 0; i < records.length; i++) {
            if (records[i].id == recordId) {
                record = records[i];
                break;
            }
        }
        if (!record) return;

        // Close search modal and trigger clone
        $('#globalSearchModal').modal('hide');
        setTimeout(function() {
            // Find the record row and trigger clone
            var $row = $('tr[data-record-id="' + recordId + '"][data-zone-id="' + zoneId + '"]');
            if ($row.length) {
                $row.find('.clone-record-btn').click();
            } else {
                // Record row might not be visible, trigger clone directly
                var zoneOptions = '';
                for (var id in zonesData) {
                    if (id !== zoneId) {
                        zoneOptions += '<option value="' + id + '">' + zonesData[id].name + '</option>';
                    }
                }
                if (!zoneOptions) return;

                BootstrapDialog.show({
                    title: '<i class="fa fa-copy"></i> {{ lang._("Clone Record") }}',
                    message: '<div class="form-group"><label>{{ lang._("Clone to Zone") }}</label><select class="form-control" id="cloneTargetZone">' + zoneOptions + '</select></div>' +
                        '<div class="form-group"><label>{{ lang._("Record Name") }}</label><input type="text" class="form-control" id="cloneRecordName" value="' + escapeHtml(record.name) + '"></div>',
                    type: BootstrapDialog.TYPE_INFO,
                    buttons: [{
                        label: '{{ lang._("Cancel") }}',
                        action: function(dlg) { dlg.close(); }
                    }, {
                        label: '<i class="fa fa-copy"></i> {{ lang._("Clone") }}',
                        cssClass: 'btn-primary',
                        action: function(dlg) {
                            var targetZoneId = dlg.getModalBody().find('#cloneTargetZone').val();
                            var targetZoneName = dlg.getModalBody().find('#cloneTargetZone option:selected').text();
                            var cloneName = dlg.getModalBody().find('#cloneRecordName').val().trim() || record.name;
                            ajaxCall('/api/hclouddns/hetzner/createRecord', {
                                account_uuid: currentAccountUuid, zone_id: targetZoneId, zone_name: targetZoneName,
                                record_name: cloneName, record_type: record.type, value: record.value, ttl: record.ttl || 300
                            }, function(data) {
                                dlg.close();
                                if (data && data.status === 'ok') {
                                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: '{{ lang._("Record cloned successfully to") }} ' + targetZoneName});
                                    var $targetRecords = $('#zone-records-' + targetZoneId);
                                    if ($targetRecords.hasClass('show')) loadRecords(targetZoneId);
                                } else {
                                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: '{{ lang._("Failed to clone record") }}: ' + (data.message || '{{ lang._("Unknown error") }}')});
                                }
                            });
                        }
                    }]
                });
            }
        }, 500);
    });

    // Refresh All zones for global search
    $('#globalSearchRefreshAll').on('click', function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Loading...") }}');
        var zoneIds = Object.keys(zonesData);
        var loaded = 0;
        var total = zoneIds.length;

        function loadNext(idx) {
            if (idx >= total) {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> {{ lang._("Refresh All") }}');
                performGlobalSearch();
                return;
            }

            var zoneId = zoneIds[idx];
            // Skip already loaded zones
            if (zoneRecordsCache[zoneId]) {
                loaded++;
                $btn.html('<i class="fa fa-spinner fa-spin"></i> ' + loaded + '/' + total);
                loadNext(idx + 1);
                return;
            }

            ajaxCall('/api/hclouddns/hetzner/listRecordsForAccount', {
                account_uuid: currentAccountUuid, zone_id: zoneId, all_types: '1'
            }, function(data) {
                loaded++;
                if (data && data.status === 'ok' && data.records) {
                    zoneRecordsCache[zoneId] = data.records;
                }
                $btn.html('<i class="fa fa-spinner fa-spin"></i> ' + loaded + '/' + total);
                setTimeout(function() { loadNext(idx + 1); }, 100);
            });
        }

        loadNext(0);
    });

    // ========== Feature: DNSSEC Status ==========
    var dnssecCache = {};

    function loadDnssecStatus(zoneId, zoneName) {
        if (dnssecCache[zoneId]) {
            renderDnssecBadge(zoneId, dnssecCache[zoneId]);
            return;
        }

        ajaxCall('/api/hclouddns/hetzner/dnssecStatus', {
            account_uuid: currentAccountUuid,
            zone_name: zoneName
        }, function(data) {
            if (data && data.status === 'ok' && data.dnssec) {
                dnssecCache[zoneId] = data.dnssec;
                renderDnssecBadge(zoneId, data.dnssec);
            }
        });
    }

    function renderDnssecBadge(zoneId, dnssec) {
        var $badge = $('.zone-panel[data-zone-id="' + zoneId + '"] .dnssec-badge');
        if ($badge.length === 0) {
            // Create badge after zone name
            var $zoneName = $('.zone-panel[data-zone-id="' + zoneId + '"] .zone-name');
            $badge = $('<span class="dnssec-badge"></span>');
            $zoneName.after($badge);
        }

        if (dnssec.signed && dnssec.delegated) {
            $badge.html('<i class="fa fa-shield dnssec-signed-delegated"></i>')
                .attr('title', '{{ lang._("DNSSEC: Signed + Delegated") }} (' + dnssec.dnskey_count + ' DNSKEY)');
        } else if (dnssec.signed) {
            $badge.html('<i class="fa fa-shield dnssec-signed-only"></i>')
                .attr('title', '{{ lang._("DNSSEC: Signed but not delegated (no DS record at parent)") }}');
        } else {
            $badge.html('<i class="fa fa-shield dnssec-not-signed"></i>')
                .attr('title', '{{ lang._("DNSSEC: Not signed") }}');
        }
    }
});
</script>
