{#

Copyright (C) 2023 Frank Wall
OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script>
    $( document ).ready(function() {
        var data_get_map = {'frm_GeneralSettings':"/api/autorecovery/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // save settings and start countdown
        $("#countdownAct").click(function(){
            saveFormToEndpoint(url="/api/autorecovery/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                // after successful save, start the countdown
                ajaxCall(url="/api/autorecovery/service/countdown", sendData={},callback=function(data,status) {
                    // reload page to show countdown timer
                    setTimeout(function () {
                        window.location.reload(true)
                    }, 1000);
                });
            });
        });

        // link save button to API set action
        $("#abortAct").click(function(){
            ajaxCall(url="/api/autorecovery/service/abort", sendData={},callback=function(data,status) {
                // reload page to hide countdown timer
                setTimeout(function () {
                    window.location.reload(true)
                }, 1000);
            });
        });

        // show reminder when config has pending changes
        function show_countdown_alert() {
            ajaxCall(url="/api/autorecovery/service/time", sendData={}, callback=function(data,status) {
                if (data['response'] && data['response'].trim() && data['response'] > 0) {
                    var countdowndate = new Date(data['response']*1000);
                    $("#countdownRunning").append(countdowndate.toLocaleString());
                    $("#countdownRunning").show();
                    $("#countdownAct").hide(); // hide button
                } else {
                    $("#countdownRunning").hide();
                    $("#abortAct").hide(); // hide button
                }
            });
        }
        show_countdown_alert();

        // hide configd field when not required
        $("#autorecovery\\.general\\.action").change(function () {
            var recovery_action = $(this).val();
            if (['restore_configd', 'configd'].includes(recovery_action)) {
                $(".table_configd").show();
            } else {
                $(".table_configd").hide();
            }
        });
        $("#autorecovery\\.general\\.action").change();
    });
</script>

<div  class="col-md-12">
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
</div>

<div class="col-md-12">
    <hr/>
    <div id="countdownRunning" class="alert alert-danger" style="display: none" role="alert">
      {{ lang._('WARNING! The countdown is on. Time of scheduled system recovery: ') }}
    </div>
    <p>{{ lang._('%sHOW IT WORKS:%s') | format('<b>', '</b>') }}</p>
    <ul>
      <li>{{ lang._("Enter the countdown and choose a recovery action.") }}</li>
      <li>{{ lang._("Hit the Start Countdown button.") }}</li>
      <li>{{ lang._("Auto Recovery automatically creates a configuration backup and starts the countdown.") }}</li>
      <li>{{ lang._("The GUI will refresh and display the recovery time.") }}</li>
      <li>{{ lang._("When the countdown reaches 0, the selected recovery actions are performed.") }}</li>
      <li>{{ lang._("Depending on the selected recovery action, the previous configuration will be restored and all configuration changes since starting the countdown are lost.") }}</li>
    </ul>
    <hr/>
    {{ lang._('%sPLEASE NOTE:%s Auto Recovery is a community plugin without support or guarantees. Auto Recovery can only restore the OPNsense system configuration to a previous state. It does not restore any other files nor does it revert any filesystem modifications. It certainly is not meant to replace a backup, nor does it protect against failed software upgrades.') | format('<b>', '</b>') }}
    <hr/>
    <button class="btn btn-primary" id="countdownAct" type="button"><b>{{ lang._('Start Countdown') }}</b></button>
    <button class="btn btn-primary" id="abortAct" type="button"><b>{{ lang._('Abort Countdown') }}</b></button>
</div>
