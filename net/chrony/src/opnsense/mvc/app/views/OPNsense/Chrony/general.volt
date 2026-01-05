{#
 # Copyright (c) 2019 Deciso B.V.
 # Copyright (c) 2020-2021 Michael Muenz <m.muenz@gmail.com>
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
 #}

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#chronysources">{{ lang._('Sources') }}</a></li>
    <li><a data-toggle="tab" href="#chronysourcestats">{{ lang._('Source Stats') }}</a></li>
    <li><a data-toggle="tab" href="#chronytracking">{{ lang._('Tracking') }}</a></li>
    <li><a data-toggle="tab" href="#chronyauthdata">{{ lang._('Auth Data') }}</a></li>
    <li><a data-toggle="tab" href="#chronyntpdata">{{ lang._('NTP Data') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="chronysources" class="tab-pane fade in">
        <pre id="listchronysources"></pre>
    </div>
    <div id="chronysourcestats" class="tab-pane fade in">
        <pre id="listchronysourcestats"></pre>
    </div>
    <div id="chronytracking" class="tab-pane fade in">
        <pre id="listchronytracking"></pre>
    </div>
    <div id="chronyauthdata" class="tab-pane fade in">
        <pre id="listchronyauthdata"></pre>
    </div>
    <div id="chronyntpdata" class="tab-pane fade in">
        <pre id="listchronyntpdata"></pre>
    </div>
</div>

<script>

// Put API call into a function, needed for auto-refresh
function update_chronysources() {
    ajaxCall(url="/api/chrony/service/chronysources", sendData={}, callback=function(data,status) {
        $("#listchronysources").text($('<div>').html(data['response']).text());
    });
}

function update_chronysourcestats() {
    ajaxCall(url="/api/chrony/service/chronysourcestats", sendData={}, callback=function(data,status) {
        $("#listchronysourcestats").text($('<div>').html(data['response']).text());
    });
}

// Put API call into a function, needed for auto-refresh
function update_chronytracking() {
    ajaxCall(url="/api/chrony/service/chronytracking", sendData={}, callback=function(data,status) {
        $("#listchronytracking").text(data['response']);
    });
}

function update_chronyauthdata() {
    ajaxCall(url="/api/chrony/service/chronyauthdata", sendData={}, callback=function(data,status) {
        $("#listchronyauthdata").text(data['response']);
    });
}

function update_chronyntpdata() {
    ajaxCall(url="/api/chrony/service/chronyntpdata", sendData={}, callback=function(data,status) {
        $("#listchronyntpdata").text(data['response']);
    });
}

    $(function() {
        var data_get_map = {'frm_general_settings':"/api/chrony/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

    updateServiceControlUI('chrony');

    // Call function update_neighbor with a auto-refresh of 5 seconds
    setInterval(update_chronysources, 5000);
    setInterval(update_chronysourcestats, 5000);
    setInterval(update_chronytracking, 5000);
    setInterval(update_chronyauthdata, 5000);
    setInterval(update_chronyntpdata, 5000);

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/chrony/general/set", formid='frm_general_settings',callback_ok=function(){
                $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/chrony/service/reconfigure", sendData={}, callback=function(data,status) {
                    updateServiceControlUI('chrony');
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

    });
</script>
