{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    $(function() {
        $("#cscli_decisions").UIBootgrid({
            search: '/api/crowdsec/decisions/search/',
            del: '/api/crowdsec/decisions/del/',
            datakey: "id",
            options: {
                formatters: {
                    "source": function(column, row) {
                        return row.origin;
                    },
                    "scope_value": function(column, row) {
                        return row.scope + (row.value ? ':' + row.value : '');
                    },
                    "reason": function(column, row) {
                        return row.scenario;
                    },
                    "action": function(column, row) {
                        return row.type;
                    },
                    "country": function(column, row) {
                        return row.alert_source.cn;
                    },
                    "as": function(column, row) {
                        return row.alert_source.as_name;
                    },
                    "expiration": function(column, row) {
                        return row.duration;
                    },
                },
            }
        });

        updateServiceControlUI('crowdsec');
    });
</script>

Note: the decisions coming from the CAPI (signals collected by the CrowdSec users) do not appear here.
To show them, use <code>cscli decisions list -a</code> in a shell.

<table id="cscli_decisions" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="id" data-type="numeric" data-visible="false" data-identifier="true" data-order="asc">ID</th>
            <th data-column-id="source" data-formatter="source" data-visible="false">Source</th>
            <th data-column-id="scope_value" data-formatter="scope_value">Scope:Value</th>
            <th data-column-id="reason" data-formatter="reason">Reason</th>
            <th data-column-id="action" data-formatter="action" data-visible="false">Action</th>
            <th data-column-id="country" data-formatter="country">Country</th>
            <th data-column-id="as" data-formatter="as">AS</th>
            <th data-column-id="events_count" data-type="numeric">Events</th>
            <th data-column-id="expiration" data-formatter="expiration">Expiration</th>
            <th data-column-id="alert_id" data-type="numeric" data-visible="false">Alert&nbsp;ID</th>
            <th data-column-id="commands" data-formatter="commands" data-sortable="false">Commands</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
            <tr>
                <td/>
                <td>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                        <span class="fa fa-trash-o fa-fw"></span>
                    </button>
                </td>
            </tr>
    </tfoot>
</table>
