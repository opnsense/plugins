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
                responseHandler: CrowdSec.messageHandler($("#decisions_message")),
            },
        });

        ajaxGet("/api/crowdsec/general/get", {}, function (data, status) {
            const general = (status === "success" && data.general) || {};
            const bouncerRemote = general.firewall_bouncer_enabled === "1" &&
                general.lapi_manual_configuration !== "1" &&
                ((general.bouncer_lapi || {}).remote || {}).selected === 1;
            $("#msg_not_enforced").toggleClass("hidden", !bouncerRemote);
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<div class="alert alert-warning hidden" role="alert" id="msg_not_enforced">
    {{ lang._('The remediation component connects to a remote LAPI: these decisions of the local LAPI are not blocked by this firewall.') }}
</div>
<div class="alert alert-info hidden" role="alert" id="decisions_message"></div>

Note: the decisions coming from the CAPI (signals collected by the CrowdSec users) do not appear here.
To show them, use <code>cscli decisions list -a</code> in a shell.

<table id="cscli_decisions" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="id" data-type="numeric" data-visible="false" data-order="asc">ID</th>
            <th data-column-id="source" data-visible="false">Source</th>
            <th data-column-id="scope_value">Scope:Value</th>
            <th data-column-id="reason">Reason</th>
            <th data-column-id="action" data-visible="false">Action</th>
            <th data-column-id="country">Country</th>
            <th data-column-id="as">AS</th>
            <th data-column-id="events_count" data-type="numeric">Events</th>
            <th data-column-id="expiration">Expiration</th>
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
