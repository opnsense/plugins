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
            'search':'/api/quagga/static/searchRoute',
            'get':'/api/quagga/static/getRoute/',
            'set':'/api/quagga/static/setRoute/',
            'add':'/api/quagga/static/addRoute/',
            'del':'/api/quagga/static/delRoute/',
            'toggle':'/api/quagga/static/toggleRoute/'
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

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/quagga/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring STATIC') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
    <div id="STATICChangeMessage" class="alert alert-info" style="display: none" role="alert">
        {{ lang._('After changing settings, please remember to apply them.') }}
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditSTATICRoute,'id':formGridEditSTATICRoute['edit_dialog_id'],'label':lang._('Edit Routes')])}}
