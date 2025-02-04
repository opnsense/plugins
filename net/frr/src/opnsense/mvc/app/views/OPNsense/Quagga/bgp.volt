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
            'search':'/api/quagga/bgp/searchNeighbor',
            'get':'/api/quagga/bgp/getNeighbor/',
            'set':'/api/quagga/bgp/setNeighbor/',
            'add':'/api/quagga/bgp/addNeighbor/',
            'del':'/api/quagga/bgp/delNeighbor/',
            'toggle':'/api/quagga/bgp/toggleNeighbor/'
        });
        $("#{{formGridEditBGPASPaths['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/searchAspath',
            'get':'/api/quagga/bgp/getAspath/',
            'set':'/api/quagga/bgp/setAspath/',
            'add':'/api/quagga/bgp/addAspath/',
            'del':'/api/quagga/bgp/delAspath/',
            'toggle':'/api/quagga/bgp/toggleAspath/'
        });
        $("#{{formGridEditBGPPrefixLists['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/searchPrefixlist',
            'get':'/api/quagga/bgp/getPrefixlist/',
            'set':'/api/quagga/bgp/setPrefixlist/',
            'add':'/api/quagga/bgp/addPrefixlist/',
            'del':'/api/quagga/bgp/delPrefixlist/',
            'toggle':'/api/quagga/bgp/togglePrefixlist/'
        });
        $("#{{formGridEditBGPCommunityLists['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/searchCommunitylist',
            'get':'/api/quagga/bgp/getCommunitylist/',
            'set':'/api/quagga/bgp/setCommunitylist/',
            'add':'/api/quagga/bgp/addCommunitylist/',
            'del':'/api/quagga/bgp/delCommunitylist/',
            'toggle':'/api/quagga/bgp/toggleCommunitylist/'
        });
        $("#{{formGridEditBGPRouteMaps['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/searchRoutemap',
            'get':'/api/quagga/bgp/getRoutemap/',
            'set':'/api/quagga/bgp/setRoutemap/',
            'add':'/api/quagga/bgp/addRoutemap/',
            'del':'/api/quagga/bgp/delRoutemap/',
            'toggle':'/api/quagga/bgp/toggleRoutemap/'
        });
        $("#{{formGridEditBGPPeergroups['table_id']}}").UIBootgrid({
            'search':'/api/quagga/bgp/searchPeergroup',
            'get':'/api/quagga/bgp/getPeergroup/',
            'set':'/api/quagga/bgp/setPeergroup/',
            'add':'/api/quagga/bgp/addPeergroup/',
            'del':'/api/quagga/bgp/delPeergroup/',
            'toggle':'/api/quagga/bgp/togglePeergroup/'
        });
    });
</script>

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

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/quagga/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring BGP') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
    <div id="BGPChangeMessage" class="alert alert-info" style="display: none" role="alert">
        {{ lang._('After changing settings, please remember to apply them.') }}
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPNeighbor,'id':formGridEditBGPNeighbor['edit_dialog_id'],'label':lang._('Edit Neighbor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPASPaths,'id':formGridEditBGPASPaths['edit_dialog_id'],'label':lang._('Edit AS Paths')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPPrefixLists,'id':formGridEditBGPPrefixLists['edit_dialog_id'],'label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPCommunityLists,'id':formGridEditBGPCommunityLists['edit_dialog_id'],'label':lang._('Edit Community Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPRouteMaps,'id':formGridEditBGPRouteMaps['edit_dialog_id'],'label':lang._('Edit Route Maps')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPPeergroups,'id':formGridEditBGPPeergroups['edit_dialog_id'],'label':lang._('Edit Peer Groups')])}}
