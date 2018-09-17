{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Michael Muenz <m.muenz@gmail.com>
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
<div class="alert alert-warning" role="alert" id="missing_clamav" style="display:none;min-height:65px;">
    <div style="margin-top: 8px;">{{ lang._('No ClamAV plugin found, please install via System > Firmware > Plugins.')}}</div>
</div>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#antivirus">{{ lang._('Antivirus') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="antivirus" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':antivirusForm,'id':'frm_antivirus_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary"  id="saveAct2" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct2_progress"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
    $( document ).ready(function() {
        var data_get_map = {'frm_general_settings':"/api/cicap/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        var data_get_map2 = {'frm_antivirus_settings':"/api/cicap/antivirus/get"};
        mapDataToFormUI(data_get_map2).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        ajaxCall(url="/api/cicap/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });

	// check if ClamAV plugin is installed
        ajaxCall(url="/api/cicap/service/checkclamav", sendData={}, callback=function(data,status) {
	    if (data == "0") {
                $('#missing_clamav').show();
            }
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/cicap/general/set", formid='frm_general_settings',callback_ok=function(){
		    $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/cicap/service/reconfigure", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/cicap/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                            });
			    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
        $("#saveAct2").click(function(){
            saveFormToEndpoint(url="/api/cicap/antivirus/set", formid='frm_antivirus_settings',callback_ok=function(){
		    $("#saveAct2_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/cicap/service/reconfigure", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/cicap/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                            });
			    $("#saveAct2_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
    });
</script>
