{#
 # Copyright (c) 2026 Henry Stern <henry@stern.ca>
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
    'use strict';

    $(document).ready(function () {

        // One instance filter scopes BOTH grids (Unsafe Routes + MTU Overrides).
        // Deep-link from the instance "Routes" button carries ?instance=<uuid>.
        const deepLinkInstance = new URLSearchParams(window.location.search).get('instance') || '';
        let scopedInstance = deepLinkInstance;

        // Wire the bootgrid change-alert on both tables before UIBootgrid captures them.

        function scopeRequest(request) {
            if (scopedInstance) {
                request['instance'] = scopedInstance;
            }
            return request;
        }

        // Tab 1: unsafe routes.
        $("#{{formGridUnsafeRoute['table_id']}}").UIBootgrid({
            search: '/api/nebula/unsafe_route/search_item',
            get:    '/api/nebula/unsafe_route/get_item/',
            set:    '/api/nebula/unsafe_route/set_item/',
            add:    '/api/nebula/unsafe_route/add_item/',
            del:    '/api/nebula/unsafe_route/del_item/',
            toggle: '/api/nebula/unsafe_route/toggle_item/',
            options: { selection: false, requestHandler: scopeRequest }
        });

        // Tab 2: per-route MTU overrides (tun.routes).
        $("#{{formGridTunRoute['table_id']}}").UIBootgrid({
            search: '/api/nebula/tun_route/search_item',
            get:    '/api/nebula/tun_route/get_item/',
            set:    '/api/nebula/tun_route/set_item/',
            add:    '/api/nebula/tun_route/add_item/',
            del:    '/api/nebula/tun_route/del_item/',
            toggle: '/api/nebula/tun_route/toggle_item/',
            options: { selection: false, requestHandler: scopeRequest }
        });

        // Tab 3: static host map (reach a peer by its Nebula IP before discovery).
        $("#{{formGridStaticHostMap['table_id']}}").UIBootgrid({
            search: '/api/nebula/static_host_map/search_item',
            get:    '/api/nebula/static_host_map/get_item/',
            set:    '/api/nebula/static_host_map/set_item/',
            add:    '/api/nebula/static_host_map/add_item/',
            del:    '/api/nebula/static_host_map/del_item/',
            toggle: '/api/nebula/static_host_map/toggle_item/',
            options: { selection: false, requestHandler: scopeRequest }
        });

        // Cert index (uuid -> networks) so the static-host-map dialog can derive
        // the Nebula IP from a chosen lighthouse certificate. On certref change,
        // fill nebula_ip from the cert's first network's host IP (drop the mask).
        let nebula_cert_networks = {};
        ajaxGet('/api/nebula/certificate/search_item', {}, function (data) {
            if (data && data.rows) {
                $.each(data.rows, function (i, r) {
                    nebula_cert_networks[r.uuid] = r.networks || '';
                });
            }
        });
        $(document).on('changed.bs.select change', '#entry\\.certref', function () {
            let nets = nebula_cert_networks[$(this).val() || ''];
            if (nets) {
                let ip = String(nets).split(',')[0].trim().split('/')[0].trim();
                if (ip) { $("#entry\\.nebula_ip").val(ip); }
            }
        });

        // Shared instance filter dropdown (above the tabs).
        const $filter = $("#nebula_route_instance_filter");
        ajaxGet('/api/nebula/instance/search_item', {}, function (data, status) {
            $filter.append($("<option/>").val('').text('{{ lang._('All instances') }}'));
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    let label = row.description ? row.description : row.uuid;
                    $filter.append($("<option/>").val(row.uuid).text(label));
                });
            }
            $filter.val(deepLinkInstance);
            if ($filter.hasClass('selectpicker')) {
                $filter.selectpicker('refresh');
            }
        });

        $filter.on('changed.bs.select change', function () {
            scopedInstance = $(this).val() || '';
            $("#{{formGridUnsafeRoute['table_id']}}").bootgrid('reload');
            $("#{{formGridTunRoute['table_id']}}").bootgrid('reload');
            $("#{{formGridStaticHostMap['table_id']}}").bootgrid('reload');
        });

        $("#reconfigureAct").SimpleActionButton();

        // Persistent "apply needed": if a prior change has not been reconfigured,
        // raise the change notice on load too (the marker is cleared on Apply).
        ajaxGet('/api/nebula/service/dirty', {}, function (data) {
            if (data && data.isDirty) {
                $(document).trigger('settings-changed');
            }
        });
        updateServiceControlUI('nebula');
    });
</script>

<div class="col-md-12" style="margin-top: 10px; margin-bottom: 10px;">
    <label for="nebula_route_instance_filter">{{ lang._('Instance') }}</label>
    <select id="nebula_route_instance_filter" class="selectpicker" data-width="300px"
            data-live-search="true"></select>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#unsafe_tab">{{ lang._('Unsafe Routes') }}</a></li>
    <li><a data-toggle="tab" href="#mtu_tab">{{ lang._('MTU Overrides') }}</a></li>
    <li><a data-toggle="tab" href="#shm_tab">{{ lang._('Static Host Map') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="unsafe_tab" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridUnsafeRoute + {
            'command_width': '160'
        }) }}
    </div>
    <div id="mtu_tab" class="tab-pane fade">
        {{ partial('layout_partials/base_bootgrid_table', formGridTunRoute + {
            'command_width': '160'
        }) }}
    </div>
    <div id="shm_tab" class="tab-pane fade">
        {{ partial('layout_partials/base_bootgrid_table', formGridStaticHostMap + {
            'command_width': '160'
        }) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {
    'data_endpoint': '/api/nebula/service/reconfigure',
    'data_service_widget': 'nebula',
    'data_error_title': 'Error reconfiguring Nebula'
}) }}

{{ partial("layout_partials/base_dialog", [
    'fields': formDialogUnsafeRoute,
    'id':     formGridUnsafeRoute['edit_dialog_id'],
    'label':  lang._('Edit Unsafe Route')
]) }}
{{ partial("layout_partials/base_dialog", [
    'fields': formDialogTunRoute,
    'id':     formGridTunRoute['edit_dialog_id'],
    'label':  lang._('Edit MTU Override')
]) }}
{{ partial("layout_partials/base_dialog", [
    'fields': formDialogStaticHostMap,
    'id':     formGridStaticHostMap['edit_dialog_id'],
    'label':  lang._('Edit Static Host Map Entry')
]) }}
