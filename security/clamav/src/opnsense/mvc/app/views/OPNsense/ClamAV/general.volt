{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Michael Muenz
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
<?php if (!file_exists('/var/db/clamav/main.cvd')): ?>
        <div class="alert alert-warning" role="alert" style="min-height: 65px;">
                <button class='btn btn-primary pull-right' id="dl_sig" type="button">{{ lang._('Download signatures') }}<i id="dl_sig_progress"></i> </button>
        <div style="margin-top: 8px;" id="dl_sig_err">{{ lang._('No signature database found, please download before starting. Download will take several minutes, come back in a few moments until this message is gone. If you have memory file system enabled where /var is mounted into RAM you have to download this file with every reboot.')}}</div>
        </div>
<?php endif ?>
<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <hr />
            <div class="col-md-12">
                <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress" class=""></i></button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $( document ).ready(function() {
        var data_get_map = {'frm_general_settings':"/api/clamav/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });


        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/clamav/general/set", formid='frm_general_settings',callback_ok=function(){
					$("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/clamav/service/reconfigure", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                            });
							$("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
        $("#dl_sig").click(function(){
            ajaxCall(url="/api/clamav/service/freshclam", callback_ok=function(){
                                        $("#dl_sig_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/clamav/service/reconfigure", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                            });
                                                        $("#dl_sig_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });

    });
</script>
