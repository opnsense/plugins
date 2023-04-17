{#
 #
 # Copyright © 2023 Cloudfence
 # Copyright (c) 2019 Deciso B.V.
 #  All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 # this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 # this list of conditions and the following disclaimer in the documentation
 # and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #
 #}


<div class="alert alert-warning hidden" role="alert" id="registration_alert">
    <button class="btn btn-primary pull-right hidden" id="registerAgent_btn" type="button">{{ lang._('Register agent with Wazuh Manager') }} <i id="registerAgent_progress"></i></button>
    <div style="margin-top: 8px;">{{ lang._('Pending: This Wazuh agent is not connected to Wazuh manager yet.') }}</div>
</div>
<div class="alert alert-info hidden" role="alert" id="registration_success">
    {{ lang._('Connected: This Wazuh agent is successfully connected to Wazuh manager!') }}
</div>


<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
    <hr />
    <div class="col-md-6">
        <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAct_progress"></i></button>
    </div>
</div>



<script>
    function registerCheck() {
        ajaxCall(url="/api/wazuhagent/service/check", sendData={}, callback=function(data,status) {
            if (data['register'] == 'registered') {
                $("#registration_success").removeClass("hidden");
                $("#registerAgent_progress").removeClass("fa fa-spinner fa-pulse");
                $("#registerAgent_btn").addClass("hidden");
                $("#registration_alert").addClass("hidden");
            } else {
                $("#registration_alert").removeClass("hidden");
                $("#registerAgent_progress").removeClass("fa fa-spinner fa-pulse");
                $("#registerAgent_btn").removeClass("hidden");
                $("#registerAgent_btn").prop("disabled", false);
                $("#registration_success").addClass("hidden");
            }
        });
    }

    function agentRegister() {
        $("#registerAgent_progress").addClass("fa fa-spinner fa-pulse");
        $("#registerAgent_btn").prop("disabled", true);
        ajaxCall(url="/api/wazuhagent/service/register", sendData={}, callback=function(data,status) {
            setTimeout(registerCheck, 10000);
        });
    }

    $( document ).ready(function() {
        var data_get_map = {'frm_general_settings':"/api/wazuhagent/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        ajaxCall(url="/api/wazuhagent/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });
        // control service buttons
        updateServiceControlUI('wazuhagent');

        //check for registration status
        registerCheck();

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/wazuhagent/general/set", formid='frm_general_settings',callback_ok=function(){
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/wazuhagent/service/reconfigure", sendData={}, callback=function(data,status) {
                    ajaxCall(url="/api/wazuhagent/service/status", sendData={}, callback=function(data,status) {
                        updateServiceStatusUI(data['status']);
                    });
                    updateServiceControlUI('wazuhagent');
                    //check for registration status
                    registerCheck();
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

        // wazuh agent register button
        $("#registerAgent_btn").click(function(){
            // call function
            agentRegister();
        });
    });
</script>
