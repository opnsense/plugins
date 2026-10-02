{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/CrowdSec/crowdsec-misc.js"></script>
<script>
    // the stored API key is never sent to the browser, only whether it is set
    function updateKeyPlaceholder() {
        ajaxGet("/api/crowdsec/status/key", {}, function (data, status) {
            $('input[id="general.remote_bouncer_api_key"]').attr('placeholder',
                status === "success" && data.set ? "{{ lang._('Key is set — leave empty to keep') }}" : '');
        });
    }

    function formRow(field) {
        return $('tr[id="row_general.' + field + '"]');
    }

    function isChecked(field) {
        return $('input[id="general.' + field + '"]').is(':checked');
    }

    // show the settings relevant for the selected components, and warn
    // about combinations where decisions are not enforced here
    function updateSettingsForm() {
        const manual = isChecked('lapi_manual_configuration');
        const lapi = isChecked('lapi_enabled');
        const bouncer = isChecked('firewall_bouncer_enabled');
        const bouncerRemote = bouncer && $('select[id="general.bouncer_lapi"]').val() === 'remote';

        formRow('bouncer_lapi').toggle(bouncer);
        formRow('remote_lapi_url').toggle(bouncerRemote);
        formRow('remote_bouncer_api_key').toggle(bouncerRemote);

        $("#warnLapiNotEnforced").toggleClass("hidden", !(bouncerRemote && lapi && !manual));
        $("#warnAgentWithoutLapi").toggleClass("hidden", !(isChecked('agent_enabled') && !lapi && !manual));
    }

    $( document ).ready(function() {
        const data_get_map = {'frm_GeneralSettings':"/api/crowdsec/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            $('.selectpicker').selectpicker('refresh');
            updateSettingsForm();
        });
        updateKeyPlaceholder();

        $('#frm_GeneralSettings').on('change', 'input, select', updateSettingsForm);

        // tests the applied settings, from the firewall itself
        $('input[id="general.remote_bouncer_api_key"]').after(
            $('<div style="margin-top: 5px;">').append(
                $('<button class="btn btn-xs btn-default" id="testAct" type="button">')
                    .text("{{ lang._('Test connection (applied settings)') }}")
                    .click(function () {
                        CrowdSec.testConnection($("#test_result"));
                    }),
                ' ',
                $('<span id="test_result">')
            )
        );

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/crowdsec/general/set",formid='frm_GeneralSettings',callback_ok=function(){
                $("#settingsSavedMsg").text("Saving settings....").removeClass("hidden");
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/crowdsec/service/reconfigure", sendData={},callback=function(data,status) {
                    $("#settingsSavedMsg").html(
                        '<i class="fa fa-check text-success"></i> Settings have been saved, services restarted.'
                    ).removeClass("hidden");
                    $('input[id="general.remote_bouncer_api_key"]').val('');
                    updateKeyPlaceholder();
                });
            });
        });

        if(window.location.hash !== "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
    });
</script>

<style type="text/css">
#introduction a.btn-info {
  color: black;
  margin: 3px;
}

.tab-pane {
  margin: 10px;
}
</style>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" id="introduction-tab" href="#introduction"><b>Introduction</b></a></li>
    <li><a data-toggle="tab" id="settings-tab" href="#settings"><b>Settings</b></a></li>
</ul>

<div class="content-box tab-content">
    <div id="introduction" class="tab-pane fade in active">
        <h1>Introduction</h1>

        <p>This plugin installs a CrowdSec agent/<a href="https://doc.crowdsec.net/docs/next/local_api/intro">LAPI</a>
        node, and a <a href="https://docs.crowdsec.net/docs/bouncers/firewall/">Firewall Bouncer</a>.</p>

        <p>Out of the box, by enabling them in the "Settings" tab, they can protect the OPNsense server
        by receiving thousands of IP addresses of active attackers, which are immediately banned at the
        firewall level. In addition, the logs of the ssh service and OPNsense administration interface are
        analyzed for possible brute-force attacks; any such scenario triggers a ban and is reported to the
        CrowdSec Central API
        (meaning <a href="https://docs.crowdsec.net/docs/concepts/">timestamp, scenario, attacking IP</a>).</p>

        <p>Other attack behaviors can be recognized on the OPNsense server and its plugins, or
        <a href="https://doc.crowdsec.net/docs/next/user_guides/multiserver_setup">any other agent</a>
        connected to the same LAPI node. Other types of remediation are possible (ex. captcha test for scraping attempts).</p>

        We recommend you to <a href="https://app.crowdsec.net/">register to the Console</a>. This helps you manage your instances,
        and us to have better overall metrics.

        <p>Please refer to the <a href="https://crowdsec.net/blog/category/tutorial/">tutorials</a> to explore
        the possibilities.</p>

        <p>To block the decisions of a LAPI on another machine, set "Remediation component connects to" to
        "Remote LAPI" on the Settings tab.</p>

        <p>For the latest plugin documentation, including how to use it with an external LAPI, see <a
        href="https://docs.crowdsec.net/u/getting_started/installation/opnsense">Install
        CrowdSec (OPNsense)</a></p>

        <p>A few remarks:</p>

        <ul>
            <li>
                New acquisition files go under <code>/usr/local/etc/crowdsec/acquis.d</code>. See opnsense.yaml for details.
                The option <code>poll_without_inotify: true</code> is required if the log sources are symlinks (which
                is the case for most opnsense logs).
            </li>
            <li>
                If your OPNsense is &lt;22.1, you must check "Disable circular logs" in the Settings menu for the
                ssh and web-auth parsers to work. If you upgrade to 22.1, it will be done automatically.
                See <a href="https://github.com/crowdsecurity/opnsense-plugin-crowdsec/blob/main/src/etc/crowdsec/acquis.d/opnsense.yaml">acquis.d/opnsense.yaml</a>
            </li>
            <li>
                At the moment, the CrowdSec package for OPNsense is fully functional on the
                command line but its web interface is limited; you can only list the installed objects and revoke
                <a href="https://docs.crowdsec.net/docs/user_guides/decisions_mgmt/">decisions</a>, see the status
                of the components on the Overview page, and connect the remediation component to a remote LAPI.
                For anything else you need the shell or the <a href="https://app.crowdsec.net">CrowdSec Console</a>.
            </li>
            <li>
                Do not enable/start the agent and bouncer services with <code>sysrc</code> or <code>/etc/rc.conf</code>
                like you would on vanilla freebsd, the plugin takes care of that.
            </li>
            <li>
                The parsers, scenarios and all plugins from the Hub are periodically upgraded. The
                <a href="https://hub.crowdsec.net/author/crowdsecurity/collections/freebsd">crowdsecurity/freebsd</a> and
                <a href="https://hub.crowdsec.net/author/crowdsecurity/collections/opnsense">crowdsecurity/opnsense</a>
                collections are installed by default.
            </li>
        </ul>

        <div>
            <a class="btn btn-default btn-info" href="https://doc.crowdsec.net/docs/intro">
                Documentation
            </a>
            <a class="btn btn-default btn-info" href="https://crowdsec.net/blog/">
                Blog
            </a>
            <a class="btn btn-default btn-info" href="https://app.crowdsec.net/">
                Console
            </a>
            <a class="btn btn-default btn-info" href="https://hub.crowdsec.net/">
                CrowdSec Hub
            </a>
        </div>

        <h1>Installation</h1>

        <p>
            On the Settings tab, you can expose CrowdSec to the LAN for other servers by changing `LAPI listen address`.
            Otherwise, leave the default value.
        </p>

        <p>
            Select the first three checkboxes: IDS, LAPI and IPS. Click Apply. If you need to restart, you can do so
            from the <a href="/ui/core/service">System > Diagnostics > Services</a> page.
        </p>

        <p>
            To only block the decisions of a LAPI on another machine, select only IPS, set "Remediation component
            connects to" to "Remote LAPI", enter the URL of the remote LAPI and the API key of a bouncer registered
            on it. Click Apply.
        </p>

        <h1>Test the plugin</h1>

        <p>
            A quick way to test that everything is working correctly is to
            execute the following command.
        </p>

        <p>
            Your ssh session should freeze and you should be kicked out from
            the firewall. You will not be able to connect to it (from the same
            IP address) for two minutes.
        </p>

        <p>
            It might be a good idea to have a secondary IP from which you can
            connect, should anything go wrong.
        </p>

        <pre><code>[root@OPNsense ~]# cscli decisions add -t ban -d 2m -i &lt;your_ip_address&gt;</code></pre>

        <p>
            This is a more secure way to test than attempting to brute-force
            yourself: the default ban period is 4 hours, and Crowdsec reads the
            logs from the beginning, so it could ban you even if you failed ssh
            login 10 times in 30 seconds two hours before installing it.
        </p>

        <p>
            When the remediation component connects to a remote LAPI, add the test decision on that LAPI
            instead. The address then appears under Decisions, "Blocked by this firewall", and in the blocked
            addresses count on the Overview page.
        </p>

        <div>
            <a class="btn btn-default btn-info" href="https://github.com/crowdsecurity/crowdsec">
                GitHub
            </a>
            <a class="btn btn-default btn-info" href="https://discourse.crowdsec.net/">
                Discourse
            </a>
            <a class="btn btn-default btn-info" href="https://discord.com/invite/wGN7ShmEE8">
                Discord
            </a>
            <a class="btn btn-default btn-info" href="https://twitter.com/Crowd_Security">
                Twitter
            </a>
        </div>
    </div>

    <div id="settings" class="tab-pane fade active">
        <div class="alert alert-info hidden" role="alert" id="settingsSavedMsg">
        </div>
        <div class="alert alert-warning hidden" role="alert" id="warnLapiNotEnforced">
            {{ lang._('The remediation component connects to a remote LAPI: the decisions of the local LAPI are not blocked by this firewall.') }}
        </div>
        <div class="alert alert-warning hidden" role="alert" id="warnAgentWithoutLapi">
            {{ lang._('The log processor is enabled but the local LAPI is not. Unless you connect the log processor to a remote LAPI with "Manual LAPI configuration", it has no LAPI to send its alerts to.') }}
        </div>
        <div  class="col-md-12">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
        </div>

        <div class="col-md-12">
            <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Apply') }}</b></button>
        </div>
    </div>
</div>
