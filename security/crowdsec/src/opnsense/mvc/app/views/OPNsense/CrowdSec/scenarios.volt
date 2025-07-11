{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    $(function() {
        $("#cscli_scenarios").UIBootgrid({
            search: '/api/crowdsec/scenarios/search/',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    "localpath": function(column, row) {
                        return CrowdSec.formatters.trimpath(row[column.id]);
                    },
                },
            }
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<table id="cscli_scenarios" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="name">Name</th>
            <th data-column-id="status">Status</th>
            <th data-column-id="local_version">Version</th>
            <th data-column-id="local_path" data-formatter="localpath" data-visible="false">Path</th>
            <th data-column-id="description">Description</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
