{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<style>
    #alertDetailBody .detail-label { width: 150px; }
    #alertDetailBody .detail-value-wrap { word-break: break-all; }
    #alertDetailBody h4 { margin-top: 20px; padding-left: 8px; }
    #alertDetailBody h5 { padding-left: 8px; }
</style>

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    $(function() {
        $("#cscli_alerts").UIBootgrid({
            search: '/api/crowdsec/alerts/search/',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    "created": CrowdSec.formatters.datetime,
                    "commands": function(column, row) {
                        return '<button type="button" class="btn btn-xs btn-default alert-inspect" ' +
                            'data-alert-id="' + row.id + '" title="Inspect">' +
                            '<span class="fa fa-fw fa-info-circle"></span></button>';
                    },
                },
            }
        });

        $(document).on('click', '.alert-inspect', function() {
            var alertId = $(this).data('alert-id');
            var $body = $('#alertDetailBody');

            $body.html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            $('#alertDetailModal').modal('show');

            $.getJSON('/api/crowdsec/alerts/get/' + alertId, function(data) {
                if (data.message) {
                    $body.html('<div class="alert alert-danger">' + data.message + '</div>');
                    return;
                }

                var a = data.alert;

                var html = '<table class="table table-condensed table-striped">' +
                    '<tbody>' +
                    '<tr><td class="detail-label"><strong>ID</strong></td><td>' + a.id + '</td></tr>' +
                    '<tr><td><strong>Date</strong></td><td>' + a.created_at + '</td></tr>' +
                    '<tr><td><strong>Machine</strong></td><td>' + a.machine_id + '</td></tr>' +
                    '<tr><td><strong>Simulation</strong></td><td>' + (a.simulated ? 'true' : 'false') + '</td></tr>' +
                    '<tr><td><strong>Remediation</strong></td><td>' + (a.remediation ? 'true' : 'false') + '</td></tr>' +
                    '<tr><td><strong>Reason</strong></td><td>' + a.scenario + '</td></tr>' +
                    '<tr><td><strong>Events Count</strong></td><td>' + a.events_count + '</td></tr>' +
                    '<tr><td><strong>Scope:Value</strong></td><td>' + a.scope_value + '</td></tr>' +
                    '<tr><td><strong>Country</strong></td><td>' + a.country + '</td></tr>' +
                    '<tr><td><strong>AS</strong></td><td>' + a.as_name + (a.as_number ? ' (AS' + a.as_number + ')' : '') + '</td></tr>' +
                    '<tr><td><strong>IP Range</strong></td><td>' + a.ip_range + '</td></tr>' +
                    '<tr><td><strong>Begin</strong></td><td>' + a.start_at + '</td></tr>' +
                    '<tr><td><strong>End</strong></td><td>' + a.stop_at + '</td></tr>' +
                    '<tr><td><strong>UUID</strong></td><td>' + a.uuid + '</td></tr>' +
                    '</tbody></table>';

                if (a.decisions && a.decisions.length > 0) {
                    html += '<h4>Active Decisions</h4>' +
                        '<table class="table table-condensed table-striped">' +
                        '<thead><tr>' +
                        '<th>ID</th><th>Scope:Value</th><th>Action</th><th>Expiration</th><th>Origin</th>' +
                        '</tr></thead><tbody>';

                    for (var i = 0; i < a.decisions.length; i++) {
                        var d = a.decisions[i];
                        html += '<tr>' +
                            '<td>' + d.id + '</td>' +
                            '<td>' + d.scope + '</td>' +
                            '<td>' + d.type + '</td>' +
                            '<td>' + d.duration + '</td>' +
                            '<td>' + d.origin + '</td>' +
                            '</tr>';
                    }

                    html += '</tbody></table>';
                }

                if (a.events && a.events.length > 0) {
                    html += '<h4>Events</h4>';

                    for (var i = 0; i < a.events.length; i++) {
                        var evt = a.events[i];
                        var meta = evt.meta || {};

                        if (a.events.length > 1) {
                            html += '<h5>Event ' + (i + 1) + ' — ' + $('<span>').text(evt.timestamp).html() + '</h5>';
                        }

                        html += '<table class="table table-condensed table-striped"><tbody>';

                        var keys = Object.keys(meta).sort();
                        for (var j = 0; j < keys.length; j++) {
                            var escaped = $('<span>').text(meta[keys[j]]).html();
                            html += '<tr><td class="detail-label"><strong>' + keys[j] + '</strong></td>' +
                                '<td class="detail-value-wrap">' + escaped + '</td></tr>';
                        }

                        html += '</tbody></table>';
                    }
                }

                $body.html(html);
            }).fail(function() {
                $body.html('<div class="alert alert-danger">Failed to retrieve alert details.</div>');
            });
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<table id="cscli_alerts" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="id" data-type="numeric" data-order="asc">ID</th>
            <th data-column-id="value">Value</th>
            <th data-column-id="reason">Reason</th>
            <th data-column-id="country">Country</th>
            <th data-column-id="as">AS</th>
            <th data-column-id="decisions">Decisions</th>
            <th data-column-id="created" data-formatter="created">Created</th>
            <th data-column-id="commands" data-formatter="commands" data-sortable="false">Details</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>

<div class="modal fade" id="alertDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Alert Details</h4>
            </div>
            <div class="modal-body" id="alertDetailBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
