{#

OPNsense® is Copyright © 2014 – 2025 by Deciso B.V.
This file is Copyright © 2017 by Fabian Franz
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

    function quagga_update_status() {
      updateServiceControlUI('quagga');
    }

    $( document ).ready(function() {
        mapDataToFormUI({'frm_ospf_settings':"/api/quagga/ospfsettings/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('quagga');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/quagga/ospfsettings/set", 'frm_ospf_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('quagga');
            }
        });

        $("#{{formGridEditOSPFNeighbor['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospfsettings/search_neighbor',
            'get':'/api/quagga/ospfsettings/get_neighbor/',
            'set':'/api/quagga/ospfsettings/set_neighbor/',
            'add':'/api/quagga/ospfsettings/add_neighbor/',
            'del':'/api/quagga/ospfsettings/del_neighbor/',
            'toggle':'/api/quagga/ospfsettings/toggle_neighbor/'
        });
        $("#{{formGridEditNetwork['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospfsettings/search_network',
            'get':'/api/quagga/ospfsettings/get_network/',
            'set':'/api/quagga/ospfsettings/set_network/',
            'add':'/api/quagga/ospfsettings/add_network/',
            'del':'/api/quagga/ospfsettings/del_network/',
            'toggle':'/api/quagga/ospfsettings/toggle_network/'
        });
        $("#{{formGridEditInterface['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospfsettings/search_interface',
            'get':'/api/quagga/ospfsettings/get_interface/',
            'set':'/api/quagga/ospfsettings/set_interface/',
            'add':'/api/quagga/ospfsettings/add_interface/',
            'del':'/api/quagga/ospfsettings/del_interface/',
            'toggle':'/api/quagga/ospfsettings/toggle_interface/'
        });
        $("#{{formGridEditPrefixLists['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospfsettings/search_prefixlist',
            'get':'/api/quagga/ospfsettings/get_prefixlist/',
            'set':'/api/quagga/ospfsettings/set_prefixlist/',
            'add':'/api/quagga/ospfsettings/add_prefixlist/',
            'del':'/api/quagga/ospfsettings/del_prefixlist/',
            'toggle':'/api/quagga/ospfsettings/toggle_prefixlist/'
        });
        $("#{{formGridEditRouteMaps['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospfsettings/search_routemap',
            'get':'/api/quagga/ospfsettings/get_routemap/',
            'set':'/api/quagga/ospfsettings/set_routemap/',
            'add':'/api/quagga/ospfsettings/add_routemap/',
            'del':'/api/quagga/ospfsettings/del_routemap/',
            'toggle':'/api/quagga/ospfsettings/toggle_routemap/'
        });
        $("#{{formGridEditRedistribution['table_id']}}").UIBootgrid({
            'search':'/api/quagga/ospfsettings/search_redistribution',
            'get':'/api/quagga/ospfsettings/get_redistribution/',
            'set':'/api/quagga/ospfsettings/set_redistribution/',
            'add':'/api/quagga/ospfsettings/add_redistribution/',
            'del':'/api/quagga/ospfsettings/del_redistribution/',
            'toggle':'/api/quagga/ospfsettings/toggle_redistribution/'
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

        $("#interface\\.networktype").on("keyup change", function () {

            const networktype = String($("#interface\\.networktype").val() || "")
 
            const styleVisibility = [
                {
                    class: "style_networktype",
                    visible: networktype === "point-to-multipoint"
                },
            ];

            styleVisibility.forEach(style => {
                // hide/show rows with the class
                const elements = $("." + style.class).closest("tr");
                style.visible ? elements.show() : elements.hide();

                // hide/show thead only if its parent container has the same class
                $(".table-responsive." + style.class).find("thead").each(function () {
                    style.visible ? $(this).show() : $(this).hide();
                });
            });
        });

    });
</script>

<style>
    /* Some trickery to make the redistribution grid look like its part of the base form */
    .bootgrid-header[id*='{{ formGridEditRedistribution['table_id'] }}'] {
        padding-left: 10px;
    }
    #{{ formGridEditRedistribution['table_id'] }}.bootgrid-table {
        margin-left: 25%;
        width: 75%;
    }
    .bootgrid-footer[id*='{{ formGridEditRedistribution['table_id'] }}'] {
        margin-left: 24%;
    }
</style>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#neighbors">{{ lang._('Neighbors') }}</a></li>
    <li><a data-toggle="tab" href="#networks">{{ lang._('Networks') }}</a></li>
    <li><a data-toggle="tab" href="#interfaces">{{ lang._('Interfaces') }}</a></li>
    <li><a data-toggle="tab" href="#prefixlists">{{ lang._('Prefix Lists') }}</a></li>
    <li><a data-toggle="tab" href="#routemaps">{{ lang._('Route Maps') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <!-- Tab: General -->
    <div id="general" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_ospf_settings'])}}
        {{ partial('layout_partials/base_bootgrid_table', formGridEditRedistribution)}}
    </div>
    <!-- Tab: Neighbors -->
    <div id="neighbors" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditOSPFNeighbor)}}
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
{{ partial("layout_partials/base_dialog",['fields':formDialogEditOSPFNeighbor,'id':formGridEditOSPFNeighbor['edit_dialog_id'],'label':lang._('Edit Neighbor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditNetwork,'id':formGridEditNetwork['edit_dialog_id'],'label':lang._('Edit Network')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditInterface,'id':formGridEditInterface['edit_dialog_id'],'label':lang._('Edit Interface')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPrefixLists,'id':formGridEditPrefixLists['edit_dialog_id'],'label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRouteMaps,'id':formGridEditRouteMaps['edit_dialog_id'],'label':lang._('Edit Route Maps')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRedistribution,'id':formGridEditRedistribution['edit_dialog_id'],'label':lang._('Edit Route Redistribution')])}}
