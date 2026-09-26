{#
 # Copyright (C) 2026 Sergii Bogomolov
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
        mapDataToFormUI({'frm_GeneralSettings': "/api/netflector/settings/get"}).done(function() {
            $('.selectpicker').selectpicker('refresh');
        });

        $("#{{formGridEdit['table_id']}}").UIBootgrid({
            search: '/api/netflector/settings/search_reflector',
            get: '/api/netflector/settings/get_reflector/',
            set: '/api/netflector/settings/set_reflector/',
            add: '/api/netflector/settings/add_reflector/',
            del: '/api/netflector/settings/del_reflector/',
            toggle: '/api/netflector/settings/toggle_reflector/'
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = $.Deferred();
                saveFormToEndpoint("/api/netflector/settings/set", 'frm_GeneralSettings', dfObj.resolve, true, dfObj.reject);
                return dfObj;
            }
        });

        updateServiceControlUI('netflector');
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#reflectors">{{ lang._('Reflectors') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="general" class="tab-pane fade in active">
        {{ partial('layout_partials/base_form', ['fields': generalForm, 'id': 'frm_GeneralSettings']) }}
    </div>
    <div id="reflectors" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridEdit) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/netflector/service/reconfigure', 'data_service_widget': 'netflector'}) }}
{{ partial('layout_partials/base_dialog', ['fields': formDialogEdit, 'id': formGridEdit['edit_dialog_id'], 'label': lang._('Edit reflector')]) }}
