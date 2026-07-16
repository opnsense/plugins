{#
 #
 # Copyright (c) 2014-2019 Deciso B.V.
 # Copyright (c) 2018-2019 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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
    <li><a data-toggle="tab" href="#dnsbl">{{ lang._('DNSBL') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12 __mt">
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="dnsbl" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':dnsblForm,'id':'frm_dnsbl_settings'])}}
            <div class="col-md-12 __mt">
                <button class="btn btn-primary" id="saveAct_dnsbl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_dnsbl_progress"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let data_get_map = {
        'frm_general_settings': "/api/bind/general/get"
    };
    mapDataToFormUI(data_get_map).done(function(data) {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    mapDataToFormUI({'frm_dnsbl_settings': "/api/bind/dnsbl/get"}).done(function(data) {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    updateServiceControlUI('bind');

    $("#saveAct").click(function() {
        saveFormToEndpoint(url = "/api/bind/general/set", formid = 'frm_general_settings', callback_ok = function() {
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
                updateServiceControlUI('bind');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_dnsbl").click(function() {
        saveFormToEndpoint(url = "/api/bind/dnsbl/set", formid = 'frm_dnsbl_settings', callback_ok = function() {
            $("#saveAct_dnsbl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/bind/service/dnsbl", sendData = {}, callback = function(data, status) {
                ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
                    updateServiceControlUI('bind');
                    $("#saveAct_dnsbl_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });

    // update history on tab state and implement navigation
    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        history.pushState(null, null, e.target.hash);
    });
});
</script>
