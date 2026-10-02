{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2026 Michael Pihlblad #}

<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    "use strict";

    function serviceCell(service) {
        if (service.running) {
            return $('<span class="text-success">')
                .append('<i class="fa fa-play fa-fw"></i> ')
                .append(document.createTextNode("{{ lang._('running') }}" + (service.pid ? ' (pid ' + service.pid + ')' : '')));
        }
        return $('<span class="text-danger">')
            .append('<i class="fa fa-stop fa-fw"></i> ')
            .append(document.createTextNode("{{ lang._('not running') }}"));
    }

    function addRow($table, label, value) {
        const $value = $('<td>');
        if (value instanceof jQuery) {
            $value.append(value);
        } else {
            $value.text(value);
        }
        $table.append($('<tr>').append($('<td style="width:25%">').text(label), $value));
    }

    function renderStatus(data) {
        $("#overview_cards").empty();
        $("#msg_not_running").addClass("hidden");

        let not_running = [];

        if (data.crowdsec) {
            const $table = $('<table class="table table-condensed">');
            addRow($table, "{{ lang._('Service') }}", serviceCell(data.crowdsec));
            addRow($table, "{{ lang._('Version') }}", $('<span id="crowdsec_version">'));
            addRow($table, "{{ lang._('Log processor (IDS)') }}", data.agent_enabled ? "{{ lang._('enabled') }}" : "{{ lang._('disabled') }}");
            addRow($table, "{{ lang._('LAPI') }}", data.lapi_enabled ? "{{ lang._('enabled') }}" : "{{ lang._('disabled') }}");
            if (data.lapi) {
                addRow($table, "{{ lang._('Registered machines') }}", data.lapi.machines ?? '?');
                addRow($table, "{{ lang._('Registered bouncers') }}", data.lapi.bouncers ?? '?');
            }
            addCard("{{ lang._('CrowdSec') }}", $table);
            if (!data.crowdsec.running) {
                not_running.push("CrowdSec");
            }
            // "cscli version" output, first line is "version: vX.Y.Z-..."
            ajaxGet("/api/crowdsec/version/get", {}, function (version, status) {
                const match = status === "success" && typeof version === "string" && version.match(/^version:\s*(\S+)/m);
                $("#crowdsec_version").text(match ? match[1] : '');
            });
        }

        if (data.bouncer) {
            const $table = $('<table class="table table-condensed">');
            addRow($table, "{{ lang._('Service') }}", serviceCell(data.bouncer));
            addRow($table, "{{ lang._('Version') }}", data.bouncer.version || '');
            addRow($table, "{{ lang._('Connects to') }}",
                (data.bouncer_remote_lapi ? "{{ lang._('Remote LAPI') }}" : "{{ lang._('Local LAPI') }}") +
                (data.bouncer.api_url ? ' — ' + data.bouncer.api_url : ''));
            addRow($table, "{{ lang._('Connection') }}", $('<span id="test_result">').append(
                $('<button class="btn btn-xs btn-default" id="testAct" type="button">')
                    .text("{{ lang._('Test connection') }}")
            ));
            if (data.bouncer.blocklists) {
                addRow($table, "{{ lang._('Blocked addresses') }}",
                    'IPv4: ' + data.bouncer.blocklists.ipv4 + ', IPv6: ' + data.bouncer.blocklists.ipv6);
            }
            const $log = $('<pre style="max-height: 300px; overflow: auto;">').text((data.bouncer.log || []).join('\n'));
            addCard("{{ lang._('Firewall bouncer (remediation component)') }}", $table.add($('<h4>').text("{{ lang._('Recent log') }}")).add($log));
            if (!data.bouncer.running) {
                not_running.push("{{ lang._('Firewall bouncer') }}");
            }
        }

        if (!data.crowdsec && !data.bouncer) {
            $("#overview_cards").append($('<p>').text("{{ lang._('No CrowdSec component is enabled.') }}"));
        }

        if (not_running.length > 0) {
            $("#msg_not_running").removeClass("hidden").find("span").text(not_running.join(', '));
        }
    }

    function addCard(title, $content) {
        $("#overview_cards").append(
            $('<div class="content-box" style="margin-bottom: 20px; padding: 10px;">')
                .append($('<h3>').text(title), $content)
        );
    }

    function loadStatus() {
        ajaxGet("/api/crowdsec/status/get", {}, function (data, status) {
            if (status === "success") {
                renderStatus(data);
            }
        });
    }

    $(function() {
        loadStatus();
        $("#refreshAct").click(loadStatus);

        $("#overview_cards").on("click", "#testAct", function () {
            CrowdSec.testConnection($("#test_result"));
        });

        updateServiceControlUI('crowdsec');
    });
</script>

<div class="alert alert-danger hidden" role="alert" id="msg_not_running">
    {{ lang._('Enabled but not running:') }} <span></span>.
    {{ lang._('It may have stopped with an error; see the log below or restart it with the service buttons.') }}
</div>

<div id="overview_cards"></div>

<div>
    <button class="btn btn-default" id="refreshAct" type="button"><i class="fa fa-refresh fa-fw"></i> {{ lang._('Refresh') }}</button>
</div>
