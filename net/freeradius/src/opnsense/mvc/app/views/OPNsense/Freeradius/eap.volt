{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Michael Muenz <m.muenz@gmail.com>
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

<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form",['fields':eapForm,'id':'frm_eap_settings'])}}
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress" class=""></i></button>
    </div>
</div>

<script>
    $( document ).ready(function () {
        var outerSelectionInitialized = false;
        var data_get_map = {'frm_eap_settings':"/api/freeradius/eap/get"};
        mapDataToFormUI(data_get_map).done(function (data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            outerSelectionInitialized = $("#eap\\.restrict_outer_eap_methods").is(':checked') ||
                ($("#eap\\.allowed_outer_eap_types").val() || []).length > 0;
            toggleEapSettings();
        });

        // Show settings for the restriction state and enabled tunnel methods.
        function toggleEapSettings() {
            var restricted = $("#eap\\.restrict_outer_eap_methods").is(':checked');
            var allowed = $("#eap\\.allowed_outer_eap_types").val() || [];
            $("#eap\\.allowed_outer_eap_types").prop('disabled', !restricted).selectpicker('refresh');
            // Native multiselect helper links must not modify disabled selections.
            $("#clear-options_eap\\.allowed_outer_eap_types, #select-options_eap\\.allowed_outer_eap_types")
                .css('visibility', restricted ? 'visible' : 'hidden');
            // Restrictions may allow TTLS even when another method is the outer default.
            if ((restricted && allowed.indexOf('ttls') !== -1) || (!restricted && $("#eap\\.default_eap_type").val() === 'ttls')) {
                $(".ttls_inner").closest('tr').show();
            } else {
                $(".ttls_inner").closest('tr').hide();
            }
        }
        $("#eap\\.restrict_outer_eap_methods").change(function () {
            // Seed only the first restricted selection; never repopulate a cleared list.
            if ($("#eap\\.restrict_outer_eap_methods").is(':checked') && !outerSelectionInitialized) {
                $("#eap\\.allowed_outer_eap_types").val([$("#eap\\.default_eap_type").val()]);
                outerSelectionInitialized = true;
            }
            toggleEapSettings();
        });
        $("#eap\\.allowed_outer_eap_types, #eap\\.default_eap_type").change(toggleEapSettings);
        // Native bulk-selection links refresh the widget without emitting change.
        // Delegate to the form so their direct click handlers finish first.
        $("#frm_eap_settings").on('click',
            "#clear-options_eap\\.allowed_outer_eap_types, #select-options_eap\\.allowed_outer_eap_types",
            toggleEapSettings);
        toggleEapSettings();
        updateServiceControlUI('freeradius');

        // link save button to API set action
        $("#saveAct").click(function () {
            saveFormToEndpoint(url="/api/freeradius/eap/set", formid='frm_eap_settings',callback_ok=function () {
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/freeradius/service/reconfigure", sendData={}, callback=function (data,status) {
                    updateServiceControlUI('freeradius');
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            }, false, toggleEapSettings);
        });
    });
</script>
