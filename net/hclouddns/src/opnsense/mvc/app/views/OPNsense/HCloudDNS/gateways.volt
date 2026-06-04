{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.
#}

<script>
    $(document).ready(function() {
        // Initialize bootgrid for gateways table
        $("#grid-gateways").UIBootgrid({
            search: '/api/hclouddns/gateways/searchItem',
            get: '/api/hclouddns/gateways/getItem/',
            set: '/api/hclouddns/gateways/setItem/',
            add: '/api/hclouddns/gateways/addItem/',
            del: '/api/hclouddns/gateways/delItem/',
            toggle: '/api/hclouddns/gateways/toggleItem/',
            options: {
                formatters: {
                    commands: function(column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-edit" data-row-id="' + row.uuid + '"><span class="fa fa-pencil"></span></button> ' +
                               '<button type="button" class="btn btn-xs btn-default command-copy" data-row-id="' + row.uuid + '"><span class="fa fa-clone"></span></button> ' +
                               '<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="' + row.uuid + '"><span class="fa fa-trash-o"></span></button>' +
                               '<button type="button" class="btn btn-xs btn-info command-health" data-row-id="' + row.uuid + '" title="{{ lang._("Check Health") }}"><span class="fa fa-heartbeat"></span></button>';
                    },
                    rowtoggle: function(column, row) {
                        if (parseInt(row[column.id], 2) === 1) {
                            return '<span style="cursor: pointer;" class="fa fa-check-square-o command-toggle" data-value="1" data-row-id="' + row.uuid + '"></span>';
                        } else {
                            return '<span style="cursor: pointer;" class="fa fa-square-o command-toggle" data-value="0" data-row-id="' + row.uuid + '"></span>';
                        }
                    },
                    status: function(column, row) {
                        var statusHtml = '<span class="label label-default">{{ lang._("Unknown") }}</span>';
                        return statusHtml;
                    }
                }
            }
        });

        // Health check button handler
        $(document).on('click', '.command-health', function() {
            var uuid = $(this).data('row-id');
            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.find('span').removeClass('fa-heartbeat').addClass('fa-spinner fa-spin');

            ajaxCall('/api/hclouddns/gateways/checkHealth/' + uuid, {}, function(data, status) {
                $btn.prop('disabled', false);
                $btn.find('span').removeClass('fa-spinner fa-spin').addClass('fa-heartbeat');

                if (data) {
                    var statusClass = data.status === 'up' ? 'success' : (data.status === 'down' ? 'danger' : 'warning');
                    var message = '<strong>{{ lang._("Status") }}:</strong> ' + data.status.toUpperCase() + '<br>' +
                                  '<strong>{{ lang._("IPv4") }}:</strong> ' + (data.ipv4 || '{{ lang._("N/A") }}') + '<br>' +
                                  '<strong>{{ lang._("IPv6") }}:</strong> ' + (data.ipv6 || '{{ lang._("N/A") }}');

                    if (data.pingOk !== null) {
                        message += '<br><strong>{{ lang._("Ping") }}:</strong> ' + (data.pingOk ? '✓' : '✗');
                    }
                    if (data.httpOk !== null) {
                        message += '<br><strong>{{ lang._("HTTP") }}:</strong> ' + (data.httpOk ? '✓' : '✗');
                    }

                    BootstrapDialog.show({
                        type: statusClass === 'success' ? BootstrapDialog.TYPE_SUCCESS :
                              (statusClass === 'danger' ? BootstrapDialog.TYPE_DANGER : BootstrapDialog.TYPE_WARNING),
                        title: "{{ lang._('Gateway Health Check') }}",
                        message: message,
                        buttons: [{
                            label: "{{ lang._('Close') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                }
            });
        });

        // Refresh all gateway status
        $('#refreshGatewaysBtn').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Checking...") }}');

            ajaxCall('/api/hclouddns/gateways/status', {}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> {{ lang._("Refresh Status") }}');
                $("#grid-gateways").bootgrid('reload');

                if (data && data.gateways) {
                    var statusHtml = '<table class="table table-condensed"><thead><tr>' +
                        '<th>{{ lang._("Gateway") }}</th><th>{{ lang._("Status") }}</th><th>{{ lang._("IPv4") }}</th><th>{{ lang._("IPv6") }}</th>' +
                        '</tr></thead><tbody>';

                    $.each(data.gateways, function(uuid, gw) {
                        var statusClass = gw.status === 'up' ? 'success' : (gw.status === 'down' ? 'danger' : 'default');
                        statusHtml += '<tr>' +
                            '<td>' + uuid.substr(0, 8) + '...</td>' +
                            '<td><span class="label label-' + statusClass + '">' + gw.status.toUpperCase() + '</span></td>' +
                            '<td>' + (gw.ipv4 || '-') + '</td>' +
                            '<td>' + (gw.ipv6 || '-') + '</td>' +
                            '</tr>';
                    });

                    statusHtml += '</tbody></table>';

                    BootstrapDialog.show({
                        title: "{{ lang._('Gateway Status') }}",
                        message: statusHtml,
                        buttons: [{
                            label: "{{ lang._('Close') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                }
            });
        });
    });
</script>

<div class="tab-content content-box">
    <div id="gateways" class="tab-pane fade in active">
        <div class="content-box-main">
            <div class="table-responsive">
                <div class="col-md-12">
                    <h2>{{ lang._('Gateways') }}</h2>
                    <p class="text-muted">{{ lang._('Configure WAN interfaces for dynamic DNS updates. Each gateway can have its own IP detection method and health check settings.') }}</p>
                </div>
            </div>
        </div>
        <table id="grid-gateways" class="table table-condensed table-hover table-striped" data-editDialog="DialogGateway" data-editAlert="GatewayChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="priority" data-width="6em" data-type="string">{{ lang._('Priority') }}</th>
                    <th data-column-id="checkipMethod" data-type="string">{{ lang._('IP Method') }}</th>
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
                        <button type="button" class="btn btn-xs btn-info" id="refreshGatewaysBtn"><i class="fa fa-refresh"></i> {{ lang._('Refresh Status') }}</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog", ['fields': gatewayForm, 'id': 'DialogGateway', 'label': lang._('Edit Gateway')]) }}
