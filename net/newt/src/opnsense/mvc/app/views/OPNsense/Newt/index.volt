{# SPDX-License-Identifier: BSD-2-Clause #}
<script>
$(document).ready(function () {
    mapDataToFormUI({'frmNewt': '/api/newt/settings/get'}).done(function () {
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('newt');
    });
    $('#reconfigureAct').SimpleActionButton({
        onPreAction: function () {
            var deferred = $.Deferred();
            saveFormToEndpoint('/api/newt/settings/set', 'frmNewt', deferred.resolve, true, deferred.reject);
            return deferred;
        },
        onAction: function () { updateServiceControlUI('newt'); }
    });
});
</script>
<div class="content-box">
    {{ partial('layout_partials/base_form', ['fields': settingsForm, 'id': 'frmNewt']) }}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/newt/service/reconfigure', 'data_service_widget': 'newt'}) }}
