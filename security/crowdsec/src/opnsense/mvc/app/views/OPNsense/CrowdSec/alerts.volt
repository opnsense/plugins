{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    $(function() {
        const decisionsByType = function(decisions) {
            const dectypes = {};
            if (!decisions) {
                return '';
            }
            decisions.map(function (decision) {
                // TODO ignore negative expiration?
                dectypes[decision.type] = dectypes[decision.type]
                    ? dectypes[decision.type] + 1
                    : 1;
            });
            let ret = '';
            for (const type in dectypes) {
                if (ret !== '') {
                    ret += ' ';
                }
                ret += type + ':' + dectypes[type];
            }
            return ret;
        };

        $("#cscli_alerts").UIBootgrid({
            search: '/api/crowdsec/alerts/search/',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    "created": function(column, row) {
                        return CrowdSec.formatters.datetime(row.created_at);
                    },
                    "value": function(column, row) {
                        return row.source.scope + (row.source.value ? ':' + row.source.value : '');
                    },
                    "reason": function(column, row) {
                        return row.scenario;
                    },
                    "country": function(column, row) {
                        return row.source.cn;
                    },
                    "as": function(column, row) {
                        return row.source.as_name;
                    },
                    "decisions": function(column, row) {
                        return decisionsByType(row.decisions);
                    },
                },
            }
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<table id="cscli_alerts" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="id" data-type="numeric" data-order="asc">ID</th>
            <th data-column-id="value" data-formatter="value">Value</th>
            <th data-column-id="reason" data-formatter="reason">Reason</th>
            <th data-column-id="country" data-formatter="country">Country</th>
            <th data-column-id="as" data-formatter="as">AS</th>
            <th data-column-id="decisions" data-formatter="decisions">Decisions</th>
            <th data-column-id="created_at" data-formatter="created">Created</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
