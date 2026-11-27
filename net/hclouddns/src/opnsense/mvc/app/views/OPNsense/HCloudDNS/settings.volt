{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Hetzner Cloud DNS - Settings (Accounts)
#}

<style>
    /* Import modal styles */
    #importModal .zone-item { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; background: #fff; }
    #importModal .zone-header { padding: 10px 15px; cursor: pointer; display: flex; align-items: center; background: #f8f9fa; }
    #importModal .zone-header:hover { background: #e9ecef; }
    #importModal .zone-header .zone-checkbox { margin-right: 10px; }
    #importModal .zone-header .zone-name { flex: 1; font-weight: 500; }
    #importModal .zone-header .zone-toggle { color: #666; }
    #importModal .zone-records { padding: 10px 15px 10px 40px; border-top: 1px solid #eee; display: none; }
    #importModal .zone-records.show { display: block; }
    #importModal .record-item { padding: 5px 0; display: flex; align-items: center; }
    #importModal .record-item label { margin: 0; font-weight: normal; flex: 1; }
    #importModal .record-item.existing { opacity: 0.6; background: #f5f5f5; padding: 5px 8px; margin: 2px -8px; border-radius: 3px; }
    #importModal .record-item.existing label { color: #888; }
    .bg-success { background-color: #dff0d8 !important; transition: background-color 0.3s; }
    #importModal .record-type { font-size: 11px; padding: 2px 6px; border-radius: 3px; margin-left: 8px; }
</style>

<!-- General Settings Section -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-cog"></i> {{ lang._('General Settings') }}</h3>
    </div>
    <div class="panel-body">
        {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_general_settings']) }}
        <button class="btn btn-primary" id="saveGeneralBtn"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
    </div>
</div>

<!-- Accounts Section -->
<div class="panel panel-default" style="margin-top: 20px;">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-key"></i> {{ lang._('API Accounts') }}</h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">{{ lang._('Manage API tokens for Hetzner DNS. Each token provides access to one or more zones.') }}</p>

        <table id="grid-accounts" class="table table-condensed table-hover table-striped" data-editDialog="dialogAccount" data-editAlert="accountChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">ID</th>
                    <th data-column-id="enabled" data-type="boolean" data-formatter="rowtoggle" data-width="6em">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="apiType" data-type="string" data-width="10em">{{ lang._('API Type') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false" data-width="10em">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr><td></td><td><button data-action="add" type="button" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i></button></td><td colspan="5"></td></tr>
            </tfoot>
        </table>
        <div class="alert alert-info" id="accountChangeMessage" style="display: none;">{{ lang._('Changes need to be saved.') }}</div>
        <hr/>
        <button class="btn btn-primary" id="saveAccountsBtn"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
        <button class="btn btn-success" id="addTokenBtn"><i class="fa fa-key"></i> {{ lang._('Add Token & Import') }}</button>
    </div>
</div>

<!-- Notifications Section -->
<div class="panel panel-default" style="margin-top: 20px;">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-bell"></i> {{ lang._('Notifications') }}</h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">{{ lang._('Get notified when DNS records change, failover events occur, or errors happen.') }}</p>

        <div class="row">
            <div class="col-md-12">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="notifyEnabled"> <strong>{{ lang._('Enable Notifications') }}</strong>
                    </label>
                </div>
            </div>
        </div>

        <div id="notifySettings" style="display: none; margin-top: 15px;">
            <div class="row">
                <div class="col-md-12">
                    <h5>{{ lang._('Notify On:') }}</h5>
                    <div class="checkbox-inline">
                        <label><input type="checkbox" id="notifyOnUpdate" checked> {{ lang._('DNS Updates') }}</label>
                    </div>
                    <div class="checkbox-inline">
                        <label><input type="checkbox" id="notifyOnFailover" checked> {{ lang._('Failover') }}</label>
                    </div>
                    <div class="checkbox-inline">
                        <label><input type="checkbox" id="notifyOnFailback" checked> {{ lang._('Failback') }}</label>
                    </div>
                    <div class="checkbox-inline">
                        <label><input type="checkbox" id="notifyOnError" checked> {{ lang._('Errors') }}</label>
                    </div>
                    <button class="btn btn-primary btn-xs" id="saveNotifyGlobalBtn" style="margin-left: 15px;"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
                </div>
            </div>

            <hr/>

            <div class="row" style="display:flex; flex-wrap:wrap;">
                <!-- Ntfy -->
                <div class="col-md-4" style="display:flex;">
                    <div class="well" style="flex:1; display:flex; flex-direction:column;">
                        <h5><i class="fa fa-bullhorn"></i> {{ lang._('Ntfy') }}
                            <label class="pull-right" style="font-weight:normal;margin:0;"><input type="checkbox" id="ntfyEnabled"> {{ lang._('Enable') }}</label>
                        </h5>
                        <div class="form-group">
                            <label>{{ lang._('Server URL') }}</label>
                            <input type="url" class="form-control" id="ntfyServer" value="https://ntfy.sh" placeholder="https://ntfy.sh">
                        </div>
                        <div class="form-group">
                            <label>{{ lang._('Topic') }}</label>
                            <input type="text" class="form-control" id="ntfyTopic" placeholder="my-ddns-alerts">
                        </div>
                        <div class="form-group">
                            <label>{{ lang._('Priority') }}</label>
                            <select class="form-control" id="ntfyPriority">
                                <option value="min">Min (1)</option>
                                <option value="low">Low (2)</option>
                                <option value="default" selected>Default (3)</option>
                                <option value="high">High (4)</option>
                                <option value="urgent">Urgent (5)</option>
                            </select>
                        </div>
                        <div style="margin-top:auto;">
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm saveChannelBtn" data-channel="ntfy"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
                                <button class="btn btn-info btn-sm testChannelBtn" data-channel="ntfy"><i class="fa fa-paper-plane"></i> {{ lang._('Test') }}</button>
                            </div>
                            <span class="channel-result" id="ntfyResult" style="margin-left: 10px;"></span>
                        </div>
                    </div>
                </div>

                <!-- Email (SMTP) -->
                <div class="col-md-4" style="display:flex;">
                    <div class="well" style="flex:1; display:flex; flex-direction:column;">
                        <h5><i class="fa fa-envelope"></i> {{ lang._('Email (SMTP)') }}
                            <label class="pull-right" style="font-weight:normal;margin:0;"><input type="checkbox" id="emailEnabled"> {{ lang._('Enable') }}</label>
                        </h5>
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>{{ lang._('SMTP Server') }}</label>
                                    <input type="text" class="form-control" id="smtpServer" placeholder="smtp.example.com">
                                </div>
                                <div class="form-group">
                                    <label>{{ lang._('Port') }}</label>
                                    <input type="number" class="form-control" id="smtpPort" value="587" min="1" max="65535">
                                </div>
                                <div class="form-group">
                                    <label>{{ lang._('Username') }} <small class="text-muted">({{ lang._('opt.') }})</small></label>
                                    <input type="text" class="form-control" id="smtpUser" placeholder="">
                                </div>
                                <div class="form-group">
                                    <label>{{ lang._('Password') }} <small class="text-muted">({{ lang._('opt.') }})</small></label>
                                    <input type="password" class="form-control" id="smtpPassword" placeholder="">
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>{{ lang._('Encryption') }}</label>
                                    <select class="form-control" id="smtpTls">
                                        <option value="starttls">STARTTLS</option>
                                        <option value="ssl">SSL/TLS</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>{{ lang._('Sender (From)') }}</label>
                                    <input type="email" class="form-control" id="emailFrom" placeholder="hclouddns@firewall.local">
                                </div>
                                <div class="form-group">
                                    <label>{{ lang._('Recipient (To)') }}</label>
                                    <input type="email" class="form-control" id="emailTo" placeholder="admin@example.com">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:auto;">
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm saveChannelBtn" data-channel="email"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
                                <button class="btn btn-info btn-sm testChannelBtn" data-channel="email"><i class="fa fa-paper-plane"></i> {{ lang._('Test') }}</button>
                            </div>
                            <span class="channel-result" id="emailResult" style="margin-left: 10px;"></span>
                        </div>
                    </div>
                </div>

                <!-- Webhook -->
                <div class="col-md-4" style="display:flex;">
                    <div class="well" style="flex:1; display:flex; flex-direction:column;">
                        <h5><i class="fa fa-link"></i> {{ lang._('Webhook') }}
                            <label class="pull-right" style="font-weight:normal;margin:0;"><input type="checkbox" id="webhookEnabled"> {{ lang._('Enable') }}</label>
                        </h5>
                        <div class="form-group">
                            <label>{{ lang._('URL') }}</label>
                            <input type="url" class="form-control" id="webhookUrl" placeholder="https://example.com/webhook">
                        </div>
                        <div class="form-group">
                            <label>{{ lang._('Method') }}</label>
                            <select class="form-control" id="webhookMethod">
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                            </select>
                        </div>
                        <div style="margin-top:auto;">
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm saveChannelBtn" data-channel="webhook"><i class="fa fa-save"></i> {{ lang._('Save') }}</button>
                                <button class="btn btn-info btn-sm testChannelBtn" data-channel="webhook"><i class="fa fa-paper-plane"></i> {{ lang._('Test') }}</button>
                            </div>
                            <span class="channel-result" id="webhookResult" style="margin-left: 10px;"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup / Export Section -->
<div class="panel panel-default" style="margin-top: 20px;">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-download"></i> {{ lang._('Backup / Export') }}</h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">{{ lang._('Export your configuration as JSON for backup or migration. Import to restore settings.') }}</p>
        <div class="row" style="display:flex; flex-wrap:wrap;">
            <div class="col-md-6" style="display:flex;">
                <div class="well" style="flex:1; display:flex; flex-direction:column;">
                    <h5><i class="fa fa-cloud-download"></i> {{ lang._('Export Configuration') }}</h5>
                    <p class="small text-muted">{{ lang._('Download current configuration as JSON file.') }}</p>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="exportIncludeTokens"> {{ lang._('Include API tokens (security risk!)') }}
                        </label>
                    </div>
                    <div style="margin-top:auto;">
                        <button class="btn btn-primary" id="exportConfigBtn"><i class="fa fa-download"></i> {{ lang._('Export') }}</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6" style="display:flex;">
                <div class="well" style="flex:1; display:flex; flex-direction:column;">
                    <h5><i class="fa fa-cloud-upload"></i> {{ lang._('Import Configuration') }}</h5>
                    <p class="small text-muted">{{ lang._('Import configuration from a JSON backup file.') }}</p>
                    <input type="file" id="importConfigFile" accept=".json" style="margin-bottom: 10px;">
                    <div style="margin-top:auto;">
                        <button class="btn btn-warning" id="importConfigBtn" disabled><i class="fa fa-upload"></i> {{ lang._('Import') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-download"></i> <span id="importSectionTitle">{{ lang._('Add Token & Import DNS Entries') }}</span></h4>
            </div>
            <div class="modal-body" style="padding: 20px 30px;">
        <!-- Step 1: Token Input -->
        <div id="importStep1">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ lang._('Account Name') }}</label>
                        <input type="text" class="form-control" id="importAccountName" placeholder="e.g. Production, My Project">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>{{ lang._('API Type') }}</label>
                        <select class="form-control" id="importApiType">
                            <option value="cloud">Hetzner Cloud API</option>
                            <option value="dns">Hetzner DNS API (deprecated)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>{{ lang._('API Token') }}</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="importToken" placeholder="{{ lang._('Paste Hetzner API Token here') }}">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" id="toggleImportToken"><i class="fa fa-eye"></i></button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-info" id="validateImportToken"><i class="fa fa-check-circle"></i> {{ lang._('Validate Token & Load Zones') }}</button>
            <div id="tokenValidationResult" style="margin-top: 10px;"></div>
        </div>

        <!-- Step 2: Zone/Record Selection -->
        <div id="importStep2" style="display: none;">
            <div class="alert alert-success">
                <i class="fa fa-check"></i> <span id="tokenValidMsg"></span>
            </div>

            <h5>{{ lang._('Select Zones and Records to Import') }}</h5>
            <p class="text-muted small">{{ lang._('Click zones to import all records, or expand to select individual records.') }}</p>

            <div id="zonesList" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 15px;"></div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ lang._('Primary Gateway') }}</label>
                        <select class="form-control" id="importPrimaryGw"></select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ lang._('Failover Gateway') }}</label>
                        <select class="form-control" id="importFailoverGw"></select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <span id="importSelectedCount" class="text-muted"></span>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-success" id="importBtn" disabled><i class="fa fa-download"></i> {{ lang._('Import Selected') }}</button>
            <button type="button" class="btn btn-default" id="backToStep1Btn"><i class="fa fa-arrow-left"></i> {{ lang._('Back') }}</button>
        </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Account Dialog -->
{{ partial("layout_partials/base_dialog", ['fields': accountForm, 'id': 'dialogAccount', 'label': lang._('API Account')]) }}


<script>
$(document).ready(function() {
    updateServiceControlUI('hclouddns');

    // ==================== GENERAL SETTINGS ====================
    var data_get_map = {'frm_general_settings': '/api/hclouddns/settings/get'};
    mapDataToFormUI(data_get_map).done(function() {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $('#saveGeneralBtn').click(function() {
        saveFormToEndpoint('/api/hclouddns/settings/set', 'frm_general_settings', function() {
            ajaxCall('/api/hclouddns/service/reconfigure', {_: ''}, function() {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Success', message: 'Settings saved successfully.'});
            });
        }, true);
    });

    // ==================== NOTIFICATIONS ====================
    function loadNotificationSettings() {
        ajaxCall('/api/hclouddns/settings/get', {}, function(data) {
            if (data && data.hclouddns && data.hclouddns.notifications) {
                var n = data.hclouddns.notifications;
                $('#notifyEnabled').prop('checked', n.enabled === '1').trigger('change');
                $('#notifyOnUpdate').prop('checked', n.notifyOnUpdate === '1');
                $('#notifyOnFailover').prop('checked', n.notifyOnFailover === '1');
                $('#notifyOnFailback').prop('checked', n.notifyOnFailback === '1');
                $('#notifyOnError').prop('checked', n.notifyOnError === '1');
                $('#emailEnabled').prop('checked', n.emailEnabled === '1');
                $('#emailTo').val(n.emailTo || '');
                $('#emailFrom').val(n.emailFrom || '');
                $('#smtpServer').val(n.smtpServer || '');
                $('#smtpPort').val(n.smtpPort || '587');
                $('#smtpTls').val(n.smtpTls || 'starttls');
                $('#smtpUser').val(n.smtpUser || '');
                $('#smtpPassword').val(n.smtpPassword || '');
                $('#webhookEnabled').prop('checked', n.webhookEnabled === '1');
                $('#webhookUrl').val(n.webhookUrl || '');
                $('#webhookMethod').val(n.webhookMethod || 'POST');
                $('#ntfyEnabled').prop('checked', n.ntfyEnabled === '1');
                $('#ntfyServer').val(n.ntfyServer || 'https://ntfy.sh');
                $('#ntfyTopic').val(n.ntfyTopic || '');
                $('#ntfyPriority').val(n.ntfyPriority || 'default');
            }
        });
    }

    $('#notifyEnabled').change(function() {
        $('#notifySettings').toggle($(this).is(':checked'));
    });

    $('#smtpTls').change(function() {
        var portMap = {'starttls': 587, 'ssl': 465, 'none': 25};
        $('#smtpPort').val(portMap[$(this).val()] || 587);
    });

    // Collect all notification settings into POST data
    function getAllNotifyData() {
        return {
            'hclouddns[notifications][enabled]': $('#notifyEnabled').is(':checked') ? '1' : '0',
            'hclouddns[notifications][notifyOnUpdate]': $('#notifyOnUpdate').is(':checked') ? '1' : '0',
            'hclouddns[notifications][notifyOnFailover]': $('#notifyOnFailover').is(':checked') ? '1' : '0',
            'hclouddns[notifications][notifyOnFailback]': $('#notifyOnFailback').is(':checked') ? '1' : '0',
            'hclouddns[notifications][notifyOnError]': $('#notifyOnError').is(':checked') ? '1' : '0',
            'hclouddns[notifications][emailEnabled]': $('#emailEnabled').is(':checked') ? '1' : '0',
            'hclouddns[notifications][emailTo]': $('#emailTo').val(),
            'hclouddns[notifications][emailFrom]': $('#emailFrom').val(),
            'hclouddns[notifications][smtpServer]': $('#smtpServer').val(),
            'hclouddns[notifications][smtpPort]': $('#smtpPort').val(),
            'hclouddns[notifications][smtpTls]': $('#smtpTls').val(),
            'hclouddns[notifications][smtpUser]': $('#smtpUser').val(),
            'hclouddns[notifications][smtpPassword]': $('#smtpPassword').val(),
            'hclouddns[notifications][webhookEnabled]': $('#webhookEnabled').is(':checked') ? '1' : '0',
            'hclouddns[notifications][webhookUrl]': $('#webhookUrl').val(),
            'hclouddns[notifications][webhookMethod]': $('#webhookMethod').val(),
            'hclouddns[notifications][ntfyEnabled]': $('#ntfyEnabled').is(':checked') ? '1' : '0',
            'hclouddns[notifications][ntfyServer]': $('#ntfyServer').val(),
            'hclouddns[notifications][ntfyTopic]': $('#ntfyTopic').val(),
            'hclouddns[notifications][ntfyPriority]': $('#ntfyPriority').val()
        };
    }

    // Show inline result next to button
    function showChannelResult($span, success, message) {
        var icon = success ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-danger"></i>';
        $span.html(icon + ' <small>' + message + '</small>');
        setTimeout(function() { $span.fadeOut(400, function() { $(this).empty().show(); }); }, 8000);
    }

    // Save global notify toggles (enabled + notify-on checkboxes)
    $('#saveNotifyGlobalBtn').click(function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        ajaxCall('/api/hclouddns/settings/set', getAllNotifyData(), function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
            if (data && data.status === 'ok') {
                $btn.after('<span class="text-success" style="margin-left:8px;"><i class="fa fa-check"></i> Saved</span>');
                setTimeout(function() { $btn.next('span').fadeOut(400, function() { $(this).remove(); }); }, 3000);
            }
        });
    });

    // Per-channel Save button
    $(document).on('click', '.saveChannelBtn', function() {
        var channel = $(this).data('channel');
        var $btn = $(this).prop('disabled', true);
        var origHtml = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin"></i>');
        var $result = $('#' + channel + 'Result');

        ajaxCall('/api/hclouddns/settings/set', getAllNotifyData(), function(data) {
            $btn.prop('disabled', false).html(origHtml);
            if (data && data.status === 'ok') {
                showChannelResult($result, true, 'Saved');
            } else {
                var msg = 'Save failed';
                if (data && data.validations) {
                    msg = Object.values(data.validations).join(', ');
                }
                showChannelResult($result, false, msg);
            }
        });
    });

    // Per-channel Test button: saves first, then tests
    $(document).on('click', '.testChannelBtn', function() {
        var channel = $(this).data('channel');
        var $btn = $(this).prop('disabled', true);
        var origHtml = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin"></i>');
        var $result = $('#' + channel + 'Result');
        $result.html('<small class="text-muted"><i class="fa fa-spinner fa-spin"></i> Saving & testing...</small>');

        // Save all settings first, then test the specific channel
        ajaxCall('/api/hclouddns/settings/set', getAllNotifyData(), function(saveData) {
            if (saveData && saveData.status !== 'ok') {
                $btn.prop('disabled', false).html(origHtml);
                var msg = 'Save failed';
                if (saveData && saveData.validations) {
                    msg = Object.values(saveData.validations).join(', ');
                }
                showChannelResult($result, false, msg);
                return;
            }
            // Now test the channel
            ajaxCall('/api/hclouddns/service/testNotify/' + channel, {}, function(data) {
                $btn.prop('disabled', false).html(origHtml);
                if (data && data.results && data.results[channel]) {
                    var r = data.results[channel];
                    showChannelResult($result, r.success, r.message || (r.success ? 'OK' : 'Failed'));
                } else {
                    var msg = (data && data.message) ? data.message : 'Test failed';
                    showChannelResult($result, false, msg);
                }
            });
        });
    });

    // Load notification settings on page load
    loadNotificationSettings();

    // ==================== BACKUP / EXPORT ====================
    $('#exportConfigBtn').click(function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Exporting...');
        var includeTokens = $('#exportIncludeTokens').is(':checked') ? '1' : '0';

        ajaxCall('/api/hclouddns/settings/export/' + includeTokens, {}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Export');

            if (data && data.status === 'ok' && data.export) {
                // Download as JSON file
                var jsonStr = JSON.stringify(data.export, null, 2);
                var blob = new Blob([jsonStr], {type: 'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'hclouddns-config-' + new Date().toISOString().slice(0,10) + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_SUCCESS,
                    title: 'Export Complete',
                    message: 'Configuration exported: ' + data.export.gateways.length + ' gateways, ' +
                             data.export.accounts.length + ' accounts, ' + data.export.entries.length + ' entries.'
                });
            } else {
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Export Error', message: 'Failed to export configuration. Please check system logs.'});
            }
        });
    });

    // Enable import button when file is selected
    $('#importConfigFile').change(function() {
        $('#importConfigBtn').prop('disabled', !this.files.length);
    });

    $('#importConfigBtn').click(function() {
        var file = $('#importConfigFile')[0].files[0];
        if (!file) return;

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var importData = JSON.parse(e.target.result);

                // Show confirmation dialog
                var msg = 'This will import configuration from "' + file.name + '":<br><br>';
                if (importData.gateways) msg += '• ' + importData.gateways.length + ' gateway(s)<br>';
                if (importData.accounts) msg += '• ' + importData.accounts.length + ' account(s)<br>';
                if (importData.entries) msg += '• ' + importData.entries.length + ' DNS entry/entries<br>';
                msg += '<br><strong class="text-warning">Note:</strong> This will ADD to existing configuration, not replace it.';

                BootstrapDialog.confirm({
                    title: 'Confirm Import',
                    message: msg,
                    type: BootstrapDialog.TYPE_WARNING,
                    btnOKLabel: 'Import',
                    btnOKClass: 'btn-warning',
                    callback: function(result) {
                        if (result) {
                            ajaxCall('/api/hclouddns/settings/import', {import: JSON.stringify(importData)}, function(data) {
                                $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                                $('#importConfigFile').val('');

                                if (data && data.status === 'ok') {
                                    var msg = data.message || 'Import successful.';
                                    if (data.errors && data.errors.length > 0) {
                                        msg += '<br><br><strong>Warnings:</strong><br>' + data.errors.join('<br>');
                                    }
                                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, title: 'Import Complete', message: msg});
                                    $('#grid-accounts').bootgrid('reload');
                                } else {
                                    BootstrapDialog.alert({
                                        type: BootstrapDialog.TYPE_DANGER,
                                        title: 'Import Failed',
                                        message: data.message || 'Failed to import configuration.'
                                    });
                                }
                            });
                        } else {
                            $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        }
                    }
                });
            } catch (err) {
                $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: 'Invalid JSON file: ' + err.message});
            }
        };
        reader.readAsText(file);
    });

    // ==================== ACCOUNTS ====================
    $('#grid-accounts').UIBootgrid({
        search: '/api/hclouddns/accounts/searchItem',
        get: '/api/hclouddns/accounts/getItem/',
        set: '/api/hclouddns/accounts/setItem/',
        add: '/api/hclouddns/accounts/addItem/',
        del: '/api/hclouddns/accounts/delItem/',
        toggle: '/api/hclouddns/accounts/toggleItem/',
        options: {
            formatters: {
                commands: function(col, row) {
                    return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '" title="Edit"><i class="fa fa-pencil fa-fw"></i></button> ' +
                           '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '" title="Delete"><i class="fa fa-trash-o fa-fw"></i></button>';
                }
            }
        }
    });

    // Custom delete handler with cascade warning
    $(document).on('click', '.command-delete', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var uuid = $(this).data('row-id');
        var $row = $(this).closest('tr');
        var accountName = $row.find('td:nth-child(3)').text() || 'this account';

        // First check if there are associated entries
        ajaxCall('/api/hclouddns/accounts/getEntryCount/' + uuid, {}, function(data) {
            var entryCount = (data && data.count) ? data.count : 0;
            var message = 'Are you sure you want to delete "' + accountName + '"?';

            if (entryCount > 0) {
                var entryList = (data.entries && data.entries.length > 0)
                    ? '<br><br><small class="text-muted">' + data.entries.slice(0, 5).join(', ') + (data.entries.length > 5 ? '...' : '') + '</small>'
                    : '';
                message = '<strong class="text-danger"><i class="fa fa-exclamation-triangle"></i> Warning!</strong><br><br>' +
                          'This account has <strong>' + entryCount + ' DNS entries</strong> that will also be deleted:' +
                          entryList + '<br><br>' +
                          'Do you want to continue?';
            }

            BootstrapDialog.confirm({
                title: entryCount > 0 ? 'Delete Account & Entries' : 'Delete Account',
                message: message,
                type: entryCount > 0 ? BootstrapDialog.TYPE_DANGER : BootstrapDialog.TYPE_DEFAULT,
                btnCancelLabel: 'Cancel',
                btnOKLabel: entryCount > 0 ? 'Delete All' : 'Delete',
                btnOKClass: 'btn-danger',
                callback: function(result) {
                    if (result) {
                        ajaxCall('/api/hclouddns/accounts/delItem/' + uuid, {}, function(delResult) {
                            if (delResult && delResult.result === 'deleted') {
                                ajaxCall('/api/hclouddns/settings/set', {}, function() {
                                    $('#grid-accounts').bootgrid('reload');
                                    var msg = 'Account deleted.';
                                    if (delResult.deletedEntries > 0) {
                                        msg = 'Account and ' + delResult.deletedEntries + ' DNS entries deleted.';
                                    }
                                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: msg});
                                });
                            } else {
                                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Delete Error', message: 'Failed to delete account. Please check system logs.'});
                            }
                        });
                    }
                }
            });
        });
    });

    $('#saveAccountsBtn').click(function() {
        saveFormToEndpoint('/api/hclouddns/settings/set', 'frm_general_settings', function() {
            $('#grid-accounts').bootgrid('reload');
        }, true);
    });

    // ==================== INLINE IMPORT SECTION ====================
    var importZonesData = [];
    var importAccountUuid = null;
    var importIsExistingAccount = false;
    var importToken = '';
    var existingEntries = {};

    function resetImportSection() {
        $('#importAccountName').val('').prop('disabled', false);
        $('#importApiType').val('cloud').prop('disabled', false);
        $('#importToken').val('').prop('disabled', false);
        $('#tokenValidationResult').empty();
        $('#importStep2').hide();
        $('#importStep1').show();
        $('#zonesList').empty();
        $('#importBtn').prop('disabled', true);
        $('#importSelectedCount').text('');
        $('#validateImportToken').show().prop('disabled', false).html('<i class="fa fa-check-circle"></i> Validate Token & Load Zones');
        $('#backToStep1Btn').show();
        importZonesData = [];
        importAccountUuid = null;
        importIsExistingAccount = false;
        importToken = '';
        existingEntries = {};
        loadGatewaysForImport();
    }

    function showImportSection() {
        $('#importModal').modal('show');
    }

    $(document).on('click', '#addTokenBtn', function(e) {
        e.preventDefault();
        resetImportSection();
        $('#importSectionTitle').text('Add Token & Import DNS Entries');
        showImportSection();
    });

    $(document).on('click', '#closeImportSection', function() {
        $('#importModal').modal('hide');
    });

    $(document).on('click', '#backToStep1Btn', function() {
        $('#importStep2').hide();
        $('#importStep1').show();
    });

    function openImportForAccount(accountUuid, accountName, apiType) {
        resetImportSection();
        importAccountUuid = accountUuid;
        importIsExistingAccount = true;
        $('#importAccountName').val(accountName).prop('disabled', true);
        $('#importApiType').val(apiType).prop('disabled', true);
        $('#importToken').val('********').prop('disabled', true);
        $('#importSectionTitle').text('Import more zones for "' + accountName + '"');
        $('#backToStep1Btn').hide();
        $('#validateImportToken').hide();
        showImportSection();
        $('#tokenValidationResult').html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Loading zones...</div>');
        ajaxCall('/api/hclouddns/entries/getExistingForAccount', {account_uuid: accountUuid}, function(existingData) {
            existingEntries = {};
            if (existingData && existingData.entries) {
                $.each(existingData.entries, function(i, e) {
                    var key = e.zoneId + ':' + e.recordName + ':' + e.recordType;
                    existingEntries[key] = true;
                });
            }
            ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: accountUuid}, function(data) {
                if (data && data.status === 'ok' && data.zones && data.zones.length > 0) {
                    $('#tokenValidMsg').text(data.zones.length + ' zone(s) found.');
                    $('#tokenValidationResult').empty();
                    $('#importStep1').hide();
                    $('#importStep2').show();
                    renderZonesForImport(data.zones, null, accountUuid);
                } else {
                    var msg = (data && data.message) ? data.message : 'Could not load zones.';
                    $('#tokenValidationResult').html('<div class="alert alert-danger"><i class="fa fa-times"></i> ' + msg + '</div>');
                }
            });
        });
    }

    $('#toggleImportToken').click(function() {
        var $i = $('#importToken');
        if ($i.attr('type') === 'password') { $i.attr('type', 'text'); $(this).html('<i class="fa fa-eye-slash"></i>'); }
        else { $i.attr('type', 'password'); $(this).html('<i class="fa fa-eye"></i>'); }
    });

    function loadGatewaysForImport() {
        ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(data) {
            var $p = $('#importPrimaryGw').empty();
            var $f = $('#importFailoverGw').empty().append('<option value="">None (no failover)</option>');
            var gateways = [];
            if (data && data.rows) {
                gateways = data.rows.filter(function(gw) { return gw.enabled === '1'; });
                gateways.sort(function(a, b) { return (parseInt(a.priority) || 99) - (parseInt(b.priority) || 99); });
            }
            if (gateways.length === 0) {
                // No gateways configured - use default option
                $p.append('<option value="_default">Default Gateway (auto-detect)</option>');
            } else {
                $.each(gateways, function(i, gw) {
                    $p.append('<option value="' + gw.uuid + '">' + gw.name + ' (Prio ' + gw.priority + ')</option>');
                    $f.append('<option value="' + gw.uuid + '">' + gw.name + ' (Prio ' + gw.priority + ')</option>');
                });
                if (gateways.length > 0) { $p.val(gateways[0].uuid); }
                if (gateways.length > 1) { $f.val(gateways[1].uuid); }
            }
            updateImportCount();
        });
    }

    $('#validateImportToken').click(function() {
        var token = $('#importToken').val().trim();
        var name = $('#importAccountName').val().trim();
        var apiType = $('#importApiType').val();

        if (!name) { $('#tokenValidationResult').html('<div class="alert alert-warning">Please enter an account name.</div>'); return; }
        if (!token) { $('#tokenValidationResult').html('<div class="alert alert-warning">Please enter an API token.</div>'); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Validating...');
        $('#tokenValidationResult').html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Validating token and loading zones...</div>');
        importToken = token;

        ajaxCall('/api/hclouddns/accounts/addItem', {account: {enabled: '1', name: name, apiType: apiType, apiToken: token, description: ''}}, function(addResult) {
            if (addResult && addResult.uuid) {
                importAccountUuid = addResult.uuid;
                ajaxCall('/api/hclouddns/settings/set', {}, function() {
                    ajaxCall('/api/hclouddns/hetzner/listZonesForAccount', {account_uuid: importAccountUuid}, function(data) {
                        $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Validate Token & Load Zones');
                        if (data && data.status === 'ok' && data.zones && data.zones.length > 0) {
                            $('#tokenValidMsg').text('Token valid! ' + data.zones.length + ' zone(s) found.');
                            $('#importStep1').hide();
                            $('#importStep2').show();
                            renderZonesForImport(data.zones, null, importAccountUuid);
                            $('#grid-accounts').bootgrid('reload');
                        } else {
                            var msg = (data && data.message) ? data.message : 'Invalid token or no zones found.';
                            $('#tokenValidationResult').html('<div class="alert alert-danger"><i class="fa fa-times"></i> ' + msg + '</div>');
                            if (importAccountUuid) {
                                ajaxCall('/api/hclouddns/accounts/delItem/' + importAccountUuid, {}, function() {
                                    ajaxCall('/api/hclouddns/settings/set', {}, function() {});
                                });
                                importAccountUuid = null;
                            }
                        }
                    });
                });
            } else {
                $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Validate Token & Load Zones');
                var errMsg = (addResult && addResult.validations) ? Object.values(addResult.validations).join(', ') : 'Could not create account.';
                $('#tokenValidationResult').html('<div class="alert alert-danger">' + errMsg + '</div>');
            }
        });
    });

    function renderZonesForImport(zones, token, accountUuid) {
        importZonesData = zones;
        var $list = $('#zonesList').empty();

        $.each(zones, function(i, zone) {
            $list.append(
                '<div class="zone-item" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '">' +
                    '<div class="zone-header">' +
                        '<input type="checkbox" class="zone-checkbox" id="zc-' + zone.id + '">' +
                        '<label for="zc-' + zone.id + '" class="zone-name">' + zone.name + '</label>' +
                        '<span class="badge zone-badge" id="badge-' + zone.id + '"><i class="fa fa-spinner fa-spin"></i></span> ' +
                        '<i class="fa fa-chevron-right zone-toggle"></i>' +
                    '</div>' +
                    '<div class="zone-records" id="zr-' + zone.id + '"><i class="fa fa-spinner fa-spin"></i> Loading records...</div>' +
                '</div>'
            );

            var recordsEndpoint = accountUuid ? '/api/hclouddns/hetzner/listRecordsForAccount' : '/api/hclouddns/hetzner/listRecords';
            var recordsData = accountUuid ? {account_uuid: accountUuid, zone_id: zone.id} : {token: token, zone_id: zone.id};

            ajaxCall(recordsEndpoint, recordsData, function(data) {
                var $recs = $('#zr-' + zone.id).empty();
                var $badge = $('#badge-' + zone.id);
                if (data && data.status === 'ok' && data.records) {
                    var aRecords = data.records.filter(function(r) { return r.type === 'A' || r.type === 'AAAA'; });
                    var existingCount = 0;
                    $.each(aRecords, function(j, rec) {
                        var key = zone.id + ':' + rec.name + ':' + rec.type;
                        if (existingEntries[key]) existingCount++;
                    });
                    var newCount = aRecords.length - existingCount;
                    $badge.text(aRecords.length + ' A/AAAA' + (existingCount > 0 ? ' (' + existingCount + ' imported)' : ''));
                    if (aRecords.length === 0) {
                        $recs.html('<em class="text-muted">No A/AAAA records in this zone.</em>');
                        return;
                    }
                    $.each(aRecords, function(j, rec) {
                        var rid = 'rec-' + zone.id + '-' + j;
                        var typeClass = rec.type === 'A' ? 'label-primary' : 'label-info';
                        var key = zone.id + ':' + rec.name + ':' + rec.type;
                        var isExisting = existingEntries[key] || false;
                        var disabledAttr = isExisting ? ' disabled' : '';
                        var checkedAttr = isExisting ? ' checked' : '';
                        var lockIcon = isExisting ? '<i class="fa fa-lock text-muted" title="Already imported"></i> ' : '';
                        var itemClass = isExisting ? 'record-item existing' : 'record-item';
                        $recs.append(
                            '<div class="' + itemClass + '">' +
                                '<input type="checkbox" class="record-checkbox" id="' + rid + '"' + checkedAttr + disabledAttr +
                                    ' data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '" ' +
                                    'data-record-name="' + rec.name + '" data-record-type="' + rec.type + '" data-ttl="' + rec.ttl + '" data-existing="' + isExisting + '">' +
                                '<label for="' + rid + '">' + lockIcon + rec.name + '</label>' +
                                '<span class="record-type label ' + typeClass + '">' + rec.type + '</span>' +
                                '<span class="text-muted small" style="margin-left:10px;">' + rec.value + '</span>' +
                            '</div>'
                        );
                    });
                } else {
                    $badge.text('error');
                    $recs.html('<em class="text-danger">Failed to load records.</em>');
                }
            });
        });
    }

    $(document).on('click', '.zone-header', function(e) {
        if ($(e.target).is('input, label')) return;
        var $item = $(this).closest('.zone-item');
        var $recs = $item.find('.zone-records');
        var $icon = $(this).find('.zone-toggle');
        $recs.toggleClass('show');
        $icon.toggleClass('fa-chevron-right fa-chevron-down');
    });

    $(document).on('change', '.zone-checkbox', function() {
        var $item = $(this).closest('.zone-item');
        $item.find('.record-checkbox').prop('checked', $(this).is(':checked'));
        updateImportCount();
    });

    $(document).on('change', '.record-checkbox', function() {
        var $item = $(this).closest('.zone-item');
        var total = $item.find('.record-checkbox').length;
        var checked = $item.find('.record-checkbox:checked').length;
        $item.find('.zone-checkbox').prop('checked', checked === total).prop('indeterminate', checked > 0 && checked < total);
        updateImportCount();
    });

    function updateImportCount() {
        var newCount = $('.record-checkbox:checked:not(:disabled)').length;
        var existingCount = $('.record-checkbox:checked:disabled').length;
        var text = '';
        if (newCount > 0) text = newCount + ' new record(s) to import';
        if (existingCount > 0) text += (text ? ', ' : '') + existingCount + ' already imported';
        $('#importSelectedCount').text(text);
        // Enable button if records are selected (gateway is always available - either configured or _default)
        $('#importBtn').prop('disabled', newCount === 0);
    }

    $('#importPrimaryGw').change(updateImportCount);

    $('#importBtn').click(function() {
        var primaryGw = $('#importPrimaryGw').val();
        var failoverGw = $('#importFailoverGw').val();

        // Handle _default gateway (no gateways configured - will use system default)
        if (primaryGw === '_default') {
            primaryGw = '';
            failoverGw = '';
        }
        if (primaryGw && primaryGw === failoverGw) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Failover gateway must differ from primary.'}); return; }

        var entries = [];
        $('.record-checkbox:checked:not(:disabled)').each(function() {
            entries.push({
                account: importAccountUuid,
                zoneId: $(this).data('zone-id'),
                zoneName: $(this).data('zone-name'),
                recordName: $(this).data('record-name'),
                recordType: $(this).data('record-type'),
                ttl: $(this).data('ttl') || 300
            });
        });

        if (entries.length === 0) { BootstrapDialog.alert({type: BootstrapDialog.TYPE_WARNING, message: 'Please select at least one new record to import.'}); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

        ajaxCall('/api/hclouddns/entries/batchAdd', {entries: entries, primaryGateway: primaryGw, failoverGateway: failoverGw}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Import');
            if (data && data.status === 'ok') {
                $('#importModal').modal('hide');
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: data.added + ' DNS entry/entries imported successfully!'});
                $('#grid-accounts').bootgrid('reload');
            } else {
                var errMsg = (data && data.message) ? data.message : 'Import failed. Please check system logs.';
                BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, title: 'Import Error', message: errMsg});
            }
        });
    });

});
</script>
