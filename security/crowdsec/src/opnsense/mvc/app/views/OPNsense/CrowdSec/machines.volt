{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    $(function() {
        $("#cscli_machines").UIBootgrid({
            search: '/api/crowdsec/machines/search/',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    "name": function(column, row) {
                        return row.machineId;
                    },
                    "ip_address": function(column, row) {
                        return row.ipAddress;
                    },
                    "created": function(column, row) {
                        return CrowdSec.formatters.datetime(row.created_at);
                    },
                    "last_seen": function(column, row) {
                        return CrowdSec.formatters.datetime(row.last_heartbeat);
                    },
                    "validated": function(column, row) {
                        return CrowdSec.formatters.yesno(row.isValidated);
                    }
                },
            }
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<table id="cscli_machines" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="name" data-formatter="name">Name</th>
            <th data-column-id="version">Version</th>
            <th data-column-id="validated" data-formatter="validated">Validated?</th>
            <th data-column-id="ip_address" data-formatter="ip_address">IP Address</th>
            <th data-column-id="created" data-formatter="created" data-visible="false">Created</th>
            <th data-column-id="last_seen" data-formatter="last_seen">Last Seen</th>
            <th data-column-id="os" data-visible="false">OS</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
