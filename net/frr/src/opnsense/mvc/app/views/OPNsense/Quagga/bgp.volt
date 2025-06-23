{#

OPNsense® is Copyright © 2014 – 2025 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 - 2020 Michael Muenz <m.muenz@gmail.com>
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
    $(document).ready(function() {
        mapDataToFormUI({'frm_bgp_settings':"/api/quagga/bgp/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('quagga');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/quagga/bgp/set", 'frm_bgp_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('quagga');
            }
        });

        $("#{{formGridEditBGPNeighbor['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_neighbor',
            'get':'/api/quagga/bgp/get_neighbor/',
            'set':'/api/quagga/bgp/set_neighbor/',
            'add':'/api/quagga/bgp/add_neighbor/',
            'del':'/api/quagga/bgp/del_neighbor/',
            'toggle':'/api/quagga/bgp/toggle_neighbor/'
        });
        $("#{{formGridEditBGPASPaths['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_aspath',
            'get':'/api/quagga/bgp/get_aspath/',
            'set':'/api/quagga/bgp/set_aspath/',
            'add':'/api/quagga/bgp/add_aspath/',
            'del':'/api/quagga/bgp/del_aspath/',
            'toggle':'/api/quagga/bgp/toggle_aspath/'
        });
        $("#{{formGridEditBGPPrefixLists['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_prefixlist',
            'get':'/api/quagga/bgp/get_prefixlist/',
            'set':'/api/quagga/bgp/set_prefixlist/',
            'add':'/api/quagga/bgp/add_prefixlist/',
            'del':'/api/quagga/bgp/del_prefixlist/',
            'toggle':'/api/quagga/bgp/toggle_prefixlist/'
        });
        $("#{{formGridEditBGPCommunityLists['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_communitylist',
            'get':'/api/quagga/bgp/get_communitylist/',
            'set':'/api/quagga/bgp/set_communitylist/',
            'add':'/api/quagga/bgp/add_communitylist/',
            'del':'/api/quagga/bgp/del_communitylist/',
            'toggle':'/api/quagga/bgp/toggle_communitylist/'
        });
        $("#{{formGridEditBGPRouteMaps['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_routemap',
            'get':'/api/quagga/bgp/get_routemap/',
            'set':'/api/quagga/bgp/set_routemap/',
            'add':'/api/quagga/bgp/add_routemap/',
            'del':'/api/quagga/bgp/del_routemap/',
            'toggle':'/api/quagga/bgp/toggle_routemap/'
        });
        $("#{{formGridEditBGPPeergroups['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_peergroup',
            'get':'/api/quagga/bgp/get_peergroup/',
            'set':'/api/quagga/bgp/set_peergroup/',
            'add':'/api/quagga/bgp/add_peergroup/',
            'del':'/api/quagga/bgp/del_peergroup/',
            'toggle':'/api/quagga/bgp/toggle_peergroup/'
        });
        $("#{{formGridEditRedistribution['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/search_redistribution',
            'get':'/api/quagga/bgp/get_redistribution/',
            'set':'/api/quagga/bgp/set_redistribution/',
            'add':'/api/quagga/bgp/add_redistribution/',
            'del':'/api/quagga/bgp/del_redistribution/',
            'toggle':'/api/quagga/bgp/toggle_redistribution/'
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
    <li><a data-toggle="tab" href="#aspaths">{{ lang._('AS Path Lists') }}</a></li>
    <li><a data-toggle="tab" href="#prefixlists">{{ lang._('Prefix Lists') }}</a></li>
    <li><a data-toggle="tab" href="#communitylists">{{ lang._('Community Lists') }}</a></li>
    <li><a data-toggle="tab" href="#routemaps">{{ lang._('Route Maps') }}</a></li>
    <li><a data-toggle="tab" href="#peergroups">{{ lang._('Peer Groups') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <!-- Tab: General -->
    <div id="general" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':bgpForm,'id':'frm_bgp_settings'])}}
        {{ partial('layout_partials/base_bootgrid_table', formGridEditRedistribution)}}
    </div>
    <!-- Tab: Neighbors -->
    <div id="neighbors" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditBGPNeighbor)}}
    </div>
    <!-- Tab: AS Paths -->
    <div id="aspaths" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditBGPASPaths)}}
    </div>
    <!-- Tab: Prefix Lists -->
    <div id="prefixlists" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditBGPPrefixLists)}}
    </div>
    <!-- Tab: Community Lists -->
    <div id="communitylists" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditBGPCommunityLists)}}
    </div>
    <!-- Tab: Route Maps -->
    <div id="routemaps" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditBGPRouteMaps)}}
    </div>
    <!-- Tab: Peer Groups -->
    <div id="peergroups" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEditBGPPeergroups)}}
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/quagga/service/reconfigure', 'data_service_widget': 'quagga'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPNeighbor,'id':formGridEditBGPNeighbor['edit_dialog_id'],'label':lang._('Edit Neighbor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPASPaths,'id':formGridEditBGPASPaths['edit_dialog_id'],'label':lang._('Edit AS Paths')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPPrefixLists,'id':formGridEditBGPPrefixLists['edit_dialog_id'],'label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPCommunityLists,'id':formGridEditBGPCommunityLists['edit_dialog_id'],'label':lang._('Edit Community Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPRouteMaps,'id':formGridEditBGPRouteMaps['edit_dialog_id'],'label':lang._('Edit Route Maps')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPPeergroups,'id':formGridEditBGPPeergroups['edit_dialog_id'],'label':lang._('Edit Peer Groups')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRedistribution,'id':formGridEditRedistribution['edit_dialog_id'],'label':lang._('Edit Route Redistribution')])}}
