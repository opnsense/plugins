{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.
#}

<script>
    $(document).ready(function() {
        // Variables to store fetched data
        var currentToken = '';
        var zonesData = [];
        var recordsData = [];

        // Initialize bootgrid for accounts table
        $("#grid-accounts").UIBootgrid({
            search: '/api/hclouddns/accounts/searchItem',
            get: '/api/hclouddns/accounts/getItem/',
            set: '/api/hclouddns/accounts/setItem/',
            add: '/api/hclouddns/accounts/addItem/',
            del: '/api/hclouddns/accounts/delItem/',
            toggle: '/api/hclouddns/accounts/toggleItem/',
            options: {
                formatters: {
                    commands: function(column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-edit" data-row-id="' + row.uuid + '"><span class="fa fa-pencil"></span></button> ' +
                               '<button type="button" class="btn btn-xs btn-default command-copy" data-row-id="' + row.uuid + '"><span class="fa fa-clone"></span></button> ' +
                               '<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="' + row.uuid + '"><span class="fa fa-trash-o"></span></button>';
                    },
                    rowtoggle: function(column, row) {
                        if (parseInt(row[column.id], 2) === 1) {
                            return '<span style="cursor: pointer;" class="fa fa-check-square-o command-toggle" data-value="1" data-row-id="' + row.uuid + '"></span>';
                        } else {
                            return '<span style="cursor: pointer;" class="fa fa-square-o command-toggle" data-value="0" data-row-id="' + row.uuid + '"></span>';
                        }
                    }
                }
            }
        });

        // Load zones button handler
        $(document).on('click', '#loadZonesBtn', function() {
            var token = $('#account\\.apiToken').val();
            if (!token) {
                BootstrapDialog.alert({
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('Please enter an API token first.') }}",
                    type: BootstrapDialog.TYPE_WARNING
                });
                return;
            }

            currentToken = token;
            $('#loadZonesBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Loading...") }}');

            ajaxCall('/api/hclouddns/hetzner/listZones', {token: token}, function(data, status) {
                $('#loadZonesBtn').prop('disabled', false).html('<i class="fa fa-cloud-download"></i> {{ lang._("Load Zones") }}');

                if (data && data.status === 'ok' && data.zones) {
                    zonesData = data.zones;
                    var $zoneSelect = $('#account\\.zoneId');
                    $zoneSelect.empty();
                    $zoneSelect.append('<option value="">{{ lang._("-- Select Zone --") }}</option>');

                    $.each(data.zones, function(i, zone) {
                        $zoneSelect.append('<option value="' + zone.id + '" data-name="' + zone.name + '">' + zone.name + ' (' + zone.records_count + ' records)</option>');
                    });

                    $zoneSelect.selectpicker('refresh');
                    BootstrapDialog.alert({
                        title: "{{ lang._('Success') }}",
                        message: "{{ lang._('Found') }} " + data.zones.length + " {{ lang._('zone(s).') }}",
                        type: BootstrapDialog.TYPE_SUCCESS
                    });
                } else {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Error') }}",
                        message: data && data.message ? data.message : "{{ lang._('Failed to load zones. Check your API token.') }}",
                        type: BootstrapDialog.TYPE_DANGER
                    });
                }
            });
        });

        // Zone selection change - auto-fill zone name and load records
        $(document).on('change', '#account\\.zoneId', function() {
            var selectedOption = $(this).find('option:selected');
            var zoneName = selectedOption.data('name') || '';
            $('#account\\.zoneName').val(zoneName);

            // Auto-load records when zone is selected
            if ($(this).val() && currentToken) {
                loadRecords(currentToken, $(this).val());
            }
        });

        // Load records button handler
        $(document).on('click', '#loadRecordsBtn', function() {
            var token = currentToken || $('#account\\.apiToken').val();
            var zoneId = $('#account\\.zoneId').val();

            if (!token || !zoneId) {
                BootstrapDialog.alert({
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('Please select a zone first.') }}",
                    type: BootstrapDialog.TYPE_WARNING
                });
                return;
            }

            loadRecords(token, zoneId);
        });

        function loadRecords(token, zoneId) {
            $('#loadRecordsBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Loading...") }}');

            ajaxCall('/api/hclouddns/hetzner/listRecords', {token: token, zone_id: zoneId}, function(data, status) {
                $('#loadRecordsBtn').prop('disabled', false).html('<i class="fa fa-list"></i> {{ lang._("Load Records") }}');

                if (data && data.status === 'ok' && data.records) {
                    recordsData = data.records;
                    var $recordsSelect = $('#account\\.records');
                    $recordsSelect.empty();

                    $.each(data.records, function(i, record) {
                        var label = record.name + ' (' + record.type + ') - ' + record.value;
                        var value = record.name + ':' + record.type;
                        $recordsSelect.append('<option value="' + value + '">' + label + '</option>');
                    });

                    $recordsSelect.selectpicker('refresh');
                } else {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Error') }}",
                        message: data && data.message ? data.message : "{{ lang._('Failed to load records.') }}",
                        type: BootstrapDialog.TYPE_DANGER
                    });
                }
            });
        }

        // Validate token button
        $(document).on('click', '#validateTokenBtn', function() {
            var token = $('#account\\.apiToken').val();
            if (!token) {
                BootstrapDialog.alert({
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('Please enter an API token.') }}",
                    type: BootstrapDialog.TYPE_WARNING
                });
                return;
            }

            $('#validateTokenBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            ajaxCall('/api/hclouddns/hetzner/validateToken', {token: token}, function(data, status) {
                $('#validateTokenBtn').prop('disabled', false).html('<i class="fa fa-check"></i>');

                if (data && data.valid) {
                    currentToken = token;
                    BootstrapDialog.alert({
                        title: "{{ lang._('Valid Token') }}",
                        message: data.message || "{{ lang._('Token is valid.') }}",
                        type: BootstrapDialog.TYPE_SUCCESS
                    });
                } else {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Invalid Token') }}",
                        message: data && data.message ? data.message : "{{ lang._('Token validation failed.') }}",
                        type: BootstrapDialog.TYPE_DANGER
                    });
                }
            });
        });

        // Hook into dialog open to add custom buttons
        $(document).on('opnsense_bootgrid_mapped', function(e, data) {
            // Add buttons after API token field
            var tokenField = $('#account\\.apiToken').closest('tr');
            if (tokenField.length && !$('#validateTokenBtn').length) {
                var btnHtml = '<td colspan="2" style="padding: 5px 0 15px 0;">' +
                    '<button type="button" class="btn btn-default btn-xs" id="validateTokenBtn"><i class="fa fa-check"></i> {{ lang._("Validate") }}</button> ' +
                    '<button type="button" class="btn btn-primary btn-xs" id="loadZonesBtn"><i class="fa fa-cloud-download"></i> {{ lang._("Load Zones") }}</button>' +
                    '</td>';
                tokenField.after('<tr>' + btnHtml + '</tr>');
            }

            // Add load records button after zone selection
            var zoneField = $('#account\\.zoneId').closest('tr');
            if (zoneField.length && !$('#loadRecordsBtn').length) {
                var recordsBtnHtml = '<td colspan="2" style="padding: 5px 0 15px 0;">' +
                    '<button type="button" class="btn btn-default btn-xs" id="loadRecordsBtn"><i class="fa fa-list"></i> {{ lang._("Load Records") }}</button>' +
                    '</td>';
                var zoneNameField = $('#account\\.zoneName').closest('tr');
                zoneNameField.after('<tr>' + recordsBtnHtml + '</tr>');
            }
        });
    });
</script>

<div class="tab-content content-box">
    <div id="accounts" class="tab-pane fade in active">
        <table id="grid-accounts" class="table table-condensed table-hover table-striped" data-editDialog="DialogAccount" data-editAlert="AccountChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="zoneName" data-type="string">{{ lang._('Zone') }}</th>
                    <th data-column-id="records" data-type="string">{{ lang._('Records') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog", ['fields': accountForm, 'id': 'DialogAccount', 'label': lang._('Edit Account')]) }}
