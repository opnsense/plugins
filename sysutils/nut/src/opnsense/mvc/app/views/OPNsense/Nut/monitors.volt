{#
 # Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
 #
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
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
    $(document).ready(function() {
        var data_get_map = {'frm_nut':'/api/nut/general/get'};
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#{{formGridLocalMonitor['table_id']}}").UIBootgrid(
            {
                search:'/api/nut/monitors/search_local_monitor/',
                get:'/api/nut/monitors/get_local_monitor/',
                set:'/api/nut/monitors/set_local_monitor/',
                add:'/api/nut/monitors/add_local_monitor/',
                del:'/api/nut/monitors/del_local_monitor/',
                toggle:'/api/nut/monitors/toggle_local_monitor/'
            }
        );

        $("#{{formGridRemoteMonitor['table_id']}}").UIBootgrid(
            {
                search:'/api/nut/monitors/search_remote_monitor/',
                get:'/api/nut/monitors/get_remote_monitor/',
                set:'/api/nut/monitors/set_remote_monitor/',
                add:'/api/nut/monitors/add_remote_monitor/',
                del:'/api/nut/monitors/del_remote_monitor/',
                toggle:'/api/nut/monitors/toggle_remote_monitor/'
            }
        );

        $("#reconfigureAct").SimpleActionButton();

        updateServiceControlUI('nut');
    });
</script>

{% if !server_enabled %}
<div class="alert alert-warning" role="alert" id="nut_server_disabled" style="min-height:65px;">
    <div style="margin-top: 8px;">
        {{ lang._('NUT is in netclient mode. All local local UPS monitor definitions will be ignored. Remote UPS monitors will be unaffected.') }}
    </div>
</div>
{% endif %}

<div class="content-box">
    <h2>{{ lang._('Global Monitor Settings') }}</h2>
    {{ partial("layout_partials/base_form", ['fields':monitorsForm, 'id':'frm_nut-monitors']) }}

    <h2>{{ lang._('Local UPS Monitors') }}</h2>
    {{ partial('layout_partials/base_bootgrid_table', formGridLocalMonitor) }}

    <h2>{{ lang._('Remote UPS Monitors') }}</h2>
    {{ partial('layout_partials/base_bootgrid_table', formGridRemoteMonitor) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/nut/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogLocalMonitor,'id':formGridLocalMonitor['edit_dialog_id'],'label':lang._('Edit monitor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogRemoteMonitor,'id':formGridRemoteMonitor['edit_dialog_id'],'label':lang._('Edit monitor')])}}
