{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Hetzner Cloud Dynamic DNS - Main Interface with Tabs
#}

<style>
    .tab-content { padding-top: 20px; }
    /* Dashboard tiles */
    .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
    .dashboard-tile { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px; text-align: center; transition: box-shadow 0.2s; }
    .dashboard-tile:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .dashboard-tile .tile-icon { font-size: 32px; color: #337ab7; margin-bottom: 10px; }
    .dashboard-tile .tile-count { font-size: 36px; font-weight: bold; color: #333; }
    .dashboard-tile .tile-label { font-size: 14px; color: #666; margin-top: 5px; }
    .dashboard-tile .tile-detail { font-size: 12px; color: #999; margin-top: 8px; }
    /* Simulation section */
    .sim-section { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
    .sim-section.active { background: #fff3cd; border-color: #ffc107; }
    .sim-section h4 { margin-top: 0; margin-bottom: 15px; }
    .sim-gateways { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
    .sim-gateway { flex: 1; min-width: 200px; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 12px; display: flex; justify-content: space-between; align-items: center; }
    .sim-gateway.up { border-left: 4px solid #5cb85c; }
    .sim-gateway.down { border-left: 4px solid #d9534f; }
    .sim-gateway.simulated { border-left: 4px solid #f0ad4e; background: #fff8e1; }
    .sim-gateway .gw-name { font-weight: 500; }
    .sim-gateway .gw-ip { font-size: 12px; color: #666; }
    .sim-gateway .gw-status { text-align: right; }
    /* Entry status table */
    .entry-status-table { margin-top: 15px; }
    .entry-status-table td, .entry-status-table th { padding: 8px 12px; }
    .bg-success { background-color: #dff0d8 !important; transition: background-color 0.3s; }
    /* Info box for scheduled tab */
    .info-box { background: #d9edf7; border: 1px solid #bce8f1; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #31708f; }
    .info-box p { margin: 0 0 10px 0; }
    .info-box ul { margin: 0; padding-left: 20px; }
    .info-box li { margin: 5px 0; }
    .info-box em { color: #5a8fa8; }
</style>

<!-- No Accounts Warning -->
<div id="noAccountsWarning" class="alert alert-warning" style="display: none;">
    <h4><i class="fa fa-exclamation-triangle"></i> {{ lang._('No API Accounts Configured') }}</h4>
    <p>{{ lang._('You need to add at least one Hetzner DNS API account before you can manage DNS entries.') }}</p>
    <a href="/ui/hclouddns/settings" class="btn btn-warning"><i class="fa fa-cog"></i> {{ lang._('Go to Settings') }}</a>
</div>

<div id="mainContent">
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#overview"><i class="fa fa-dashboard"></i> {{ lang._('Overview') }}</a></li>
    <li><a data-toggle="tab" href="#gateways"><i class="fa fa-server"></i> {{ lang._('Gateways') }}</a></li>
    <li><a data-toggle="tab" href="#entries"><i class="fa fa-list"></i> {{ lang._('DNS Entries') }}</a></li>
    <li><a data-toggle="tab" href="#scheduled"><i class="fa fa-clock-o"></i> {{ lang._('Scheduled') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- ==================== OVERVIEW TAB ==================== -->
    <div id="overview" class="tab-pane fade in active">
        <!-- Status Bar -->
        <div class="alert alert-info" id="statusBar" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong><i class="fa fa-info-circle"></i> {{ lang._('Service Status:') }}</strong>
                <span id="serviceStatus" class="label label-default">{{ lang._('Loading...') }}</span>
                <span id="statusSummary" class="text-muted" style="margin-left: 15px;"></span>
            </div>
            <div class="text-muted small">
                <i class="fa fa-clock-o"></i> {{ lang._('Last refresh:') }} <span id="lastRefresh">-</span>
                <label style="margin-left: 15px; font-weight: normal;">
                    <input type="checkbox" id="autoRefresh"> {{ lang._('Auto-refresh (30s)') }}
                </label>
            </div>
        </div>

        <!-- Dashboard Tiles -->
        <div class="dashboard-grid">
            <div class="dashboard-tile">
                <div class="tile-icon"><i class="fa fa-server"></i></div>
                <div class="tile-count" id="gatewayCount">0</div>
                <div class="tile-label">{{ lang._('Gateways') }}</div>
                <div class="tile-detail" id="gatewayDetail">-</div>
            </div>
            <div class="dashboard-tile">
                <div class="tile-icon"><i class="fa fa-key"></i></div>
                <div class="tile-count" id="accountCount">0</div>
                <div class="tile-label">{{ lang._('Accounts') }}</div>
                <div class="tile-detail" id="accountDetail">-</div>
            </div>
            <div class="dashboard-tile">
                <div class="tile-icon"><i class="fa fa-list"></i></div>
                <div class="tile-count" id="entryCount">0</div>
                <div class="tile-label">{{ lang._('DNS Entries') }}</div>
                <div class="tile-detail" id="entryDetail">-</div>
            </div>
        </div>

        <!-- Entry Status Summary -->
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-3">
                <div class="panel panel-success">
                    <div class="panel-body text-center">
                        <h3 id="activeCount" style="margin: 0;">0</h3>
                        <small class="text-muted"><i class="fa fa-check"></i> {{ lang._('Active') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-warning">
                    <div class="panel-body text-center">
                        <h3 id="failoverCount" style="margin: 0;">0</h3>
                        <small class="text-muted"><i class="fa fa-exchange"></i> {{ lang._('Failover') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-danger">
                    <div class="panel-body text-center">
                        <h3 id="errorCount" style="margin: 0;">0</h3>
                        <small class="text-muted"><i class="fa fa-exclamation-triangle"></i> {{ lang._('Error') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3 id="pendingCount" style="margin: 0;">0</h3>
                        <small class="text-muted"><i class="fa fa-clock-o"></i> {{ lang._('Pending') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Failover Simulation Section -->
        <div class="sim-section" id="simSection">
            <h4><i class="fa fa-flask"></i> {{ lang._('Gateway Failure Simulation') }}</h4>
            <p class="text-muted">{{ lang._('Test failover behavior by simulating gateway failures. This only affects DNS updates, not actual traffic.') }}</p>

            <div class="sim-gateways" id="simGatewayContainer">
                <em class="text-muted">{{ lang._('Loading gateways...') }}</em>
            </div>

            <button class="btn btn-sm btn-warning" id="clearSimBtn" style="display: none;"><i class="fa fa-times"></i> {{ lang._('Clear All Simulations') }}</button>

            <h5 style="margin-top: 20px;"><i class="fa fa-list-alt"></i> {{ lang._('DNS Entry Status') }}</h5>
            <table class="table table-condensed table-hover entry-status-table" id="overviewEntryTable">
                <thead>
                    <tr>
                        <th style="width:25px;"></th>
                        <th>{{ lang._('Record') }}</th>
                        <th>{{ lang._('Type') }}</th>
                        <th>{{ lang._('Current IP') }}</th>
                        <th>{{ lang._('Active Gateway') }}</th>
                        <th>{{ lang._('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6" class="text-center text-muted">{{ lang._('Loading...') }}</td></tr>
                </tbody>
            </table>
        </div>

        <hr/>
        <button class="btn btn-default" id="updateNowBtn"><i class="fa fa-refresh"></i> {{ lang._('Update Now') }}</button>
    </div>

    <!-- ==================== GATEWAYS TAB ==================== -->
    <div id="gateways" class="tab-pane fade">
        <!-- Failover Settings Section -->
        <div class="panel panel-default" style="margin-bottom: 20px;">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-random"></i> {{ lang._('Failover Settings') }}</h3>
            </div>
            <div class="panel-body">
                {{ partial("layout_partials/base_form", ['fields': failoverForm, 'id': 'frm_failover_settings']) }}
                <button class="btn btn-primary btn-sm" id="saveFailoverBtn"><i class="fa fa-save"></i> {{ lang._('Save Failover Settings') }}</button>
            </div>
        </div>

        <p class="text-muted">{{ lang._('Configure network interfaces/gateways for IP detection. The gateway with lowest priority number is primary.') }}</p>

        <table id="grid-gateways" class="table table-condensed table-hover table-striped" data-editDialog="dialogGateway" data-editAlert="gatewayChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">ID</th>
                    <th data-column-id="enabled" data-type="boolean" data-formatter="rowtoggle" data-width="6em">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="priority" data-type="string" data-width="8em">{{ lang._('Priority') }}</th>
                    <th data-column-id="checkipMethod" data-type="string">{{ lang._('IP Detection') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false" data-width="7em">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr><td></td><td><button data-action="add" type="button" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i></button></td><td colspan="5"></td></tr>
            </tfoot>
        </table>
        <div class="alert alert-info" id="gatewayChangeMessage" style="display: none;">{{ lang._('Changes need to be saved and applied.') }}</div>
        <hr/>
        <button class="btn btn-primary" id="saveGatewaysBtn"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
    </div>

    <!-- ==================== ENTRIES TAB ==================== -->
    <div id="entries" class="tab-pane fade">
        <div class="alert alert-info" style="color: #31708f;">
            <i class="fa fa-cloud-upload"></i> <strong>{{ lang._('Adding entries:') }}</strong> {{ lang._('New entries are created at Hetzner DNS immediately with the current gateway IP.') }}
        </div>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> <strong>{{ lang._('Deleting entries:') }}</strong> {{ lang._('Only removes from OPNsense management. DNS records at Hetzner remain unchanged.') }}
        </div>

        <!-- DynDNS TTL Settings - Inline -->
        <div class="form-inline" style="margin-bottom: 15px; padding: 12px 15px; background: #f5f5f5; border-radius: 4px;">
            <label style="margin-right: 10px;"><i class="fa fa-clock-o"></i> {{ lang._('Default TTL') }}:</label>
            <select class="form-control selectpicker" id="defaultTtlSelect" style="width: 180px;">
                <option value="60">60s (1 min - DynDNS)</option>
                <option value="120">120s (2 min)</option>
                <option value="300" selected>300s (5 min)</option>
                <option value="600">600s (10 min)</option>
                <option value="1800">1800s (30 min)</option>
                <option value="3600">3600s (1 hour)</option>
                <option value="86400">86400s (1 day)</option>
            </select>
            <button class="btn btn-primary btn-sm" id="applyTtlBtn" style="margin-left: 15px;">
                <i class="fa fa-save"></i> {{ lang._('Save & Apply to All') }}
            </button>
            <span class="text-muted" style="margin-left: 15px;">{{ lang._('Updates TTL for all DynDNS records at Hetzner') }}</span>
        </div>

        <!-- Import from Hetzner - Inline -->
        <div class="form-inline" style="margin-bottom: 20px; padding: 12px 15px; background: #f5f5f5; border-radius: 4px;">
            <label style="margin-right: 10px;"><i class="fa fa-download"></i> {{ lang._('Import from Hetzner') }}:</label>
            <select class="form-control selectpicker" id="importAccountSelect" data-live-search="true" style="width: 200px;">
                <option value="">{{ lang._('-- Select Account --') }}</option>
            </select>
            <button class="btn btn-info btn-sm" id="loadZonesBtn" disabled style="margin-left: 15px;">
                <i class="fa fa-cloud-download"></i> {{ lang._('Load Zones') }}
            </button>
            <span class="text-muted" style="margin-left: 15px;">{{ lang._('Import existing A/AAAA records as DynDNS entries') }}</span>
        </div>

        <!-- Batch Operations Toolbar -->
        <div id="batchToolbar" class="well well-sm" style="display: none; margin-bottom: 15px;">
            <span id="batchSelectedCount" class="text-muted" style="margin-right: 15px;"></span>
            <div class="btn-group">
                <button class="btn btn-success btn-sm" id="batchEnableBtn"><i class="fa fa-check"></i> {{ lang._('Enable Selected') }}</button>
                <button class="btn btn-warning btn-sm" id="batchDisableBtn"><i class="fa fa-ban"></i> {{ lang._('Disable Selected') }}</button>
                <button class="btn btn-danger btn-sm" id="batchDeleteBtn"><i class="fa fa-trash"></i> {{ lang._('Delete Selected') }}</button>
            </div>
            <button class="btn btn-default btn-sm" id="batchClearBtn" style="margin-left: 10px;"><i class="fa fa-times"></i> {{ lang._('Clear Selection') }}</button>
        </div>

        <table id="grid-entries" class="table table-condensed table-hover table-striped" data-editDialog="dialogEntry" data-editAlert="entryChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">ID</th>
                    <th data-column-id="select" data-formatter="selectFormatter" data-sortable="false" data-width="3em"><input type="checkbox" id="selectAllEntries" title="{{ lang._('Select All') }}"></th>
                    <th data-column-id="enabled" data-type="boolean" data-formatter="rowtoggle" data-width="5em">{{ lang._('Enabled') }}</th>
                    <th data-column-id="account" data-type="string" data-formatter="accountFormatter">{{ lang._('Account') }}</th>
                    <th data-column-id="zoneName" data-type="string">{{ lang._('Zone') }}</th>
                    <th data-column-id="recordName" data-type="string">{{ lang._('Record') }}</th>
                    <th data-column-id="recordType" data-type="string" data-formatter="recordTypeFormatter" data-width="6em">{{ lang._('Type') }}</th>
                    <th data-column-id="primaryGateway" data-type="string" data-formatter="gatewayFormatter">{{ lang._('Primary IP') }}</th>
                    <th data-column-id="failoverGateway" data-type="string" data-formatter="failoverGatewayFormatter">{{ lang._('Failover IP') }}</th>
                    <th data-column-id="currentIp" data-type="string">{{ lang._('Current IP') }}</th>
                    <th data-column-id="status" data-type="string" data-formatter="statusFormatter" data-width="7em">{{ lang._('Status') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false" data-width="7em">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr><td></td><td></td><td><button data-action="add" type="button" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i></button></td><td colspan="8"></td></tr>
            </tfoot>
        </table>
        <div class="alert alert-info" id="entryChangeMessage" style="display: none;">{{ lang._('Changes need to be saved.') }}</div>
        <hr/>
        <button class="btn btn-primary" id="saveEntriesBtn"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
        <button class="btn btn-default" id="refreshEntriesBtn"><i class="fa fa-refresh"></i> {{ lang._('Refresh Status') }}</button>
        <button class="btn btn-info" id="createDualStackBtn"><i class="fa fa-link"></i> {{ lang._('Create Dual-Stack (A+AAAA)') }}</button>
        <button class="btn btn-danger" id="removeOrphanedBtn"><i class="fa fa-trash"></i> {{ lang._('Remove Orphaned') }}</button>
    </div>

    <!-- ==================== SCHEDULED TAB ==================== -->
    <div id="scheduled" class="tab-pane fade">
        <div class="info-box">
            <p><strong>{{ lang._('When do you need scheduled updates?') }}</strong></p>
            <p>{{ lang._('Normally, DNS updates are triggered automatically by:') }}</p>
            <ul>
                <li><strong>{{ lang._('Gateway Monitoring') }}</strong> - {{ lang._('When OPNsense detects a gateway failure or recovery (via dpinger), DNS records are updated immediately (~1 second response time).') }}</li>
                <li><strong>{{ lang._('IP Changes') }}</strong> - {{ lang._('When an interface IP address changes, DNS records are updated automatically.') }}</li>
            </ul>
            <p><strong>{{ lang._('Scheduled updates are optional') }}</strong> {{ lang._('and useful for:') }}</p>
            <ul>
                <li>{{ lang._('Catching any missed events as a safety net') }}</li>
                <li>{{ lang._('Environments where gateway monitoring is disabled') }}</li>
                <li>{{ lang._('Periodic verification that DNS records are in sync') }}</li>
            </ul>
            <p><em>{{ lang._('For most setups, leaving this disabled is recommended.') }}</em></p>
        </div>

        {{ partial("layout_partials/base_form", ['fields': scheduledForm, 'id': 'frm_scheduled_settings']) }}

        <hr/>
        <button class="btn btn-primary" id="saveScheduledBtn"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
    </div>
</div>
</div><!-- /mainContent -->

<!-- Gateway Dialog -->
{{ partial("layout_partials/base_dialog", ['fields': gatewayForm, 'id': 'dialogGateway', 'label': lang._('Gateway')]) }}

<!-- Entry Dialog -->
{{ partial("layout_partials/base_dialog", ['fields': entryForm, 'id': 'dialogEntry', 'label': lang._('DNS Entry')]) }}

<!-- Dual-Stack Dialog -->
<div class="modal fade" id="dialogDualStack" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-link"></i> {{ lang._('Create Dual-Stack Entry (A + AAAA)') }}</h4>
            </div>
            <div class="modal-body" style="padding: 20px 30px;">
                <div class="alert alert-info">
                    <h5 style="margin-top: 0;"><i class="fa fa-info-circle"></i> {{ lang._('What is Dual-Stack?') }}</h5>
                    <p>{{ lang._('Dual-Stack means your domain is reachable via both IPv4 and IPv6. This creates two linked DNS records:') }}</p>
                    <ul style="margin-bottom: 10px;">
                        <li><strong>A Record</strong> {{ lang._('- Points to your IPv4 address (e.g. 203.0.113.50)') }}</li>
                        <li><strong>AAAA Record</strong> {{ lang._('- Points to your IPv6 address (e.g. 2001:db8::1)') }}</li>
                    </ul>
                    <p style="margin-bottom: 0;"><i class="fa fa-link"></i> {{ lang._('The records are linked: Changes to one (enable/disable/delete) automatically affect the other.') }}</p>
                </div>
                <form id="frmDualStack">
                    <div class="form-group">
                        <label>{{ lang._('Account') }}</label>
                        <select class="form-control selectpicker" id="ds_account" data-live-search="true"></select>
                    </div>
                    <div class="form-group">
                        <label>{{ lang._('Zone') }}</label>
                        <select class="form-control selectpicker" id="ds_zone" data-live-search="true" disabled>
                            <option value="">{{ lang._('-- Select Account First --') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ lang._('Record Name') }}</label>
                        <input type="text" class="form-control" id="ds_recordName" placeholder="@ or www">
                    </div>
                    <div class="form-group">
                        <label>{{ lang._('TTL') }}</label>
                        <select class="form-control selectpicker" id="ds_ttl">
                            <option value="60">60s (1 min - DynDNS)</option>
                            <option value="120">120s (2 min)</option>
                            <option value="300">300s (5 min)</option>
                            <option value="600">600s (10 min)</option>
                            <option value="1800">1800s (30 min)</option>
                            <option value="3600">3600s (1 hour)</option>
                            <option value="86400">86400s (1 day)</option>
                        </select>
                    </div>
                    <hr/>
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fa fa-globe"></i> {{ lang._('IPv4 (A Record)') }}</h5>
                            <div class="form-group">
                                <label>{{ lang._('Primary Gateway') }}</label>
                                <select class="form-control selectpicker" id="ds_ipv4_primary"></select>
                            </div>
                            <div class="form-group">
                                <label>{{ lang._('Failover Gateway') }}</label>
                                <select class="form-control selectpicker" id="ds_ipv4_failover"></select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fa fa-globe"></i> {{ lang._('IPv6 (AAAA Record)') }}</h5>
                            <div class="form-group">
                                <label>{{ lang._('Primary Gateway') }}</label>
                                <select class="form-control selectpicker" id="ds_ipv6_primary"></select>
                            </div>
                            <div class="form-group">
                                <label>{{ lang._('Failover Gateway') }}</label>
                                <select class="form-control selectpicker" id="ds_ipv6_failover"></select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="saveDualStackBtn"><i class="fa fa-save"></i> {{ lang._('Create Dual-Stack Entry') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Records Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-download"></i> {{ lang._('Import DNS Records') }}</h4>
            </div>
            <div class="modal-body" style="padding: 30px; padding-bottom: 0;">
                <!-- Scrollable zones container -->
                <div id="importZonesContainer" style="max-height: 50vh; overflow-y: auto; margin-bottom: 15px;">
                    <div class="text-center" style="padding: 40px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p style="margin-top: 15px;">{{ lang._('Loading zones...') }}</p>
                    </div>
                </div>
                <!-- Gateway section - always visible -->
                <hr style="margin-top: 0;"/>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ lang._('Primary Gateway') }}</label>
                            <select class="form-control selectpicker" id="importPrimaryGw" data-live-search="true" data-container="body"></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ lang._('Failover Gateway') }} <small class="text-muted">({{ lang._('optional') }})</small></label>
                            <select class="form-control selectpicker" id="importFailoverGw" data-live-search="true" data-container="body">
                                <option value="">{{ lang._('-- None --') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span id="importSelectionCount" class="text-muted pull-left" style="line-height: 34px;"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="importBtn" disabled><i class="fa fa-download"></i> {{ lang._('Import Selected') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var gatewaysCache = {};
    var accountsCache = {};
    var defaultTtl = '60'; // Default TTL for DynDNS (loaded from settings)

    // Check if accounts exist and show/hide warning
    function checkAccountsExist() {
        ajaxCall('/api/hclouddns/accounts/searchItem', {}, function(data) {
            if (!data || !data.rows || data.rows.length === 0) {
                $('#noAccountsWarning').show();
                $('#mainContent').hide();
            } else {
                $('#noAccountsWarning').hide();
                $('#mainContent').show();
            }
        });
    }

    // Initial check
    checkAccountsExist();

    // Preload caches - returns promise when both are loaded
    function preloadCaches(callback) {
        var gwDeferred = $.Deferred();
        var accDeferred = $.Deferred();
        ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(data) {
            if (data && data.rows) {
                $.each(data.rows, function(i, gw) { gatewaysCache[gw.uuid] = gw.name; });
            }
            gwDeferred.resolve();
        });
        ajaxCall('/api/hclouddns/accounts/searchItem', {}, function(data) {
            if (data && data.rows) {
                $.each(data.rows, function(i, acc) { accountsCache[acc.uuid] = acc.name; });
            }
            accDeferred.resolve();
        });
        $.when(gwDeferred, accDeferred).done(function() {
            if (callback) callback();
        });
    }

    // ==================== OVERVIEW TAB ====================
    var data_get_map = {
        'frm_scheduled_settings': '/api/hclouddns/settings/get',
        'frm_failover_settings': '/api/hclouddns/settings/get'
    };
    mapDataToFormUI(data_get_map).done(function() {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        loadDashboard();
        loadSimulationStatus();
    });

    function loadDashboard() {
        ajaxCall('/api/hclouddns/settings/get', {}, function(data) {
            if (!data || !data.hclouddns) return;
            var cfg = data.hclouddns;

            // Load default TTL setting
            if (cfg.general && cfg.general.defaultTtl) {
                // Remove underscore prefix if present (e.g. "_60" -> "60")
                defaultTtl = cfg.general.defaultTtl.selected || cfg.general.defaultTtl;
                if (typeof defaultTtl === 'string' && defaultTtl.charAt(0) === '_') {
                    defaultTtl = defaultTtl.substring(1);
                }
                // Set the value in the inline TTL selector
                $('#defaultTtlSelect').val(defaultTtl).selectpicker('refresh');
            }

            // Count gateways
            var gwCount = 0, gwNames = [];
            if (cfg.gateways && cfg.gateways.gateway) {
                for (var u in cfg.gateways.gateway) {
                    gwCount++;
                    gwNames.push(cfg.gateways.gateway[u].name);
                    gatewaysCache[u] = cfg.gateways.gateway[u].name;
                }
            }
            $('#gatewayCount').text(gwCount);
            $('#gatewayDetail').text(gwNames.slice(0, 3).join(', ') + (gwNames.length > 3 ? '...' : '') || 'None configured');

            // Count accounts
            var accCount = 0, accNames = [];
            if (cfg.accounts && cfg.accounts.account) {
                for (var u in cfg.accounts.account) {
                    accCount++;
                    accNames.push(cfg.accounts.account[u].name);
                    accountsCache[u] = cfg.accounts.account[u].name;
                }
            }
            $('#accountCount').text(accCount);
            $('#accountDetail').text(accNames.slice(0, 3).join(', ') + (accNames.length > 3 ? '...' : '') || 'None configured');

            // Count entries
            var entryCount = 0, zones = {};
            if (cfg.entries && cfg.entries.entry) {
                for (var u in cfg.entries.entry) {
                    entryCount++;
                    zones[cfg.entries.entry[u].zoneName] = true;
                }
            }
            $('#entryCount').text(entryCount);
            var zoneList = Object.keys(zones);
            $('#entryDetail').text(zoneList.slice(0, 3).join(', ') + (zoneList.length > 3 ? '...' : '') || 'None configured');
        });
    }

    function loadSimulationStatus() {
        // Load gateway status with simulation info
        ajaxCall('/api/hclouddns/gateways/status', {}, function(data) {
            var $c = $('#simGatewayContainer').empty();
            if (!data || !data.gateways || Object.keys(data.gateways).length === 0) {
                $c.html('<em class="text-muted">No gateways configured. Add gateways in the Gateways tab.</em>');
                return;
            }

            var hasSimulation = false;
            $.each(data.gateways, function(uuid, gw) {
                gatewaysCache[uuid] = gw.name;
                var statusClass = gw.simulated ? 'simulated' : (gw.status === 'up' ? 'up' : 'down');
                if (gw.simulated) hasSimulation = true;

                var statusHtml = '';
                if (gw.simulated) {
                    statusHtml = '<span class="label label-warning">Simulated Down</span>';
                } else if (gw.status === 'up') {
                    statusHtml = '<span class="label label-success">Up</span>';
                } else {
                    statusHtml = '<span class="label label-danger">Down</span>';
                }

                var btnHtml = gw.simulated
                    ? '<button class="btn btn-xs btn-success sim-restore-btn" data-uuid="' + uuid + '" title="Restore"><i class="fa fa-plug"></i></button>'
                    : '<button class="btn btn-xs btn-warning sim-fail-btn" data-uuid="' + uuid + '" title="Simulate Failure"><i class="fa fa-power-off"></i></button>';

                $c.append(
                    '<div class="sim-gateway ' + statusClass + '" data-uuid="' + uuid + '">' +
                        '<div><div class="gw-name">' + gw.name + '</div><div class="gw-ip">' + (gw.ipv4 || '-') + '</div></div>' +
                        '<div class="gw-status">' + statusHtml + ' ' + btnHtml + '</div>' +
                    '</div>'
                );
            });

            if (hasSimulation) {
                $('#simSection').addClass('active');
                $('#clearSimBtn').show();
            } else {
                $('#simSection').removeClass('active');
                $('#clearSimBtn').hide();
            }
        });

        // Load entry status
        loadOverviewEntryStatus();
    }

    function loadOverviewEntryStatus() {
        ajaxCall('/api/hclouddns/entries/liveStatus', {}, function(data) {
            var $tb = $('#overviewEntryTable tbody').empty();

            // Update last refresh time
            $('#lastRefresh').text(new Date().toLocaleTimeString());

            if (!data || !data.entries || !data.entries.length) {
                $tb.html('<tr><td colspan="6" class="text-center text-muted">No entries configured.</td></tr>');
                updateStatusCounts({active: 0, failover: 0, error: 0, pending: 0});
                return;
            }

            if (data.gateways) {
                $.each(data.gateways, function(uuid, gw) { gatewaysCache[uuid] = gw.name; });
            }

            // Count statuses
            var counts = {active: 0, failover: 0, error: 0, pending: 0, orphaned: 0, paused: 0};

            $.each(data.entries, function(i, e) {
                var statusText = e.status || 'pending';
                var statusLower = statusText.toLowerCase();
                var cls = {active: 'success', failover: 'warning', paused: 'default', error: 'danger', orphaned: 'danger'}[statusLower] || 'info';
                var gwName = e.activeGatewayName || gatewaysCache[e.activeGateway] || gatewaysCache[e.primaryGateway] || '-';
                var statusIcon = (statusLower === 'orphaned') ? '<i class="fa fa-unlink"></i> ' : '';

                // Count status
                if (counts.hasOwnProperty(statusLower)) counts[statusLower]++;
                else counts.pending++;

                $tb.append(
                    '<tr>' +
                        '<td>' + (e.enabled === '1' ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-muted"></i>') + '</td>' +
                        '<td><code>' + e.recordName + '.' + e.zoneName + '</code></td>' +
                        '<td><span class="label label-' + (e.recordType === 'A' ? 'primary' : 'info') + '">' + e.recordType + '</span></td>' +
                        '<td>' + (e.currentIp || '-') + '</td>' +
                        '<td>' + gwName + '</td>' +
                        '<td><span class="label label-' + cls + '">' + statusIcon + statusText + '</span></td>' +
                    '</tr>'
                );
            });

            updateStatusCounts(counts);
        });
    }

    function updateStatusCounts(counts) {
        $('#activeCount').text(counts.active || 0);
        $('#failoverCount').text(counts.failover || 0);
        $('#errorCount').text((counts.error || 0) + (counts.orphaned || 0));
        $('#pendingCount').text((counts.pending || 0) + (counts.paused || 0));

        // Update service status badge
        var total = (counts.active || 0) + (counts.failover || 0) + (counts.error || 0) + (counts.orphaned || 0) + (counts.pending || 0) + (counts.paused || 0);
        var $status = $('#serviceStatus');

        if (total === 0) {
            $status.removeClass().addClass('label label-default').text('No Entries');
        } else if ((counts.error || 0) + (counts.orphaned || 0) > 0) {
            $status.removeClass().addClass('label label-danger').text('Errors');
        } else if (counts.failover > 0) {
            $status.removeClass().addClass('label label-warning').text('Failover Active');
        } else if (counts.active > 0) {
            $status.removeClass().addClass('label label-success').text('All OK');
        } else {
            $status.removeClass().addClass('label label-info').text('Pending');
        }

        // Update status summary
        var summary = [];
        if (counts.active > 0) summary.push(counts.active + ' active');
        if (counts.failover > 0) summary.push(counts.failover + ' failover');
        if ((counts.error || 0) + (counts.orphaned || 0) > 0) summary.push((counts.error + counts.orphaned) + ' error');
        $('#statusSummary').text(summary.join(', '));
    }

    // Auto-refresh functionality
    var autoRefreshInterval = null;
    $('#autoRefresh').change(function() {
        if ($(this).is(':checked')) {
            autoRefreshInterval = setInterval(function() {
                loadSimulationStatus();
            }, 30000);
        } else {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
    });

    // Simulation button handlers - use {_:''} to force POST request
    // After simulating, we trigger updateV2 to actually perform the DNS failover
    $(document).on('click', '.sim-fail-btn', function() {
        var uuid = $(this).data('uuid');
        var $btn = $(this).prop('disabled', true);
        ajaxCall('/api/hclouddns/service/simulateDown/' + uuid, {_: ''}, function(data) {
            if (data && data.status === 'ok') {
                // Trigger DNS update to perform failover
                ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function(updateData) {
                    $btn.prop('disabled', false);
                    loadSimulationStatus();
                    var msg = 'Gateway marked as down.';
                    if (updateData && updateData.details && updateData.details.failovers > 0) {
                        msg += ' ' + updateData.details.failovers + ' DNS entries switched to failover gateway.';
                    }
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, title: 'Failover Triggered', message: msg});
                });
            } else {
                $btn.prop('disabled', false);
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Error', message: 'Failed to simulate gateway failure.'});
            }
        });
    });

    $(document).on('click', '.sim-restore-btn', function() {
        var uuid = $(this).data('uuid');
        var $btn = $(this).prop('disabled', true);
        ajaxCall('/api/hclouddns/service/simulateUp/' + uuid, {_: ''}, function(data) {
            if (data && data.status === 'ok') {
                // Trigger DNS update to perform failback
                ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function(updateData) {
                    $btn.prop('disabled', false);
                    loadSimulationStatus();
                    var msg = 'Gateway restored.';
                    if (updateData && updateData.details && updateData.details.failbacks > 0) {
                        msg += ' ' + updateData.details.failbacks + ' DNS entries switched back to primary gateway.';
                    }
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Failback Complete', message: msg});
                });
            } else {
                $btn.prop('disabled', false);
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Error', message: 'Failed to restore gateway.'});
            }
        });
    });

    $('#clearSimBtn').click(function() {
        var $btn = $(this).prop('disabled', true);
        ajaxCall('/api/hclouddns/service/simulateClear', {_: ''}, function() {
            // Trigger DNS update to perform failback for all entries
            ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function(updateData) {
                $btn.prop('disabled', false);
                loadSimulationStatus();
                var msg = 'All simulations cleared.';
                if (updateData && updateData.details && updateData.details.failbacks > 0) {
                    msg += ' ' + updateData.details.failbacks + ' DNS entries switched back.';
                }
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_INFO, title: 'Simulations Cleared', message: msg});
            });
        });
    });

    $('#updateNowBtn').click(function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Update Now');
            loadSimulationStatus();
            if (data && data.status === 'ok') {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Success', message: data.message || 'DNS records updated.'});
            }
        });
    });

    // ==================== ENTRY SETTINGS (TTL) ====================
    // Save & Apply TTL to all entries button
    $('#applyTtlBtn').click(function() {
        var $btn = $(this);
        var selectedTtl = $('#defaultTtlSelect').val();
        BootstrapDialog.confirm({
            title: '{{ lang._("Apply TTL to All Entries") }}',
            message: '{{ lang._("This will update the TTL for all enabled DynDNS entries at Hetzner to") }} <strong>' + selectedTtl + 's</strong>. {{ lang._("Continue?") }}',
            type: BootstrapDialog.TYPE_WARNING,
            btnOKLabel: '{{ lang._("Apply TTL") }}',
            btnCancelLabel: '{{ lang._("Cancel") }}',
            callback: function(result) {
                if (result) {
                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Updating...") }}');
                    // Save the TTL setting, then apply to all entries
                    ajaxCall('/api/hclouddns/settings/set', {hclouddns: {general: {defaultTtl: '_' + selectedTtl}}}, function() {
                        ajaxCall('/api/hclouddns/entries/applyDefaultTtl', {}, function(data) {
                            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> {{ lang._("Save & Apply to All") }}');
                            if (data && data.status === 'ok') {
                                BootstrapDialog.alert({
                                    type: BootstrapDialog.TYPE_SUCCESS,
                                    title: '{{ lang._("Success") }}',
                                    message: data.message
                                });
                            } else if (data && data.status === 'partial') {
                                var msg = data.message;
                                if (data.errors && data.errors.length > 0) {
                                    msg += '<br/><br/><strong>{{ lang._("Errors") }}:</strong><br/>' + data.errors.join('<br/>');
                                }
                                BootstrapDialog.alert({
                                    type: BootstrapDialog.TYPE_WARNING,
                                    title: '{{ lang._("Partial Success") }}',
                                    message: msg
                                });
                            } else {
                                BootstrapDialog.alert({
                                    type: BootstrapDialog.TYPE_DANGER,
                                    title: '{{ lang._("Error") }}',
                                    message: data.message || '{{ lang._("Failed to apply TTL") }}'
                                });
                            }
                        });
                    }, false); // Don't show validation errors popup
                }
            }
        });
    });

    // ==================== SCHEDULED TAB ====================
    $('#saveScheduledBtn').click(function() {
        saveFormToEndpoint('/api/hclouddns/settings/set', 'frm_scheduled_settings', function() {
            ajaxCall('/api/hclouddns/service/reconfigure', {}, function() {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: '{{ lang._("Success") }}', message: '{{ lang._("Scheduled updates settings saved.") }}'});
            });
        }, true);
    });

    // ==================== GATEWAYS TAB ====================
    $('#grid-gateways').UIBootgrid({
        search: '/api/hclouddns/gateways/searchItem',
        get: '/api/hclouddns/gateways/getItem/',
        set: '/api/hclouddns/gateways/setItem/',
        add: '/api/hclouddns/gateways/addItem/',
        del: '/api/hclouddns/gateways/delItem/',
        toggle: '/api/hclouddns/gateways/toggleItem/'
    });

    $('#saveFailoverBtn').click(function() {
        saveFormToEndpoint('/api/hclouddns/settings/set', 'frm_failover_settings', function() {
            ajaxCall('/api/hclouddns/service/reconfigure', {}, function() {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Success', message: 'Failover settings saved.'});
            });
        }, true);
    });

    $('#saveGatewaysBtn').click(function() {
        saveFormToEndpoint('/api/hclouddns/settings/set', 'frm_general_settings', function() {
            $('#grid-gateways').bootgrid('reload');
            preloadCaches();
        }, true);
    });

    // ==================== ENTRIES TAB ====================
    var entryZonesCache = {}; // Cache zones per account
    var isNewEntry = false;

    // Custom formatters for entries grid (must include 'commands' since options.formatters overwrites defaults)
    // Use data-action attributes like the standard UIBootgrid Add button does
    var selectedEntries = {};

    var entryFormatters = {
        selectFormatter: function(col, row) {
            var checked = selectedEntries[row.uuid] ? ' checked' : '';
            return '<input type="checkbox" class="entry-select" data-uuid="' + row.uuid + '"' + checked + '>';
        },
        commands: function(col, row) {
            var btns = '<button type="button" class="btn btn-xs btn-default" data-action="edit" data-row-id="' + row.uuid + '" title="{{ lang._('Edit') }}"><span class="fa fa-fw fa-pencil"></span></button> ';
            btns += '<button type="button" class="btn btn-xs btn-default" data-action="copy" data-row-id="' + row.uuid + '" title="{{ lang._('Clone') }}"><span class="fa fa-fw fa-clone"></span></button> ';
            btns += '<button type="button" class="btn btn-xs btn-default" data-action="delete" data-row-id="' + row.uuid + '" title="{{ lang._('Delete') }}"><span class="fa fa-fw fa-trash-o"></span></button>';
            return btns;
        },
        accountFormatter: function(col, row) {
            var accUuid = row.account;
            var accDisplayName = row['%account'];
            if (!accUuid) return '<span class="text-muted">-</span>';
            if (accDisplayName) {
                return '<span title="' + accUuid + '">' + accDisplayName + '</span>';
            }
            var accName = accountsCache[accUuid];
            if (accName) {
                return '<span title="' + accUuid + '">' + accName + '</span>';
            }
            return '<span class="text-danger" title="Account deleted: ' + accUuid + '"><i class="fa fa-exclamation-triangle"></i> Account Missing</span>';
        },
        statusFormatter: function(col, row) {
            var rawStatus = row.status || 'pending';
            var s = rawStatus.toLowerCase();
            var displayMap = {active: 'Active', failover: 'Failover', paused: 'Paused', error: 'Error', pending: 'Pending', orphaned: 'Orphaned'};
            var displayStatus = displayMap[s] || rawStatus;
            var cls = {active: 'success', failover: 'warning', paused: 'default', error: 'danger', pending: 'info', orphaned: 'danger'}[s] || 'default';
            var icon = (s === 'orphaned') ? '<i class="fa fa-unlink"></i> ' : '';
            var title = (s === 'orphaned') ? 'Record not found at Hetzner - re-enable to recreate' : '';
            return '<span class="label label-' + cls + '" title="' + title + '">' + icon + displayStatus + '</span>';
        },
        gatewayFormatter: function(col, row) {
            var html = '<select class="form-control gw-select-primary" data-uuid="' + row.uuid + '" style="width:auto;min-width:110px;padding:3px 6px;height:30px;font-size:13px;">';
            html += '<option value="">-</option>';
            for (var uuid in gatewaysCache) {
                var selected = (uuid === row.primaryGateway) ? ' selected' : '';
                html += '<option value="' + uuid + '"' + selected + '>' + gatewaysCache[uuid] + '</option>';
            }
            html += '</select>';
            return html;
        },
        failoverGatewayFormatter: function(col, row) {
            var html = '<select class="form-control gw-select-failover" data-uuid="' + row.uuid + '" data-primary="' + row.primaryGateway + '" style="width:auto;min-width:110px;padding:3px 6px;height:30px;font-size:13px;">';
            html += '<option value="">-</option>';
            for (var uuid in gatewaysCache) {
                var selected = (uuid === row.failoverGateway) ? ' selected' : '';
                html += '<option value="' + uuid + '"' + selected + '>' + gatewaysCache[uuid] + '</option>';
            }
            html += '</select>';
            return html;
        },
        recordTypeFormatter: function(col, row) {
            var type = row.recordType || 'A';
            var cls = (type === 'A') ? 'label-primary' : 'label-info';
            var linked = row.linkedEntry ? ' <i class="fa fa-link text-success" title="Dual-Stack: Linked to ' + (type === 'A' ? 'AAAA' : 'A') + ' record"></i>' : '';
            return '<span class="label ' + cls + '">' + type + '</span>' + linked;
        }
    };

    $('#grid-entries').UIBootgrid({
        search: '/api/hclouddns/entries/searchItem',
        get: '/api/hclouddns/entries/getItem/',
        set: '/api/hclouddns/entries/setItem/',
        add: '/api/hclouddns/entries/addItem/',
        del: '/api/hclouddns/entries/delItem/',
        toggle: '/api/hclouddns/entries/toggleItem/',
        options: {
            formatters: entryFormatters
        }
    });

    // Manual click handlers for command buttons (needed because custom formatters bypass UIBootgrid's default binding)
    var currentEditUuid = null;

    // Helper to get selected key from OPNsense API response object
    function getSelectedKey(obj) {
        if (!obj || typeof obj !== 'object') return '';
        for (var key in obj) {
            if (obj[key] && obj[key].selected === 1) return key;
        }
        return '';
    }

    // Populate dialog from API response - manual field setting
    function populateEntryDialog(data, isClone) {
        var entry = data.entry;
        var isEditMode = !isClone && currentEditUuid;

        // Store data for use after modal is shown
        window._pendingEntryData = {
            entry: entry,
            isClone: isClone,
            isEditMode: isEditMode
        };
    }

    // Apply entry data to dialog fields (called after modal is visible)
    function applyEntryDataToDialog() {
        var pending = window._pendingEntryData;
        if (!pending) return;

        var entry = pending.entry;
        var isClone = pending.isClone;
        var isEditMode = pending.isEditMode;

        // Clear pending data
        window._pendingEntryData = null;

        // Helper to get selected key from API response
        function getSelected(obj) {
            if (!obj || typeof obj !== 'object') return obj || '';
            for (var k in obj) {
                if (obj[k] && obj[k].selected === 1) return k;
            }
            return '';
        }

        // Get all values
        var accKey = getSelected(entry.account);
        var typeKey = getSelected(entry.recordType);
        var primaryKey = getSelected(entry.primaryGateway);
        var failoverKey = getSelected(entry.failoverGateway);
        var statusKey = getSelected(entry.status);

        // Set text fields directly
        $('#entry\\.recordName').val(isClone ? '' : (entry.recordName || ''));
        $('#entry\\.zoneName').val(entry.zoneName || '');
        $('#entry\\.ttl').val(entry.ttl || '300');

        // Set checkbox
        var isEnabled = entry.enabled === '1' || entry.enabled === 1;
        $('#entry\\.enabled').prop('checked', isEnabled);

        // Set dropdowns - find by ID and set value
        var $account = $('#entry\\.account');
        var $recordType = $('#entry\\.recordType');
        var $primaryGw = $('#entry\\.primaryGateway');
        var $failoverGw = $('#entry\\.failoverGateway');
        var $zoneId = $('#entry\\.zoneId');

        // Set dropdown values
        if (accKey) {
            $account.val(accKey).trigger('change');
        }
        if (typeKey) {
            $recordType.val(typeKey);
        }
        if (primaryKey) {
            $primaryGw.val(primaryKey);
        }
        if (failoverKey) {
            $failoverGw.val(failoverKey);
        }

        // Refresh all selectpickers
        $('.selectpicker').selectpicker('refresh');

        // Disable fields in edit mode
        if (isEditMode) {
            $account.prop('disabled', true).selectpicker('refresh');
            $zoneId.prop('disabled', true).selectpicker('refresh');
        }

        // Load zones for the account
        if (accKey) {
            var zoneIdVal = entry.zoneId || '';
            var zoneNameVal = entry.zoneName || '';

            if (entryZonesCache[accKey]) {
                setZoneDropdownValue($zoneId, entryZonesCache[accKey], zoneIdVal, zoneNameVal, isEditMode);
            } else {
                ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: accKey}, function(zoneData) {
                    if (zoneData && zoneData.status === 'ok' && zoneData.zones) {
                        entryZonesCache[accKey] = zoneData.zones;
                        setZoneDropdownValue($zoneId, zoneData.zones, zoneIdVal, zoneNameVal, isEditMode);
                    }
                });
            }
        }

        // Update info fields (Current IP and Status)
        var currentIpVal = entry.currentIp || '-';
        var statusDisplay = {pending: 'Pending', active: 'Active', failover: 'Failover', error: 'Error', orphaned: 'Orphaned', paused: 'Paused'}[statusKey] || statusKey || '-';

        // Find the info field rows by their labels and update
        $('#dialogEntry').find('tr').each(function() {
            var $row = $(this);
            var $label = $row.find('td:first label');
            if ($label.length) {
                var labelText = $label.text().trim();
                if (labelText === 'Current IP') {
                    $row.find('td:last').html(currentIpVal);
                } else if (labelText === 'Status') {
                    $row.find('td:last').html(statusDisplay);
                }
            }
        });
    }

    // Helper to set zone dropdown value
    function setZoneDropdownValue($zone, zones, zoneIdVal, zoneNameVal, isDisabled) {
        $zone.empty().append('<option value="">-- Select zone --</option>');
        $.each(zones, function(i, zone) {
            $zone.append('<option value="' + zone.id + '" data-name="' + zone.name + '">' + zone.name + '</option>');
        });
        $zone.val(zoneIdVal);
        $zone.prop('disabled', isDisabled);
        $zone.selectpicker('refresh');
    }

    // Click handlers using data-action selectors (same pattern as Add button)
    $(document).on('click', '#grid-entries [data-action="edit"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var uuid = $(this).data('row-id');
        if (!uuid) return;

        currentEditUuid = uuid;
        isNewEntry = false;

        // Fetch entry data and populate dialog manually
        $.ajax({
            url: '/api/hclouddns/entries/getItem/' + uuid,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.entry) {
                    fillEntryDialog(data.entry, false);
                    $('#dialogEntry').modal('show');
                }
            }
        });
    });

    $(document).on('click', '#grid-entries [data-action="copy"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var uuid = $(this).data('row-id');
        if (!uuid) return;

        currentEditUuid = null;
        isNewEntry = true;

        // Fetch entry data and populate dialog manually
        $.ajax({
            url: '/api/hclouddns/entries/getItem/' + uuid,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.entry) {
                    fillEntryDialog(data.entry, true);
                    $('#dialogEntry').modal('show');
                }
            }
        });
    });

    // Fill entry dialog with data - using attribute selectors to avoid jQuery ID escaping issues
    function fillEntryDialog(entry, isClone) {
        // Helper to populate dropdown from OPNsense API response and select value
        function fillDropdown(fieldName, optionsObj) {
            var $el = $('[id="entry.' + fieldName + '"]');
            if (!$el.length || !optionsObj || typeof optionsObj !== 'object') return;

            // Clear and rebuild options
            $el.empty();

            var selectedKey = '';
            for (var key in optionsObj) {
                if (optionsObj.hasOwnProperty(key)) {
                    var opt = optionsObj[key];
                    var label = (typeof opt === 'object' && opt.value) ? opt.value : opt;
                    var isSelected = (typeof opt === 'object' && opt.selected === 1);
                    if (isSelected) selectedKey = key;

                    // Add option - use empty string display for blank values
                    var displayLabel = label || (key === '' ? '-- None --' : key);
                    $el.append($('<option>', { value: key, text: displayLabel }));
                }
            }

            // Set selected value
            $el.val(selectedKey);

            // Refresh selectpicker
            if ($el.hasClass('selectpicker')) {
                $el.selectpicker('refresh');
            }
        }

        // Helper to set text field value
        function setField(fieldName, value) {
            var $el = $('[id="entry.' + fieldName + '"]');
            if ($el.length) {
                $el.val(value);
            }
        }

        // Helper to set checkbox
        function setCheckbox(fieldName, checked) {
            var $el = $('[id="entry.' + fieldName + '"]');
            if ($el.length) {
                $el.prop('checked', checked);
            }
        }

        // Set checkbox
        setCheckbox('enabled', entry.enabled === '1' || entry.enabled === 1);

        // Set text fields
        setField('recordName', isClone ? '' : (entry.recordName || ''));
        setField('zoneName', entry.zoneName || '');
        setField('ttl', entry.ttl || '300');

        // Populate and select dropdowns
        fillDropdown('account', entry.account);
        fillDropdown('recordType', entry.recordType);
        fillDropdown('primaryGateway', entry.primaryGateway);
        fillDropdown('failoverGateway', entry.failoverGateway);

        // Zone dropdown - populate from API zones
        var accKey = '';
        if (entry.account && typeof entry.account === 'object') {
            for (var k in entry.account) {
                if (entry.account[k] && entry.account[k].selected === 1) {
                    accKey = k;
                    break;
                }
            }
        }

        // Load and populate zone dropdown
        if (accKey) {
            var $zone = $('[id="entry.zoneId"]');
            $zone.empty().append($('<option>', { value: '', text: 'Loading zones...' }));
            if ($zone.hasClass('selectpicker')) $zone.selectpicker('refresh');

            var zoneIdVal = entry.zoneId || '';
            var zoneNameVal = entry.zoneName || '';

            if (entryZonesCache[accKey]) {
                fillZoneDropdown($zone, entryZonesCache[accKey], zoneIdVal);
            } else {
                ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: accKey}, function(zoneData) {
                    if (zoneData && zoneData.status === 'ok' && zoneData.zones) {
                        entryZonesCache[accKey] = zoneData.zones;
                        fillZoneDropdown($zone, zoneData.zones, zoneIdVal);
                    } else {
                        // Fallback - just show current zone
                        $zone.empty().append($('<option>', { value: zoneIdVal, text: zoneNameVal || zoneIdVal }));
                        $zone.val(zoneIdVal);
                        if ($zone.hasClass('selectpicker')) $zone.selectpicker('refresh');
                    }
                });
            }
        }

        // Refresh all selectpickers
        $('#dialogEntry .selectpicker').selectpicker('refresh');
    }

    // Helper to fill zone dropdown
    function fillZoneDropdown($zone, zones, selectedZoneId) {
        $zone.empty().append($('<option>', { value: '', text: '-- Select zone --' }));
        $.each(zones, function(i, zone) {
            $zone.append($('<option>', { value: zone.id, text: zone.name, 'data-name': zone.name }));
        });
        $zone.val(selectedZoneId);
        if ($zone.hasClass('selectpicker')) $zone.selectpicker('refresh');
    }

    $(document).on('click', '#grid-entries [data-action="delete"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var uuid = $(this).data('row-id');
        if (!uuid) return;

        BootstrapDialog.confirm({
            title: '{{ lang._('Delete Entry') }}',
            message: '{{ lang._('Are you sure you want to delete this DNS entry? The record at Hetzner will NOT be deleted.') }}',
            type: BootstrapDialog.TYPE_DANGER,
            btnOKLabel: '{{ lang._('Delete') }}',
            btnOKClass: 'btn-danger',
            callback: function(result) {
                if (result) {
                    ajaxCall('/api/hclouddns/entries/delItem/' + uuid, {}, function(data) {
                        $('#grid-entries').bootgrid('reload');
                    });
                }
            }
        });
    });

    // Track if we're adding a new entry - use dialog event instead of click interception
    $('#grid-entries').on('loaded.rs.jquery.bootgrid', function() {
        // Track add button clicks
        $(this).find('[data-action="add"]').on('click', function() {
            isNewEntry = true;
        });
    });

    // Dialog open handler is defined after saveNewEntry function below

    // Helper to find form fields in dialog by exact ID
    function findDialogField(fieldName) {
        // OPNsense uses entry.fieldName as ID - need to escape the dot for jQuery
        var exactId = 'entry.' + fieldName;
        var $el = $('#dialogEntry').find('#' + exactId.replace(/\./g, '\\.'));
        if ($el.length === 0) {
            // Fallback: try with underscore
            $el = $('#dialogEntry').find('#entry_' + fieldName);
        }
        return $el;
    }

    // Load zones when account changes in entry dialog
    // Use exact ID with escaped dot
    $(document).on('change', '#entry\\.account', function() {
        var accountUuid = $(this).val();
        var $zoneSelect = findDialogField('zoneId');

        if (!$zoneSelect.length) {
            return;
        }

        if (!accountUuid) {
            $zoneSelect.empty().append('<option value="">-- Select account first --</option>');
            $zoneSelect.selectpicker('refresh');
            return;
        }

        $zoneSelect.empty().append('<option value="">Loading zones...</option>');
        $zoneSelect.selectpicker('refresh');

        // Check cache first
        if (entryZonesCache[accountUuid]) {
            populateZoneDropdown($zoneSelect, entryZonesCache[accountUuid]);
            return;
        }

        ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: accountUuid}, function(data) {
            if (data && data.status === 'ok' && data.zones) {
                entryZonesCache[accountUuid] = data.zones;
                populateZoneDropdown($zoneSelect, data.zones);
            } else {
                $zoneSelect.empty().append('<option value="">Failed to load zones</option>');
                $zoneSelect.selectpicker('refresh');
            }
        });
    });

    function populateZoneDropdown($select, zones) {
        $select.empty().append('<option value="">-- Select zone --</option>');
        $.each(zones, function(i, zone) {
            $select.append('<option value="' + zone.id + '" data-name="' + zone.name + '">' + zone.name + '</option>');
        });
        // Refresh selectpicker to show new options
        $select.selectpicker('refresh');
    }

    // Set zoneName when zone is selected
    $(document).on('change', '#entry\\.zoneId', function() {
        var $selected = $(this).find(':selected');
        var zoneName = $selected.data('name') || '';
        findDialogField('zoneName').val(zoneName);
    });

    // Custom save handler for new entries - replaces UIBootgrid's handler
    function saveNewEntry($btn) {
        // Gather form data using helper
        var accountUuid = findDialogField('account').val();
        var zoneId = findDialogField('zoneId').val();
        var zoneName = findDialogField('zoneName').val();
        var recordName = findDialogField('recordName').val();
        var recordType = findDialogField('recordType').val() || 'A';
        var primaryGateway = findDialogField('primaryGateway').val();
        var ttl = findDialogField('ttl').val() || '300';

        // Validate
        if (!accountUuid || !zoneId || !recordName || !primaryGateway) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please fill in Account, Zone, Record Name, and Primary Gateway.'});
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

        // First get the gateway IP
        ajaxCall('/api/hclouddns/gateways/status', {}, function(gwData) {
            var gwIp = '';
            if (gwData && gwData.gateways && gwData.gateways[primaryGateway]) {
                var gw = gwData.gateways[primaryGateway];
                gwIp = (recordType === 'AAAA') ? gw.ipv6 : gw.ipv4;
            }

            if (!gwIp) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Gateway Error', message: 'Could not get IP from selected gateway. Is the gateway online?'});
                return;
            }

            // Create record at Hetzner
            ajaxCall('/api/hclouddns/hetzner/createRecord', {
                account_uuid: accountUuid,
                zone_id: zoneId,
                record_name: recordName,
                record_type: recordType,
                value: gwIp,
                ttl: ttl
            }, function(createData) {
                if (createData && createData.status === 'ok') {
                    // Record created at Hetzner, now save locally
                    var entryData = {
                        entry: {
                            enabled: '1',
                            account: accountUuid,
                            zoneId: zoneId,
                            zoneName: zoneName,
                            recordName: recordName,
                            recordType: recordType,
                            primaryGateway: primaryGateway,
                            failoverGateway: findDialogField('failoverGateway').val() || '',
                            ttl: ttl,
                            currentIp: gwIp,
                            status: 'active'
                        }
                    };

                    ajaxCall('/api/hclouddns/entries/addItem', entryData, function(addData) {
                        $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');

                        if (addData && addData.uuid) {
                            // Save config
                            ajaxCall('/api/hclouddns/settings/set', {}, function() {
                                $('#dialogEntry').modal('hide');
                                $('#grid-entries').bootgrid('reload');
                                BootstrapDialog.alert({
                                    type: BootstrapDialog.TYPE_SUCCESS,
                                    title: 'DNS Record Created',
                                    message: 'Record ' + recordName + '.' + zoneName + ' (' + recordType + ') created at Hetzner with IP ' + gwIp
                                });
                            });
                        } else {
                            var errMsg = (addData && addData.validations) ? Object.values(addData.validations).join(', ') : 'Failed to save entry locally.';
                            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Record created at Hetzner but local save failed: ' + errMsg});
                        }
                    });
                } else {
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                    var errMsg = (createData && createData.message) ? createData.message : 'Failed to create record at Hetzner.';
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Hetzner API Error', message: errMsg});
                }
            });
        });
    }

    // Save handler for editing existing entries
    function saveEditEntry($btn, uuid) {
        var accountUuid = findDialogField('account').val();
        var zoneId = findDialogField('zoneId').val();
        var zoneName = findDialogField('zoneName').val();
        var recordName = findDialogField('recordName').val();
        var recordType = findDialogField('recordType').val() || 'A';
        var primaryGateway = findDialogField('primaryGateway').val();
        var failoverGateway = findDialogField('failoverGateway').val() || '';
        var ttl = findDialogField('ttl').val() || '300';
        var enabled = findDialogField('enabled').is(':checked') ? '1' : '0';

        if (!accountUuid || !zoneId || !recordName || !primaryGateway) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please fill in Account, Zone, Record Name, and Primary Gateway.'});
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        var entryData = {
            entry: {
                enabled: enabled,
                account: accountUuid,
                zoneId: zoneId,
                zoneName: zoneName,
                recordName: recordName,
                recordType: recordType,
                primaryGateway: primaryGateway,
                failoverGateway: failoverGateway,
                ttl: ttl
            }
        };

        ajaxCall('/api/hclouddns/entries/setItem/' + uuid, entryData, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');

            if (data && data.result === 'saved') {
                // Trigger DNS update to sync with Hetzner
                ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function() {
                    $('#dialogEntry').modal('hide');
                    $('#grid-entries').bootgrid('reload');
                    BootstrapDialog.alert({
                        type: BootstrapDialog.TYPE_SUCCESS,
                        title: 'Entry Updated',
                        message: 'DNS entry saved and synchronized with Hetzner.'
                    });
                });
            } else {
                var errMsg = (data && data.validations) ? Object.values(data.validations).join(', ') : 'Failed to save entry.';
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Save Error', message: errMsg});
            }
        });
    }

    // When dialog opens, detect new vs edit and configure accordingly
    $('#dialogEntry').on('shown.bs.modal', function() {
        var $dialog = $(this);
        var $saveBtn = $dialog.find('.btn-primary[id$="_save"]');

        // Use currentEditUuid to determine Edit vs Add/Clone
        if (currentEditUuid) {
            // EDIT mode - disable Account and Zone, show status info
            $('#entry\\.account').prop('disabled', true).selectpicker('refresh');
            $('#entry\\.zoneId').prop('disabled', true).selectpicker('refresh');

            var editUuid = currentEditUuid; // Capture for closure
            $saveBtn.off('click').on('click', function(e) {
                e.preventDefault();
                saveEditEntry($(this), editUuid);
            });
        } else {
            // ADD or CLONE mode - enable fields, use addItem (creates at Hetzner)
            $('#entry\\.account').prop('disabled', false).selectpicker('refresh');
            $('#entry\\.zoneId').prop('disabled', false).selectpicker('refresh');

            // Set default TTL for new entries (from global settings)
            if (isNewEntry && defaultTtl) {
                $('#entry\\.ttl').val('_' + defaultTtl).selectpicker('refresh');
            }

            $saveBtn.off('click').on('click', function(e) {
                e.preventDefault();
                saveNewEntry($(this));
            });
        }
    });

    // When dialog closes, reset the flags
    $('#dialogEntry').on('hidden.bs.modal', function() {
        isNewEntry = false;
        currentEditUuid = null;
    });

    // Helper function to trigger DNS update and show result
    function triggerDnsUpdate($element, successCallback) {
        ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function(data) {
            if (successCallback) successCallback();
            if (data && data.status === 'ok' && data.details) {
                var d = data.details;
                if (d.updated > 0) {
                    var msg = d.updated + ' DNS record(s) updated at Hetzner.';
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'DNS Updated', message: msg});
                }
            }
            $('#grid-entries').bootgrid('reload');
        });
    }

    $(document).on('change', '.gw-select-primary', function() {
        var $sel = $(this);
        var uuid = $sel.data('uuid');
        var newGw = $sel.val();
        var $failover = $sel.closest('tr').find('.gw-select-failover');
        var entryData = $failover.val() === newGw && newGw !== ''
            ? {primaryGateway: newGw, failoverGateway: ''}
            : {primaryGateway: newGw};

        if ($failover.val() === newGw && newGw !== '') {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Primary and failover gateway cannot be the same. Clearing failover.'});
            $failover.val('');
        }

        $sel.prop('disabled', true);
        ajaxCall('/api/hclouddns/entries/setItem/' + uuid, {entry: entryData}, function(data) {
            if (data && data.status !== 'error') {
                $sel.addClass('bg-success');
                // Trigger DNS update to apply the change at Hetzner
                triggerDnsUpdate($sel, function() {
                    $sel.prop('disabled', false).removeClass('bg-success');
                });
            } else {
                $sel.prop('disabled', false);
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Error', message: 'Failed to save entry.'});
            }
        });
    });

    $(document).on('change', '.gw-select-failover', function() {
        var $sel = $(this);
        var uuid = $sel.data('uuid');
        var newGw = $sel.val();
        var $primary = $sel.closest('tr').find('.gw-select-primary');
        if ($primary.val() === newGw && newGw !== '') {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Failover gateway cannot be the same as primary.'});
            $sel.val($sel.data('previous') || '');
            return;
        }
        $sel.prop('disabled', true);
        ajaxCall('/api/hclouddns/entries/setItem/' + uuid, {entry: {failoverGateway: newGw}}, function(data) {
            if (data && data.status !== 'error') {
                $sel.data('previous', newGw);
                $sel.addClass('bg-success');
                // No DNS update needed for failover change (only affects future failovers)
                setTimeout(function() {
                    $sel.prop('disabled', false).removeClass('bg-success');
                }, 500);
            } else {
                $sel.prop('disabled', false);
            }
        });
    });

    $('#saveEntriesBtn').click(function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        // Trigger DNS update to sync all entries with Hetzner
        ajaxCall('/api/hclouddns/service/updateV2', {_: ''}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
            $('#grid-entries').bootgrid('reload');
            if (data && data.status === 'ok') {
                var msg = 'Entries synchronized with Hetzner.';
                if (data.details && data.details.updated > 0) {
                    msg = data.details.updated + ' DNS record(s) updated.';
                }
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Success', message: msg});
            }
        });
    });

    $('#refreshEntriesBtn').click(function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Syncing...');
        ajaxCall('/api/hclouddns/entries/refreshStatus', {}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Refresh Status');
            $('#grid-entries').bootgrid('reload');
            if (data && data.orphanedCount > 0) {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_WARNING,
                    title: 'Orphaned Entries Found',
                    message: data.orphanedCount + ' entries were not found at Hetzner and have been marked as orphaned. ' +
                             'Re-enable them to recreate the DNS records.'
                });
            } else if (data && data.syncedCount > 0) {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_SUCCESS,
                    title: 'Status Refreshed',
                    message: data.syncedCount + ' entries synchronized with Hetzner.'
                });
            }
        });
    });

    // ==================== DUAL-STACK (A+AAAA) ====================
    var dsZonesCache = {};

    function loadDualStackGateways() {
        var $ipv4p = $('#ds_ipv4_primary').empty().append('<option value="">-- Select --</option>');
        var $ipv4f = $('#ds_ipv4_failover').empty().append('<option value="">None</option>');
        var $ipv6p = $('#ds_ipv6_primary').empty().append('<option value="">-- Select --</option>');
        var $ipv6f = $('#ds_ipv6_failover').empty().append('<option value="">None</option>');
        for (var uuid in gatewaysCache) {
            $ipv4p.append('<option value="' + uuid + '">' + gatewaysCache[uuid] + '</option>');
            $ipv4f.append('<option value="' + uuid + '">' + gatewaysCache[uuid] + '</option>');
            $ipv6p.append('<option value="' + uuid + '">' + gatewaysCache[uuid] + '</option>');
            $ipv6f.append('<option value="' + uuid + '">' + gatewaysCache[uuid] + '</option>');
        }
        $('#dialogDualStack .selectpicker').selectpicker('refresh');
    }

    function loadDualStackAccounts() {
        var $acc = $('#ds_account').empty().append('<option value="">-- Select Account --</option>');
        ajaxCall('/api/hclouddns/accounts/searchItem', {}, function(data) {
            if (data && data.rows) {
                $.each(data.rows, function(i, acc) {
                    if (acc.enabled === '1') {
                        $acc.append('<option value="' + acc.uuid + '">' + acc.name + '</option>');
                    }
                });
            }
            $('#ds_account').selectpicker('refresh');
        });
    }

    $('#ds_account').change(function() {
        var accountUuid = $(this).val();
        var $zone = $('#ds_zone').empty().prop('disabled', true);
        $zone.append('<option value="">-- Loading Zones --</option>');
        $zone.selectpicker('refresh');

        if (!accountUuid) {
            $zone.empty().append('<option value="">-- Select Account First --</option>').selectpicker('refresh');
            return;
        }

        ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: accountUuid}, function(data) {
            $zone.empty().append('<option value="">-- Select Zone --</option>');
            dsZonesCache = {};
            if (data && data.status === 'ok' && data.zones) {
                $.each(data.zones, function(i, z) {
                    dsZonesCache[z.id] = z.name;
                    $zone.append('<option value="' + z.id + '" data-name="' + z.name + '">' + z.name + '</option>');
                });
            }
            $zone.prop('disabled', false).selectpicker('refresh');
        });
    });

    $('#createDualStackBtn').click(function() {
        loadDualStackAccounts();
        loadDualStackGateways();
        $('#ds_zone').empty().append('<option value="">-- Select Account First --</option>').prop('disabled', true).selectpicker('refresh');
        $('#ds_recordName').val('');
        $('#ds_ttl').val(defaultTtl).selectpicker('refresh');
        $('#dialogDualStack').modal('show');
    });

    $('#saveDualStackBtn').click(function() {
        var account = $('#ds_account').val();
        var zoneId = $('#ds_zone').val();
        var zoneName = dsZonesCache[zoneId] || '';
        var recordName = $('#ds_recordName').val().trim();
        var ttl = $('#ds_ttl').val();
        var ipv4Primary = $('#ds_ipv4_primary').val();
        var ipv4Failover = $('#ds_ipv4_failover').val();
        var ipv6Primary = $('#ds_ipv6_primary').val();
        var ipv6Failover = $('#ds_ipv6_failover').val();

        if (!account) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please select an account.'}); return; }
        if (!zoneId) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please select a zone.'}); return; }
        if (!recordName) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please enter a record name.'}); return; }
        if (!ipv4Primary) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please select IPv4 primary gateway.'}); return; }
        if (!ipv6Primary) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please select IPv6 primary gateway.'}); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

        ajaxCall('/api/hclouddns/entries/createDualStack', {
            entry: {
                account: account,
                zoneId: zoneId,
                zoneName: zoneName,
                recordName: recordName,
                ttl: ttl,
                primaryGateway: ipv4Primary,
                failoverGateway: ipv4Failover,
                ipv6Gateway: ipv6Primary,
                ipv6FailoverGateway: ipv6Failover
            }
        }, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Create Dual-Stack Entry');
            if (data && data.status === 'ok') {
                $('#dialogDualStack').modal('hide');
                $('#grid-entries').bootgrid('reload');
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_SUCCESS,
                    title: 'Dual-Stack Created',
                    message: 'Linked A and AAAA records created for ' + recordName + '.' + zoneName
                });
            } else {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: 'Error',
                    message: data.message || 'Failed to create dual-stack entry.'
                });
            }
        });
    });

    // ==================== BATCH OPERATIONS ====================
    function updateBatchToolbar() {
        var count = Object.keys(selectedEntries).length;
        if (count > 0) {
            $('#batchSelectedCount').text(count + ' entry/entries selected');
            $('#batchToolbar').slideDown();
        } else {
            $('#batchToolbar').slideUp();
        }
    }

    // Handle individual checkbox selection
    $(document).on('change', '.entry-select', function() {
        var uuid = $(this).data('uuid');
        if ($(this).is(':checked')) {
            selectedEntries[uuid] = true;
        } else {
            delete selectedEntries[uuid];
        }
        updateBatchToolbar();

        // Update "select all" checkbox state
        var total = $('.entry-select').length;
        var checked = $('.entry-select:checked').length;
        $('#selectAllEntries').prop('checked', checked === total).prop('indeterminate', checked > 0 && checked < total);
    });

    // Handle "select all" checkbox
    $(document).on('change', '#selectAllEntries', function() {
        var isChecked = $(this).is(':checked');
        $('.entry-select').each(function() {
            $(this).prop('checked', isChecked);
            var uuid = $(this).data('uuid');
            if (isChecked) {
                selectedEntries[uuid] = true;
            } else {
                delete selectedEntries[uuid];
            }
        });
        updateBatchToolbar();
    });

    // Clear selection button
    $('#batchClearBtn').click(function() {
        selectedEntries = {};
        $('.entry-select').prop('checked', false);
        $('#selectAllEntries').prop('checked', false).prop('indeterminate', false);
        updateBatchToolbar();
    });

    // Batch Enable
    $('#batchEnableBtn').click(function() {
        var uuids = Object.keys(selectedEntries);
        if (uuids.length === 0) return;

        var $btn = $(this).prop('disabled', true);
        var completed = 0;

        uuids.forEach(function(uuid) {
            ajaxCall('/api/hclouddns/entries/toggleItem/' + uuid + '/1', {}, function() {
                completed++;
                if (completed === uuids.length) {
                    $btn.prop('disabled', false);
                    selectedEntries = {};
                    $('#grid-entries').bootgrid('reload');
                    updateBatchToolbar();
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: uuids.length + ' entries enabled.'});
                }
            });
        });
    });

    // Batch Disable
    $('#batchDisableBtn').click(function() {
        var uuids = Object.keys(selectedEntries);
        if (uuids.length === 0) return;

        var $btn = $(this).prop('disabled', true);
        var completed = 0;

        uuids.forEach(function(uuid) {
            ajaxCall('/api/hclouddns/entries/toggleItem/' + uuid + '/0', {}, function() {
                completed++;
                if (completed === uuids.length) {
                    $btn.prop('disabled', false);
                    selectedEntries = {};
                    $('#grid-entries').bootgrid('reload');
                    updateBatchToolbar();
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: uuids.length + ' entries disabled.'});
                }
            });
        });
    });

    // Batch Delete
    $('#batchDeleteBtn').click(function() {
        var uuids = Object.keys(selectedEntries);
        if (uuids.length === 0) return;

        BootstrapDialog.confirm({
            title: 'Delete ' + uuids.length + ' Entries',
            message: 'Are you sure you want to delete ' + uuids.length + ' selected entries?<br><br>' +
                     '<strong class="text-warning">Note:</strong> DNS records at Hetzner will NOT be deleted.',
            type: BootstrapDialog.TYPE_DANGER,
            btnOKLabel: 'Delete All',
            btnOKClass: 'btn-danger',
            callback: function(result) {
                if (result) {
                    var $btn = $('#batchDeleteBtn').prop('disabled', true);
                    var completed = 0;

                    uuids.forEach(function(uuid) {
                        ajaxCall('/api/hclouddns/entries/delItem/' + uuid, {}, function() {
                            completed++;
                            if (completed === uuids.length) {
                                $btn.prop('disabled', false);
                                selectedEntries = {};
                                $('#grid-entries').bootgrid('reload');
                                updateBatchToolbar();
                                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: uuids.length + ' entries deleted.'});
                            }
                        });
                    });
                }
            }
        });
    });

    // Remove Orphaned Entries
    $('#removeOrphanedBtn').click(function() {
        BootstrapDialog.confirm({
            title: 'Remove Orphaned Entries',
            message: 'This will permanently remove all entries marked as <span class="label label-danger"><i class="fa fa-unlink"></i> Orphaned</span> from the configuration.<br><br>' +
                     '<strong>Orphaned entries</strong> are records that no longer exist at Hetzner DNS.',
            type: BootstrapDialog.TYPE_WARNING,
            btnOKLabel: 'Remove Orphaned',
            btnOKClass: 'btn-danger',
            callback: function(result) {
                if (result) {
                    var $btn = $('#removeOrphanedBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Removing...');
                    ajaxCall('/api/hclouddns/entries/removeOrphaned', {}, function(data) {
                        $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Remove Orphaned');
                        if (data && data.status === 'ok') {
                            $('#grid-entries').bootgrid('reload');
                            if (data.removedCount > 0) {
                                BootstrapDialog.alert({
                                    type: BootstrapDialog.TYPE_SUCCESS,
                                    title: 'Orphaned Entries Removed',
                                    message: data.removedCount + ' orphaned entries have been removed.'
                                });
                            } else {
                                BootstrapDialog.alert({
                                    type: BootstrapDialog.TYPE_INFO,
                                    message: 'No orphaned entries found.'
                                });
                            }
                        } else {
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_DANGER,
                                message: data.message || 'Failed to remove orphaned entries.'
                            });
                        }
                    });
                }
            }
        });
    });

    // ==================== IMPORT FUNCTIONALITY ====================
    var importAccountUuid = '';
    var existingEntries = {}; // Track already imported entries

    // Populate import account selector
    function loadImportAccounts() {
        ajaxCall('/api/hclouddns/accounts/searchItem', {}, function(data) {
            var $select = $('#importAccountSelect');
            $select.find('option:not(:first)').remove();
            if (data && data.rows) {
                $.each(data.rows, function(i, acc) {
                    if (acc.enabled === '1') {
                        $select.append('<option value="' + acc.uuid + '">' + acc.name + '</option>');
                    }
                });
            }
            $select.selectpicker('refresh');
        });
    }

    // Load accounts when section is expanded
    $('#importSection').on('show.bs.collapse', function() {
        loadImportAccounts();
    });

    // Enable/disable load button based on account selection
    $('#importAccountSelect').on('change', function() {
        importAccountUuid = $(this).val();
        $('#loadZonesBtn').prop('disabled', !importAccountUuid);
    });

    // Load zones button
    $('#loadZonesBtn').on('click', function() {
        if (!importAccountUuid) return;

        // Load existing entries first
        ajaxCall('/api/hclouddns/entries/getExistingForAccount', {account_uuid: importAccountUuid}, function(existingData) {
            existingEntries = {};
            if (existingData && existingData.entries) {
                $.each(existingData.entries, function(i, e) {
                    var key = e.zoneId + '_' + e.recordName + '_' + e.recordType;
                    existingEntries[key] = true;
                });
            }

            // Populate gateways for import modal
            var $primaryGw = $('#importPrimaryGw').empty().append('<option value="">Default Gateway (auto-detect)</option>');
            var $failoverGw = $('#importFailoverGw').empty().append('<option value="">-- None --</option>');

            ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(gwData) {
                var prio1Gw = null, prio2Gw = null;
                if (gwData && gwData.rows && gwData.rows.length > 0) {
                    // Sort by priority
                    var sorted = gwData.rows.filter(function(gw) { return gw.enabled === '1'; })
                        .sort(function(a, b) { return parseInt(a.priority) - parseInt(b.priority); });

                    $.each(sorted, function(i, gw) {
                        $primaryGw.append('<option value="' + gw.uuid + '">' + gw.name + ' (Prio ' + gw.priority + ')</option>');
                        $failoverGw.append('<option value="' + gw.uuid + '">' + gw.name + ' (Prio ' + gw.priority + ')</option>');
                        if (gw.priority === '1') prio1Gw = gw.uuid;
                        else if (gw.priority === '2' && !prio2Gw) prio2Gw = gw.uuid;
                    });
                }
                $primaryGw.selectpicker('refresh');
                $failoverGw.selectpicker('refresh');
                // Auto-select priority 1 for primary, priority 2 for failover
                if (prio1Gw) $primaryGw.selectpicker('val', prio1Gw);
                if (prio2Gw) $failoverGw.selectpicker('val', prio2Gw);
            });

            // Load zones
            loadZonesForImport();
            $('#importModal').modal('show');
        });
    });

    function loadZonesForImport() {
        $('#importZonesContainer').html('<div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 15px;">Loading zones...</p></div>');

        ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: importAccountUuid}, function(data) {
            if (data && data.status === 'ok' && data.zones) {
                renderZonesForImport(data.zones);
            } else {
                $('#importZonesContainer').html('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + (data.message || 'Failed to load zones. Please check your API token.') + '</div>');
            }
        });
    }

    function renderZonesForImport(zones) {
        var html = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> Select A/AAAA records to import as DynDNS entries. Already imported records are disabled.</div>';

        if (zones.length === 0) {
            html += '<div class="alert alert-warning">No zones found for this account.</div>';
            $('#importZonesContainer').html(html);
            return;
        }

        $.each(zones, function(i, zone) {
            html += '<div class="panel panel-default zone-import-panel" data-zone-id="' + zone.id + '">';
            html += '<div class="panel-heading" style="cursor: pointer;">';
            html += '<label style="margin: 0; cursor: pointer;"><input type="checkbox" class="zone-checkbox" data-zone-id="' + zone.id + '"> <strong>' + zone.name + '</strong></label>';
            html += '<i class="fa fa-chevron-right pull-right zone-toggle" style="margin-top: 3px;"></i>';
            html += '</div>';
            html += '<div class="panel-body zone-records-container" style="display: none;"><div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading records...</div></div>';
            html += '</div>';
        });

        $('#importZonesContainer').html(html);
        updateImportSelection();
    }

    // Zone panel toggle
    $(document).on('click', '.zone-import-panel .panel-heading', function(e) {
        if ($(e.target).is('input')) return;

        var $panel = $(this).closest('.zone-import-panel');
        var $body = $panel.find('.zone-records-container');
        var $icon = $(this).find('.zone-toggle');
        var zoneId = $panel.data('zone-id');

        if ($body.is(':visible')) {
            $body.slideUp();
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            $body.slideDown();
            $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');

            // Load records if not loaded yet
            if ($body.find('.record-checkbox').length === 0) {
                loadRecordsForZone(zoneId, $body, $panel.find('.panel-heading strong').text());
            }
        }
    });

    function loadRecordsForZone(zoneId, $container, zoneName) {
        ajaxCall('/api/hclouddns/hetzner/listRecordsForAccount', {account_uuid: importAccountUuid, zone_id: zoneId}, function(data) {
            if (data && data.status === 'ok' && data.records) {
                var html = '<table class="table table-condensed table-hover" style="margin: 0;">';
                html += '<thead><tr><th style="width: 30px;"></th><th>Name</th><th>Type</th><th>Value</th><th>TTL</th></tr></thead><tbody>';

                var hasRecords = false;
                $.each(data.records, function(i, rec) {
                    if (rec.type === 'A' || rec.type === 'AAAA') {
                        hasRecords = true;
                        var key = zoneId + '_' + rec.name + '_' + rec.type;
                        var isExisting = existingEntries[key] === true;
                        var disabledAttr = isExisting ? 'disabled' : '';
                        var rowClass = isExisting ? 'text-muted' : '';
                        var badge = isExisting ? ' <span class="label label-default">already imported</span>' : '';

                        html += '<tr class="' + rowClass + '">';
                        html += '<td><input type="checkbox" class="record-checkbox" ' + disabledAttr + ' data-zone-id="' + zoneId + '" data-zone-name="' + zoneName + '" data-record-name="' + rec.name + '" data-record-type="' + rec.type + '" data-ttl="' + (rec.ttl || 300) + '"></td>';
                        html += '<td>' + rec.name + badge + '</td>';
                        html += '<td><span class="label label-' + (rec.type === 'A' ? 'success' : 'primary') + '">' + rec.type + '</span></td>';
                        html += '<td><code>' + rec.value + '</code></td>';
                        html += '<td>' + (rec.ttl || 300) + '</td>';
                        html += '</tr>';
                    }
                });

                if (!hasRecords) {
                    html += '<tr><td colspan="5" class="text-muted text-center">No A/AAAA records in this zone</td></tr>';
                }

                html += '</tbody></table>';
                $container.html(html);
            } else {
                $container.html('<div class="alert alert-danger">Error loading records: ' + (data.message || 'Unknown error') + '</div>');
            }
        });
    }

    // Zone checkbox toggle all records
    $(document).on('change', '.zone-checkbox', function() {
        var zoneId = $(this).data('zone-id');
        var isChecked = $(this).is(':checked');
        $(this).closest('.zone-import-panel').find('.record-checkbox:not(:disabled)').prop('checked', isChecked);
        updateImportSelection();
    });

    // Record checkbox
    $(document).on('change', '.record-checkbox', function() {
        updateImportSelection();
    });

    function updateImportSelection() {
        var count = $('.record-checkbox:checked:not(:disabled)').length;
        $('#importSelectionCount').text(count > 0 ? count + ' record(s) selected' : '');
        $('#importBtn').prop('disabled', count === 0);
    }

    // Import button
    $('#importBtn').on('click', function() {
        var primaryGw = $('#importPrimaryGw').val();
        var failoverGw = $('#importFailoverGw').val();

        if (primaryGw && primaryGw === failoverGw) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, title: 'Invalid Selection', message: 'Failover gateway must differ from primary gateway.'});
            return;
        }

        var entries = [];
        $('.record-checkbox:checked:not(:disabled)').each(function() {
            entries.push({
                account: importAccountUuid,
                zoneId: $(this).data('zone-id'),
                zoneName: $(this).data('zone-name'),
                recordName: $(this).data('record-name'),
                recordType: $(this).data('record-type'),
                ttl: $(this).data('ttl') || defaultTtl
            });
        });

        if (entries.length === 0) {
            BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, title: 'No Selection', message: 'Please select at least one record to import.'});
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

        ajaxCall('/api/hclouddns/entries/batchAdd', {entries: entries, primaryGateway: primaryGw, failoverGateway: failoverGw}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Import Selected');
            if (data && data.status === 'ok') {
                $('#importModal').modal('hide');
                var msg = data.added + ' DNS entry/entries imported successfully!';
                if (data.skipped > 0) {
                    msg += ' (' + data.skipped + ' already existed)';
                }
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Import Successful', message: msg});
                $('#grid-entries').bootgrid('reload');
            } else {
                var errorMsg = '<strong>Import failed</strong><br/><br/>';
                if (data && data.message) {
                    errorMsg += data.message;
                }
                if (data && data.errors && data.errors.length > 0) {
                    errorMsg += '<br/><br/><strong>Validation errors:</strong><ul>';
                    $.each(data.errors, function(i, err) {
                        errorMsg += '<li>' + err + '</li>';
                    });
                    errorMsg += '</ul>';
                }
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Error', message: errorMsg});
            }
        });
    });

    // Tab switch handlers
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');
        if (target === '#gateways') $('#grid-gateways').bootgrid('reload');
        else if (target === '#entries') {
            preloadCaches(function() {
                $('#grid-entries').bootgrid('reload');
            });
        }
        else if (target === '#overview') {
            loadDashboard();
            loadSimulationStatus();
        }
    });

    // Initial cache load
    preloadCaches(function() {
        $('#grid-entries').bootgrid('reload');
    });

    // Handle URL parameters for creating DynDNS entry from DNS Management page
    function handleUrlParams() {
        var urlParams = new URLSearchParams(window.location.search);
        var account = urlParams.get('account');
        var zone = urlParams.get('zone');
        var zoneName = urlParams.get('zoneName');
        var record = urlParams.get('record');
        var type = urlParams.get('type');
        var ttl = urlParams.get('ttl') || defaultTtl;

        if (account && zone && record && type) {
            // Switch to entries tab
            $('a[href="#entries"]').tab('show');

            // Wait for form to be ready, then open dialog
            setTimeout(function() {
                // Trigger add button to open dialog
                $('[data-action="add"]').first().click();

                // Wait for dialog to open and form to be ready
                setTimeout(function() {
                    // Pre-fill the form
                    if ($('#entry\\.account').length) {
                        $('#entry\\.account').val(account).selectpicker('refresh');
                        // Trigger change to load zones
                        $('#entry\\.account').trigger('change');

                        // Wait for zones to load
                        setTimeout(function() {
                            $('#entry\\.zoneId').val(zone);
                            $('#entry\\.zoneName').val(zoneName);
                            $('#entry\\.recordName').val(record);
                            $('#entry\\.recordType').val(type).selectpicker('refresh');
                            $('#entry\\.ttl').val(ttl);
                        }, 500);
                    }
                }, 300);

                // Clear URL params after processing
                window.history.replaceState({}, document.title, window.location.pathname + window.location.hash);
            }, 500);
        }
    }

    // Check URL params after page load
    setTimeout(handleUrlParams, 1000);
});
</script>
