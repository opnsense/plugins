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

        $("#{{formGridDriver['table_id']}}").UIBootgrid(
            {
                search:'/api/nut/drivers/search_driver/',
                get:'/api/nut/drivers/get_driver/',
                set:'/api/nut/drivers/set_driver/',
                add:'/api/nut/drivers/add_driver/',
                del:'/api/nut/drivers/del_driver/',
                toggle:'/api/nut/drivers/toggle_driver/'
            }
        );

        $("#reconfigureAct").SimpleActionButton();

        updateServiceControlUI('nut');
    });
</script>

{% if !server_enabled %}
<div class="alert alert-warning" role="alert" id="nut_server_disabled" style="min-height:65px;">
    <div style="margin-top: 8px;">
        {{ lang._('NUT is in netclient mode. All local driver definitions will be ignored.') }}
    </div>
</div>
{% endif %}

<div class="content-box">
    {{ partial("layout_partials/base_form", ['fields':driversForm, 'id':'frm_nut-drivers']) }}

    {{ partial('layout_partials/base_bootgrid_table', formGridDriver) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/nut/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogDriver,'id':formGridDriver['edit_dialog_id'],'label':lang._('Edit UPS')])}}
