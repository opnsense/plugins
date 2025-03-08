{#
 # Copyright (c) 2014-2025 Deciso B.V.
 # Copyright (c) 2017 Fabian Franz
 # Copyright (c) 2017 Michael Muenz <m.muenz@gmail.com>
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
    'use strict';
    $( document ).ready(function () {
        mapDataToFormUI({'frm_ospf6_settings':"/api/quagga/ospf6settings/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('quagga');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/quagga/ospf6settings/set", 'frm_ospf6_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('quagga');
            }
        });

        $("#{{formGridEditNetwork['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchNetwork',
            'get':'/api/quagga/ospf6settings/getNetwork/',
            'set':'/api/quagga/ospf6settings/setNetwork/',
            'add':'/api/quagga/ospf6settings/addNetwork/',
            'del':'/api/quagga/ospf6settings/delNetwork/',
            'toggle':'/api/quagga/ospf6settings/toggleNetwork/'
        });
        $("#{{formGridEditInterface['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchInterface',
            'get':'/api/quagga/ospf6settings/getInterface/',
            'set':'/api/quagga/ospf6settings/setInterface/',
            'add':'/api/quagga/ospf6settings/addInterface/',
            'del':'/api/quagga/ospf6settings/delInterface/',
            'toggle':'/api/quagga/ospf6settings/toggleInterface/'
        });
        $("#{{formGridEditPrefixLists['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchPrefixlist',
            'get':'/api/quagga/ospf6settings/getPrefixlist/',
            'set':'/api/quagga/ospf6settings/setPrefixlist/',
            'add':'/api/quagga/ospf6settings/addPrefixlist/',
            'del':'/api/quagga/ospf6settings/delPrefixlist/',
            'toggle':'/api/quagga/ospf6settings/togglePrefixlist/'
        });
        $("#{{formGridEditRouteMaps['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchRoutemap',
            'get':'/api/quagga/ospf6settings/getRoutemap/',
            'set':'/api/quagga/ospf6settings/setRoutemap/',
            'add':'/api/quagga/ospf6settings/addRoutemap/',
            'del':'/api/quagga/ospf6settings/delRoutemap/',
            'toggle':'/api/quagga/ospf6settings/toggleRoutemap/'
        });
        $("#{{formGridEditRedistribution['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchRedistribution',
            'get':'/api/quagga/ospf6settings/getRedistribution/',
            'set':'/api/quagga/ospf6settings/setRedistribution/',
            'add':'/api/quagga/ospf6settings/addRedistribution/',
            'del':'/api/quagga/ospf6settings/delRedistribution/',
            'toggle':'/api/quagga/ospf6settings/toggleRedistribution/'
        });

        // hook checkbox item with conditional options
        $("#ospf6\\.originate").change(function(){
            if ($(this).is(':checked')) {
                $(".ospf6_originate").closest('tr').show();
            } else {
                $(".ospf6_originate").closest('tr').hide();
            }
        });

        const $header = $(".bootgrid-header[id*='{{formGridEditRedistribution['table_id']}}']");
        if ($header.length) {
            $header.find("div.actionBar").parent().prepend(
                '<td class="col-sm-2 theading-text">' +
                '<span class="fa fa-info-circle text-muted" style="margin-right: 5px;"></span>' +
                '<strong>{{ lang._("Route Redistribution") }}</strong>' +
                '</td>'
            );
        }

    });
</script>

<style>
    .bootgrid-header .theading-text {
        padding-left: 10px !important;
    }
</style>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#networks">{{ lang._('Networks') }}</a></li>
    <li><a data-toggle="tab" href="#interfaces">{{ lang._('Interfaces') }}</a></li>
    <li><a data-toggle="tab" href="#prefixlists">{{ lang._('Prefix Lists') }}</a></li>
    <li><a data-toggle="tab" href="#routemaps">{{ lang._('Route Maps') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <!-- Tab: General -->
    <div id="general" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':ospf6Form,'id':'frm_ospf6_settings'])}}
        {{ partial('layout_partials/base_bootgrid_table', formGridEditRedistribution)}}
    </div>
    <!-- Tab: Networks -->
    <div id="networks" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditNetwork)}}
    </div>
    <!-- Tab: Interfaces -->
    <div id="interfaces" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditInterface)}}
    </div>
    <!-- Tab: Prefixlists -->
    <div id="prefixlists" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditPrefixLists)}}
    </div>
    <!-- Tab: Routemaps -->
    <div id="routemaps" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditRouteMaps)}}
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/quagga/service/reconfigure', 'data_service_widget': 'quagga'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditNetwork,'id':formGridEditNetwork['edit_dialog_id'],'label':lang._('Edit Network')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditInterface,'id':formGridEditInterface['edit_dialog_id'],'label':lang._('Edit Interface')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPrefixLists,'id':formGridEditPrefixLists['edit_dialog_id'],'label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRouteMaps,'id':formGridEditRouteMaps['edit_dialog_id'],'label':lang._('Edit Route Maps')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRedistribution,'id':formGridEditRedistribution['edit_dialog_id'],'label':lang._('Edit Route Redistribution')])}}
