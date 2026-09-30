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
                responseHandler: CrowdSec.messageHandler($("#bouncers_message")),
            }
        });

        // the bouncer of this firewall is registered on the remote LAPI, not in the table below
        ajaxGet("/api/crowdsec/status/get", {}, function (data, status) {
            if (status !== "success" || !data.bouncer_remote_lapi || !data.bouncer) {
                return;
            }
            $("#remote_bouncer_url").text(data.bouncer.api_url || '');
            $("#remote_bouncer_state")
                .text(data.bouncer.running ? "{{ lang._('running') }}" : "{{ lang._('not running') }}")
                .toggleClass("text-success", data.bouncer.running)
                .toggleClass("text-danger", !data.bouncer.running);
            $("#remote_bouncer").removeClass("hidden");
        });

        $("#testAct").click(function () {
            CrowdSec.testConnection($("#test_result"));
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<div class="content-box hidden" id="remote_bouncer" style="margin-bottom: 20px; padding: 10px;">
    <h3>{{ lang._("This firewall's bouncer") }}</h3>
    <p>
        {{ lang._('This bouncer is registered on the remote LAPI at') }} <b id="remote_bouncer_url"></b>
        {{ lang._('and is therefore not listed below.') }}
        {{ lang._('Service:') }} <b id="remote_bouncer_state"></b>.
        <a href="/ui/crowdsec/overview/index">{{ lang._('Details') }}</a>
    </p>
    <button class="btn btn-xs btn-default" id="testAct" type="button">{{ lang._('Test connection') }}</button>
    <span id="test_result"></span>
</div>

<h3>{{ lang._('Bouncers registered on the local LAPI') }}</h3>
<div class="alert alert-info hidden" role="alert" id="bouncers_message"></div>

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
