{#

OPNsense® is Copyright © 2014 – 2020 by Deciso B.V.
This file is Copyright © 2020 by Michael Muenz <m.muenz@gmail.com>
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

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#staticmappings">{{ lang._('Static Mappings') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div class="tab-pane fade in active" id="general" style="padding-bottom: 1.5em;">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
    </div>
    <div id="staticmappings" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridStaticMapping) }}
    </div>
</div>
{{ partial("layout_partials/base_dialog",['fields':formDialogEditStaticMapping,'id': formGridStaticMapping['edit_dialog_id'], 'label':lang._('Edit mapping')])}}
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/tayga/service/reconfigure', 'data_service_widget': 'tayga'}) }}

<script>
    $( document ).ready(function() {
        var data_get_map = {'frm_general_settings':"/api/tayga/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#{{formGridStaticMapping['table_id']}}").UIBootgrid({
            'search': '/api/tayga/mapping/search_staticmapping',
            'get': '/api/tayga/mapping/get_staticmapping/',
            'set': '/api/tayga/mapping/set_staticmapping/',
            'add': '/api/tayga/mapping/add_staticmapping/',
            'del': '/api/tayga/mapping/del_staticmapping/',
            'toggle': '/api/tayga/mapping/toggle_staticmapping/'
        });
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = $.Deferred();
                saveFormToEndpoint("/api/tayga/general/set", 'frm_general_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            }
        });
        updateServiceControlUI('tayga');
    });
</script>
