{#

OPNsense® is Copyright © 2014 – 2018 by Deciso B.V.
This file is Copyright © 2018 by Michael Muenz <m.muenz@gmail.com>
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

<section class="col-xs-12">
	<div class="content-box tab-content table-responsive">
{{ partial("layout_partials/base_form",['fields':this_form,'id':'frm_dnsbl_settings'])}}
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_dnsbl" type="button"><b>{{ lang._('Save & Fetch Lists') }}</b> <i id="saveAct_dnsbl_progress"></i></button>
        </div>
        &nbsp;
    </div>
</section>


<script>

$( document ).ready(function() {

    $("#saveAct_dnsbl").click(function(){
        saveFormToEndpoint(url="/api/dnsbl/settings/set", formid='frm_dnsbl_settings',callback_ok=function(){
        $("#saveAct_dnsbl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnsbl/service/update", sendData={}, callback=function(data,status) {
                $("#saveAct_dnsbl_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    var data_get_map = {'frm_dnsbl_settings':"/api/dnsbl/settings/get"};

    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

});
</script>
