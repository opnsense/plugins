{#

Copyright © 2017 Fabian Franz
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
    var data_get_map = {'general': '/api/mdnsrepeater/settings/get'};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('select').selectpicker('refresh');
    });
    ajaxCall(url="/api/mdnsrepeater/service/status", sendData={}, callback=function(data,status) {
        updateServiceStatusUI(data['result']);
    });

    // link save button to API set action
    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/mdnsrepeater/settings/set", formid='general',callback_ok=function(){
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/mdnsrepeater/service/restart", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/mdnsrepeater/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['result']);
                });
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });
});

</script>

<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form",['fields': general,'id':'general'])}}
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
    </div>
</div>
