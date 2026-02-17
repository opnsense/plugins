{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.
#}

<style>
    .status-active { color: #5cb85c; }
    .status-failover { color: #f0ad4e; }
    .status-paused { color: #999; }
    .status-error { color: #d9534f; }
    .status-pending { color: #5bc0de; }
    .batch-actions {
        background-color: #f5f5f5;
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    .batch-actions .btn {
        margin-right: 5px;
    }
</style>

<script>
    $(document).ready(function() {
        var selectedUuids = [];
        var gatewaysCache = {};

        // Load gateways for dropdown
        function loadGatewaysCache() {
            ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(data, status) {
                if (data && data.rows) {
                    $.each(data.rows, function(i, gw) {
                        gatewaysCache[gw.uuid] = gw.name;
                    });
                    // Reload grid to update gateway names
                    $("#grid-entries").bootgrid('reload');
                }
            });
        }

        loadGatewaysCache();

        // Initialize bootgrid for entries table
        $("#grid-entries").UIBootgrid({
            search: '/api/hclouddns/entries/searchItem',
            get: '/api/hclouddns/entries/getItem/',
            set: '/api/hclouddns/entries/setItem/',
            add: '/api/hclouddns/entries/addItem/',
            del: '/api/hclouddns/entries/delItem/',
            toggle: '/api/hclouddns/entries/toggleItem/',
            options: {
                selection: true,
                multiSelect: true,
                rowSelect: true,
                keepSelection: true,
                formatters: {
                    commands: function(column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-edit" data-row-id="' + row.uuid + '"><span class="fa fa-pencil"></span></button> ' +
                               '<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="' + row.uuid + '"><span class="fa fa-trash-o"></span></button>' +
                               '<button type="button" class="btn btn-xs btn-warning command-pause" data-row-id="' + row.uuid + '" title="{{ lang._("Pause/Resume") }}"><span class="fa fa-pause"></span></button>' +
                               '<button type="button" class="btn btn-xs btn-info command-refresh" data-row-id="' + row.uuid + '" title="{{ lang._("Refresh from Hetzner") }}"><span class="fa fa-refresh"></span></button>';
                    },
                    rowtoggle: function(column, row) {
                        if (parseInt(row[column.id], 2) === 1) {
                            return '<span style="cursor: pointer;" class="fa fa-check-square-o command-toggle" data-value="1" data-row-id="' + row.uuid + '"></span>';
                        } else {
                            return '<span style="cursor: pointer;" class="fa fa-square-o command-toggle" data-value="0" data-row-id="' + row.uuid + '"></span>';
                        }
                    },
                    status: function(column, row) {
                        var status = row[column.id] || 'pending';
                        var icons = {
                            'active': '<span class="status-active"><i class="fa fa-circle"></i> {{ lang._("Active") }}</span>',
                            'failover': '<span class="status-failover"><i class="fa fa-exclamation-circle"></i> {{ lang._("Failover") }}</span>',
                            'paused': '<span class="status-paused"><i class="fa fa-pause-circle"></i> {{ lang._("Paused") }}</span>',
                            'error': '<span class="status-error"><i class="fa fa-times-circle"></i> {{ lang._("Error") }}</span>',
                            'pending': '<span class="status-pending"><i class="fa fa-clock-o"></i> {{ lang._("Pending") }}</span>'
                        };
                        return icons[status] || icons['pending'];
                    },
                    gateway: function(column, row) {
                        var uuid = row[column.id];
                        return gatewaysCache[uuid] || uuid.substr(0, 8) + '...';
                    },
                    record: function(column, row) {
                        var name = row.recordName || '';
                        var zone = row.zoneName || '';
                        if (name === '@') {
                            return '<code>' + zone + '</code>';
                        }
                        return '<code>' + name + '.' + zone + '</code>';
                    }
                }
            }
        });

        // Track selection changes
        $("#grid-entries").on("selected.rs.jquery.bootgrid", function(e, rows) {
            updateSelection();
        }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
            updateSelection();
        });

        function updateSelection() {
            selectedUuids = $("#grid-entries").bootgrid("getSelectedRows");
            if (selectedUuids.length > 0) {
                $('#batchActions').show();
                $('#selectedCount').text(selectedUuids.length);
            } else {
                $('#batchActions').hide();
            }
        }

        // Pause/Resume single entry
        $(document).on('click', '.command-pause', function() {
            var uuid = $(this).data('row-id');
            ajaxCall('/api/hclouddns/entries/pause/' + uuid, {}, function(data, status) {
                if (data && data.status === 'ok') {
                    $("#grid-entries").bootgrid('reload');
                }
            });
        });

        // Refresh single entry from Hetzner
        $(document).on('click', '.command-refresh', function() {
            var uuid = $(this).data('row-id');
            var $btn = $(this);
            $btn.find('span').addClass('fa-spin');

            ajaxCall('/api/hclouddns/entries/getHetznerIp/' + uuid, {}, function(data, status) {
                $btn.find('span').removeClass('fa-spin');

                if (data && data.status === 'ok') {
                    BootstrapDialog.show({
                        title: "{{ lang._('Hetzner DNS Status') }}",
                        message: '<strong>{{ lang._("Current IP at Hetzner") }}:</strong> ' + (data.ip || '{{ lang._("Not found") }}') + '<br>' +
                                 '<strong>{{ lang._("Record ID") }}:</strong> ' + (data.recordId || '{{ lang._("N/A") }}') + '<br>' +
                                 '<strong>{{ lang._("TTL") }}:</strong> ' + (data.ttl || '{{ lang._("N/A") }}') + 's',
                        type: BootstrapDialog.TYPE_INFO,
                        buttons: [{
                            label: "{{ lang._('Close') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                } else {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Error') }}",
                        message: data && data.message ? data.message : "{{ lang._('Failed to get Hetzner IP.') }}",
                        type: BootstrapDialog.TYPE_DANGER
                    });
                }
            });
        });

        // Batch pause
        $('#batchPauseBtn').click(function() {
            if (selectedUuids.length === 0) return;

            ajaxCall('/api/hclouddns/entries/batchUpdate', {
                uuids: selectedUuids,
                action: 'pause'
            }, function(data, status) {
                if (data && data.status === 'ok') {
                    $("#grid-entries").bootgrid('reload');
                    BootstrapDialog.alert({
                        title: "{{ lang._('Success') }}",
                        message: data.processed + ' {{ lang._("entries paused.") }}',
                        type: BootstrapDialog.TYPE_SUCCESS
                    });
                }
            });
        });

        // Batch resume
        $('#batchResumeBtn').click(function() {
            if (selectedUuids.length === 0) return;

            ajaxCall('/api/hclouddns/entries/batchUpdate', {
                uuids: selectedUuids,
                action: 'resume'
            }, function(data, status) {
                if (data && data.status === 'ok') {
                    $("#grid-entries").bootgrid('reload');
                    BootstrapDialog.alert({
                        title: "{{ lang._('Success') }}",
                        message: data.processed + ' {{ lang._("entries resumed.") }}',
                        type: BootstrapDialog.TYPE_SUCCESS
                    });
                }
            });
        });

        // Batch delete
        $('#batchDeleteBtn').click(function() {
            if (selectedUuids.length === 0) return;

            BootstrapDialog.confirm({
                title: "{{ lang._('Confirm Delete') }}",
                message: "{{ lang._('Are you sure you want to delete') }} " + selectedUuids.length + " {{ lang._('entries?') }}",
                type: BootstrapDialog.TYPE_DANGER,
                btnOKLabel: "{{ lang._('Delete') }}",
                btnOKClass: 'btn-danger',
                callback: function(result) {
                    if (result) {
                        ajaxCall('/api/hclouddns/entries/batchUpdate', {
                            uuids: selectedUuids,
                            action: 'delete'
                        }, function(data, status) {
                            if (data && data.status === 'ok') {
                                $("#grid-entries").bootgrid('reload');
                                selectedUuids = [];
                                updateSelection();
                            }
                        });
                    }
                }
            });
        });

        // Batch change gateway
        $('#batchGatewayBtn').click(function() {
            if (selectedUuids.length === 0) return;

            // Build gateway select options
            var options = '<option value="">{{ lang._("-- Select Gateway --") }}</option>';
            $.each(gatewaysCache, function(uuid, name) {
                options += '<option value="' + uuid + '">' + name + '</option>';
            });

            BootstrapDialog.show({
                title: "{{ lang._('Change Primary Gateway') }}",
                message: '<div class="form-group">' +
                         '<label>{{ lang._("New Primary Gateway") }}</label>' +
                         '<select id="newGatewaySelect" class="form-control">' + options + '</select>' +
                         '</div>',
                buttons: [{
                    label: "{{ lang._('Cancel') }}",
                    action: function(dialog) { dialog.close(); }
                }, {
                    label: "{{ lang._('Apply') }}",
                    cssClass: 'btn-primary',
                    action: function(dialog) {
                        var newGateway = $('#newGatewaySelect').val();
                        if (!newGateway) {
                            alert("{{ lang._('Please select a gateway.') }}");
                            return;
                        }

                        ajaxCall('/api/hclouddns/entries/batchUpdate', {
                            uuids: selectedUuids,
                            action: 'setGateway',
                            gateway: newGateway
                        }, function(data, status) {
                            if (data && data.status === 'ok') {
                                $("#grid-entries").bootgrid('reload');
                                dialog.close();
                            }
                        });
                    }
                }]
            });
        });

        // Refresh all entries status
        $('#refreshAllBtn').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Refreshing...") }}');

            ajaxCall('/api/hclouddns/entries/refreshStatus', {}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> {{ lang._("Refresh Status") }}');
                $("#grid-entries").bootgrid('reload');

                if (data && data.status === 'ok') {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Status Refreshed') }}",
                        message: data.entries.length + ' {{ lang._("entries checked.") }}',
                        type: BootstrapDialog.TYPE_SUCCESS
                    });
                }
            });
        });

        // Update all records now
        $('#updateNowBtn').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Updating...") }}');

            ajaxCall('/api/hclouddns/service/updateV2', {}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> {{ lang._("Update Now") }}');
                $("#grid-entries").bootgrid('reload');

                if (data) {
                    var message = data.message || '{{ lang._("Update completed.") }}';
                    var type = data.status === 'ok' ? BootstrapDialog.TYPE_SUCCESS :
                               (data.status === 'warning' ? BootstrapDialog.TYPE_WARNING : BootstrapDialog.TYPE_INFO);

                    BootstrapDialog.alert({
                        title: "{{ lang._('Update Result') }}",
                        message: message,
                        type: type
                    });
                }
            });
        });
    });
</script>

<div class="tab-content content-box">
    <div id="entries" class="tab-pane fade in active">
        <div class="content-box-main">
            <div class="col-md-12">
                <h2>{{ lang._('DNS Entries') }}</h2>
                <p class="text-muted">{{ lang._('Manage your dynamic DNS entries. Select multiple entries for batch operations.') }}</p>
            </div>

            <!-- Batch Actions -->
            <div class="col-md-12">
                <div class="batch-actions" id="batchActions" style="display: none;">
                    <strong><span id="selectedCount">0</span> {{ lang._('selected') }}:</strong>
                    <button type="button" class="btn btn-sm btn-warning" id="batchPauseBtn"><i class="fa fa-pause"></i> {{ lang._('Pause') }}</button>
                    <button type="button" class="btn btn-sm btn-success" id="batchResumeBtn"><i class="fa fa-play"></i> {{ lang._('Resume') }}</button>
                    <button type="button" class="btn btn-sm btn-info" id="batchGatewayBtn"><i class="fa fa-exchange"></i> {{ lang._('Change Gateway') }}</button>
                    <button type="button" class="btn btn-sm btn-danger" id="batchDeleteBtn"><i class="fa fa-trash"></i> {{ lang._('Delete') }}</button>
                </div>
            </div>
        </div>

        <table id="grid-entries" class="table table-condensed table-hover table-striped" data-editDialog="DialogEntry" data-editAlert="EntryChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="5em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('On') }}</th>
                    <th data-column-id="zoneName" data-type="string" data-formatter="record">{{ lang._('Record') }}</th>
                    <th data-column-id="recordType" data-width="5em" data-type="string">{{ lang._('Type') }}</th>
                    <th data-column-id="currentIp" data-type="string">{{ lang._('Current IP') }}</th>
                    <th data-column-id="primaryGateway" data-type="string" data-formatter="gateway">{{ lang._('Gateway') }}</th>
                    <th data-column-id="status" data-width="8em" data-type="string" data-formatter="status">{{ lang._('Status') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                        <button type="button" class="btn btn-xs btn-info" id="refreshAllBtn"><i class="fa fa-refresh"></i> {{ lang._('Refresh Status') }}</button>
                        <button type="button" class="btn btn-xs btn-success" id="updateNowBtn"><i class="fa fa-bolt"></i> {{ lang._('Update Now') }}</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog", ['fields': entryForm, 'id': 'DialogEntry', 'label': lang._('Edit Entry')]) }}
