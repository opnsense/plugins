{#
 # Copyright (c) 2024 Deciso B.V.
 # Copyright (c) 2024 Mike Shuey
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
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script>
    $( document ).ready(function() {
        mapDataToFormUI({'frm_static_settings': "/api/quagga/static/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('quagga');
        });

        $("#{{formGridEditSTATICRoute['table_id']}}").UIBootgrid({
            'search':'/api/quagga/static/search_route',
            'get':'/api/quagga/static/get_route/',
            'set':'/api/quagga/static/set_route/',
            'add':'/api/quagga/static/add_route/',
            'del':'/api/quagga/static/del_route/',
            'toggle':'/api/quagga/static/toggle_route/'
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/quagga/static/set", 'frm_static_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('quagga');
            }
      });
  });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#iproute">{{ lang._('Routes') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- Tab: General -->
    <div id="general"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':staticForm,'id':'frm_static_settings'])}}
    </div>
    <!-- Tab: Routes -->
    <div id="iproute" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditSTATICRoute)}}
    </div>
</div>
{{ partial(
    'layout_partials/base_apply_button',
    {
        'data_endpoint': '/api/quagga/service/reconfigure',
        'data_service_widget': 'quagga',
        'data_change_message_content': lang._('Apply will reload the service without causing interruptions. Some changes will need a full restart with the available service control buttons.')
    }
) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditSTATICRoute,'id':formGridEditSTATICRoute['edit_dialog_id'],'label':lang._('Edit Routes')])}}
