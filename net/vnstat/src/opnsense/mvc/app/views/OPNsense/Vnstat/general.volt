{#
 # Copyright (c) 2014-2018 Deciso B.V.
 # Copyright (c) 2018 Michael Muenz <m.muenz@gmail.com>
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

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#hourly">{{ lang._('Hourly Statistics') }}</a></li>
    <li><a data-toggle="tab" href="#daily">{{ lang._('Daily Statistics') }}</a></li>
    <li><a data-toggle="tab" href="#monthly">{{ lang._('Monthly Statistics') }}</a></li>
    <li><a data-toggle="tab" href="#yearly">{{ lang._('Yearly Statistics') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
                <button class="btn pull-right" id="resetdbAct" type="button"><b>{{ lang._('Reset') }}</b> <i id="resetdbAct_progress" class=""></i></button>
            </div>
        </div>
    </div>
    <div id="hourly" class="tab-pane fade in">
      <pre id="listhourly"></pre>
    </div>
    <div id="daily" class="tab-pane fade in">
      <pre id="listdaily"></pre>
    </div>
    <div id="monthly" class="tab-pane fade in">
      <pre id="listmonthly"></pre>
    </div>
    <div id="yearly" class="tab-pane fade in">
      <pre id="listyearly"></pre>
    </div>
</div>

<script>

// Put API call into a function, needed for auto-refresh
function update_hourly() {
    ajaxCall(url="/api/vnstat/service/hourly", sendData={}, callback=function(data,status) {
        $("#listhourly").text(data['response']);
    });
}
function update_daily() {
    ajaxCall(url="/api/vnstat/service/daily", sendData={}, callback=function(data,status) {
        $("#listdaily").text(data['response']);
    });
}
function update_monthly() {
    ajaxCall(url="/api/vnstat/service/monthly", sendData={}, callback=function(data,status) {
        $("#listmonthly").text(data['response']);
    });
}
function update_yearly() {
    ajaxCall(url="/api/vnstat/service/yearly", sendData={}, callback=function(data,status) {
        $("#listyearly").text(data['response']);
    });
}

$( document ).ready(function() {
    var data_get_map = {'frm_general_settings':"/api/vnstat/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    updateServiceControlUI('vnstat');

    // Call function update_neighbor with a auto-refresh of 3 seconds
    setInterval(update_hourly, 3000);
    setInterval(update_daily, 3000);
    setInterval(update_monthly, 3000);
    setInterval(update_yearly, 3000);

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/vnstat/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/vnstat/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('vnstat');
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
                ajaxCall(url="/api/vnstat/service/resetdb", sendData={}, callback=function(data,status) {
                    ajaxCall(url="/api/vnstat/service/reconfigure", sendData={}, callback=function(data,status) {
                    updateServiceControlUI('vnstat');
                    $("#resetdbAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });
});

</script>
