{#
 # Copyright (c) 2025 github.com/mr-manuel
 # All rights reserved.
 #
 # License: BSD 2-Clause
 #}

<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
    <table class="table-responsive table table-striped">
        <tr>
            <td>
                <p>⚠️ Enabling this plugin will NOT automatically send mail notifications</p>
                <p><a id="help_for_general.install" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> To get alerted by mail, you have to set up <a href="/ui/monit" target="_blank">Monit</a> with a Service and Service Test that monitors the <code>/var/log/arpndplogging.log</code> logfile.</p>
                <p>Click <a id="help_for_general.install" href="#" class="showhelp">here</a> to display the mail alerting setup instructions at the end of this page.</p>
                <p>Do you like this plugin? Consider to <a href="https://github.md0.eu/links/opnsense-arp-ndp-logging" target="_blank">make a donation</a>.</p>
            </td>
        </tr>
    </table>
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
        <button class="btn pull-right" id="resetdbAct" type="button"><b>{{ lang._('Reset database') }}</b> <i id="resetdbAct_progress" class=""></i></button>
    </div>
    <div class="col-md-12 hidden" data-for="help_for_general.install">
        <hr />
        <h2>How to setup mail alerting</h2>
        <ol>
            <li>
                <p>Go to <code>Services</code> -&gt; <code>Monit</code> -&gt; <code>Settings</code> and then to the <code>General Settings</code> tab. Make sure you enabled Monit and populated the mail fields.</p>
            </li>
            <li>
                <p>Go to the <code>Alert Settings</code> tab. Make sure, you added an alert. If not add a new alert, fill out the fields and then save:</p>
            <ul>
            <li>
                <p>Enable alert: ☑</p>
            </li>
            <li>
                <p>Recipient: Insert the mail address where you want to receive the notifications</p>
            </li>
            <li>
                <p>Not on: ☐</p>
            </li>
            <li>
                <p>Events: <code>Nothing selected</code> or at least <code>Content failed</code></p>
            </li>
            <li>
                <p>Mail format:</p>
                <p>Copy the code below. Do not forget to replace <code>sender_mail_address@example.tld</code> with the mail from the account you set under <code>General Settings</code> -&gt; <code>Mail Server Username</code>.</p>
<pre>
from: sender_mail_address@example.tld
subject: Monit alert -- $EVENT: $SERVICE
message:
$EVENT: $SERVICE

# Host
$HOST

# Date
$DATE

# Action
$ACTION

# Description
$DESCRIPTION
</pre>
            </li>
            <li>
                <p>Reminder: leave empty</p>
            </li>
            <li>
                <p>Description: Not needed, but you can insert whatever you like</p>
            </li>
            </ul>
            </li>
            <li>
                <p>Go to the <code>Service Tests Settings</code> tab. Add a new test, fill out the fields and then save:</p>
                <ul>
                    <li>Name: <code>ArpNdpLogging_common</code> or whatever you like</li>
                    <li>Condition: <code>content = "detected"</code></li>
                    <li>Action: <code>Alert</code></li>
                </ul>
            </li>
            <li>
                <p>Go to the <code>Service Settings</code> tab. Add a new service, fill out the fields and then save:</p>
                <ul>
                    <li>Enable service checks: ☑</li>
                    <li>Name: <code>ArpNdpLogging_common</code> or whatever you like</li>
                    <li>Type: <code>File</code></li>
                    <li>Path: <code>/var/log/arpndplogging.log</code></li>
                    <li>Start: leave empty</li>
                    <li>Stop: leave empty</li>
                    <li>Tests: Select <code>ArpNdpLogging_common</code> or whatever you inserted in step 2</li>
                    <li>Depends: leave empty</li>
                    <li>Description: Not needed, but you can insert whatever you like</li>
                </ul>
            </li>
            <li>
                <p>Test it by connecting a new device or by clicking on the <code>Reset database</code> button on this page.</p>
            </li>
        </ol>
    </div>
</div>

<script>
    $(function() {
        var data_get_map = {'frm_general_settings':"/api/arpndplogging/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        updateServiceControlUI('arpndplogging');

        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/arpndplogging/general/set", formid='frm_general_settings',callback_ok=function(){
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/arpndplogging/service/reconfigure", sendData={}, callback=function(data,status) {
                    updateServiceControlUI('arpndplogging');
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

        $("#resetdbAct").click(function () {
            stdDialogConfirm(
                '{{ lang._('Confirm database reset') }}',
                '{{ lang._('Do you want to reset the database?') }}',
                '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                    $("#resetdbAct_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/arpndplogging/service/stop", sendData={}, callback=function(data,status) {
                        ajaxCall(url="/api/arpndplogging/service/resetdb", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/arpndplogging/service/start", sendData={}, callback=function(data,status) {
                            updateServiceControlUI('arpndplogging');
                            $("#resetdbAct_progress").removeClass("fa fa-spinner fa-pulse");
                        });
                    });
                });
            });
        });

    });
</script>
