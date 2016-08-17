{#
Copyright (C) 2016 gitdevmod@github.com

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

{{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}

<script type="text/javascript">
    $( document ).ready(function() {
        var data_get_map = {'frm_GeneralSettings':"/api/ssoproxyad/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/ssoproxyad/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                // action to run after successful save, for example reconfigure service.
		ajaxCall(url="/api/ssoproxyad/service/reload", sendData={},callback=function(data,status) {
                // action to run after reload
                });
            });
        });

	$("#testAct").click(function(){
            $("#responseMsg").removeClass("hidden");
            ajaxCall(url="/api/ssoproxyad/service/test", sendData={},callback=function(data,status) {
                // action to run after reload
                $("#responseMsg").html(data['message']);
            });
       });
	$("#joinDomainAct").click(function(){
            $("#responseMsg").removeClass("hidden");
            ajaxCall(url="/api/ssoproxyad/service/joinDomain", sendData={},callback=function(data,status) {
                // action to run after reload
                $("#responseMsg").html(data['message']);
            });
       });
	$("#updateDomainAct").click(function(){
            $("#responseMsg").removeClass("hidden");
            ajaxCall(url="/api/ssoproxyad/service/updateDomain", sendData={},callback=function(data,status) {
                // action to run after reload
                $("#responseMsg").html(data['message']);
            });
       });



    });
</script>

<div class="col-md-12">
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
</div>


<div class="alert alert-info hidden" role="alert" id="responseMsg">
</div>
<button class="btn btn-primary"  id="testAct" type="button"><b>{{ lang._('Test') }}</b></button>
<button class="btn btn-primary"  id="joinDomainAct" type="button"><b>{{ lang._('Join Domain') }}</b></button>
<button class="btn btn-primary"  id="updateDomainAct" type="button"><b>{{ lang._('Update Domain') }}</b></button>
