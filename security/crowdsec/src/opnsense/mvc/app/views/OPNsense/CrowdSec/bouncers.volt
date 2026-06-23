{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    $(function() {
        $("#cscli_bouncers").UIBootgrid({
            search: '/api/crowdsec/bouncers/search/',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    "created": CrowdSec.formatters.datetime,
                    "last_seen": CrowdSec.formatters.datetime,
                    "valid": CrowdSec.formatters.yesno,
                },
            }
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<table id="cscli_bouncers" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="name">Name</th>
            <th data-column-id="type">Type</th>
            <th data-column-id="version">Version</th>
            <th data-column-id="created" data-formatter="created" data-visible="false">Created</th>
            <th data-column-id="valid" data-formatter="valid">Valid</th>
            <th data-column-id="ip_address">IP Address</th>
            <th data-column-id="last_seen" data-formatter="last_seen">Last Seen</th>
            <th data-column-id="os" data-visible="false">OS</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
