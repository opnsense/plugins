{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
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
        mapDataToFormUI({'frm_GeneralSettings':"/api/helloworld/settings/get"}).done(function(data){
            // place actions to run after load, for example update form styles.
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint("/api/helloworld/settings/set",'frm_GeneralSettings',function(){
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/helloworld/service/reload", sendData={},callback=function(data,status) {
                    // action to run after reload
                });
            });
        });

        // use a SimpleActionButton() to call /api/helloworld/service/test
        $("#testAct").SimpleActionButton({
            onAction: function(data) {
                $("#responseMsg").removeClass("hidden").html(data['message']);
            }
        });

        // use SimpleActionButton() to call /api/helloworld/service/reconfigure
        $("#reconfigureAct").SimpleActionButton({
            onAction: function(data) {
                $("#responseMsg").removeClass("hidden").html(data['message']);
            }
        });

        // set up the base_bootgrid_table
        $("#{{formGridGeneral['table_id']}}").UIBootgrid({
            'search':'/api/helloworld/settings/search_general_grid',
            'get':'/api/helloworld/settings/get_general_grid/',
            'set':'/api/helloworld/settings/set_general_grid/',
            'add':'/api/helloworld/settings/add_general_grid/',
            'del':'/api/helloworld/settings/del_general_grid/',
            'toggle':'/api/helloworld/settings/toggle_general_grid/'
        });
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#baseform">{{ lang._('Form') }}</a></li>
    <li><a data-toggle="tab" href="#basetable">{{ lang._('Table') }}</a></li>
</ul>

<!-- Tab content container -->
<div class="tab-content content-box">
    <!-- Tab: base_form example -->
    <div id="baseform" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form", ['fields':generalForm, 'id':'frm_GeneralSettings']) }}
        <div class="col-md-12">
            <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
            <button class="btn btn-primary" id="testAct" data-endpoint="/api/helloworld/service/test" data-label="{{ lang._('Test') }}"></button>
            <div class="alert alert-info hidden" role="alert" id="responseMsg"></div>
        </div>
    </div>
    <!-- Tab: base_bootgrid_table example -->
    <div id="basetable" class="tab-pane fade in">
        <!-- auto creates a bootgrid from the data in formGridGeneral -->
        {{ partial('layout_partials/base_bootgrid_table', formGridGeneral) }}
        <!-- general purpose apply button, used to trigger reconfigureAct -->
        {{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/helloworld/service/reconfigure'}) }}
    </div>
</div>


<!-- base_dialog used by the base_bootgrid_table -->
{{ partial("layout_partials/base_dialog",['fields':formDialogGeneral,'id':formGridGeneral['edit_dialog_id'],'label':lang._('Edit General')])}}
