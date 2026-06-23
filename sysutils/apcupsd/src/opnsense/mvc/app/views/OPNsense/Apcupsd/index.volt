{#
 # Copyright (C) 2021 Dan Lundqvist
 # Copyright (C) 2021 David Berry
 # Copyright (C) 2021 Nicola Pellegrini
 #
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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
<div  class="col-md-12">
    {{ partial('layout_partials/base_form',['fields':generalForm,'id':'frm_GeneralSettings','apply_btn_id':'saveAct'])}}
</div>
<script>
    $(document).ready(function () {
        var data_get_map = { 'frm_GeneralSettings': '/api/apcupsd/settings/get' };

        mapDataToFormUI(data_get_map).done(function(data) {
            // place actions to run after load, for example update form styles.
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // Adds or updates the service control
        updateServiceControlUI('apcupsd');

        var waitEnabled = function(callback, ntry) {
            ntry = ntry || 0;
            ajaxCall('/api/apcupsd/service/status', {}, function(data) {
                if ((data && data['status'] === 'running') || ntry > 5) {
                    callback.call(null);
                } else {
                    setTimeout(function() {
                        waitEnabled(callback, ntry++);
                    }, 1000);
                }
            });
        };

        // link save button to API set action
        $('#saveAct').click(function(){
            saveFormToEndpoint('/api/apcupsd/settings/set', 'frm_GeneralSettings', function() {
                $('#frm_GeneralSettings_progress').addClass('fa fa-spinner fa-pulse');
                // action to run after successful save, for example reconfigure service.
                ajaxCall('/api/apcupsd/service/reconfigure', {}, function(data, status) {
                    // action to run after reload
                    $('#frm_GeneralSettings_progress').removeClass('fa fa-spinner fa-pulse');
                    updateServiceControlUI('apcupsd');
                    waitEnabled(function() {
                        updateServiceControlUI('apcupsd');
                    });
                });
            });
        });
    });
</script>
