{#
 # Copyright (C) 2024 Traz Technologies Inc.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#status">{{ lang._('Status') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div class="alert alert-info" role="alert" style="margin: 15px;">
                <p>
                    {{ lang._('Flowtriq detects DDoS attacks by analyzing NetFlow data from your firewall.') }}
                    {{ lang._('Configure the connection to your ftagent host below, then visit') }}
                    <a href="https://flowtriq.com/dashboard" target="_blank">flowtriq.com/dashboard</a>
                    {{ lang._('to view detected attacks and manage alerting.') }}
                </p>
                <p style="margin-top: 8px; margin-bottom: 0;">
                    {{ lang._('Need an ftagent host?') }}
                    {{ lang._('Install ftagent on any Linux server:') }}
                    <code>pip install ftagent</code>
                    &mdash;
                    <a href="https://flowtriq.com/docs?section=flow-collector" target="_blank">{{ lang._('Documentation') }}</a>
                </p>
            </div>
            {{ partial("layout_partials/base_form",['fields':settingsForm,'id':'frm_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
                <button class="btn btn-default pull-right" id="testAct" type="button"><b>{{ lang._('Test Connection') }}</b> <i id="testAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="status" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div class="col-md-12" style="padding: 15px;">
                <h3>{{ lang._('softflowd Status') }}</h3>
                <pre id="softflowd_status">{{ lang._('Loading...') }}</pre>
                <hr />
                <button class="btn btn-default" id="refreshStatus" type="button"><b>{{ lang._('Refresh') }}</b> <i id="refreshStatus_progress"></i></button>
            </div>
        </div>
    </div>
</div>

<script>

function updateStatus() {
    $("#refreshStatus_progress").addClass("fa fa-spinner fa-pulse");
    ajaxCall(url="/api/flowtriq/service/status", sendData={}, callback=function(data, status) {
        if (data['status']) {
            $("#softflowd_status").text(data['status']);
        } else {
            $("#softflowd_status").text("Service not running");
        }
        $("#refreshStatus_progress").removeClass("fa fa-spinner fa-pulse");
    });
}

$( document ).ready(function() {
    var data_get_map = {'frm_settings':"/api/flowtriq/settings/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('flowtriq');
    });

    updateStatus();

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/flowtriq/settings/set", formid='frm_settings', callback_ok=function(){
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/flowtriq/service/reconfigure", sendData={}, callback=function(data, status) {
                updateServiceControlUI('flowtriq');
                updateStatus();
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#testAct").click(function(){
        $("#testAct_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url="/api/flowtriq/service/test", sendData={}, callback=function(data, status) {
            if (data['response']) {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_INFO,
                    title: "{{ lang._('Connection Test') }}",
                    message: $('<pre/>').text(data['response']),
                    draggable: true
                });
            }
            $("#testAct_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    $("#refreshStatus").click(function(){
        updateStatus();
    });

    if(window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });
});

</script>
