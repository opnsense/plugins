{#

Copyright (C) 2024 Volodymyr Paprotski
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
        updateServiceControlUI('stepca');
        ajaxCall(url="/api/stepca/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });
        
        // load initial data
        var data_get_map = {'frm_GeneralSettings':"/api/stepca/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#saveAct").click(function(){
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            saveFormToEndpoint(url="/api/stepca/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                ajaxCall(url="/api/stepca/service/reconfigure", sendData={}, callback=function(data,status) {
                    // when done, disable progress animation.
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                    updateServiceControlUI('stepca');
                    if (status != "success" || data['status'] != 'ok') {
                        stdDialogInform("{{ lang._('Error reconfiguring StepCA') }}", data['status'], 'warning');
                    }
                });
            });
        });
    });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">

</div>

<div  class="col-md-12">
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
</div>

<div class="col-md-12">
    <hr/>
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Apply') }}</b><i id="saveAct_progress" class=""></i></button>
    <br/>
    <br/>
</div>
