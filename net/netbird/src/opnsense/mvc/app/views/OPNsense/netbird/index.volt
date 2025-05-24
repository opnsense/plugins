{#

Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
Copyright (C) 2025 squared GmbH
Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
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

    $(document).ready(function () {

        $("#netbird\\.initial\\.initsure").click(function () {
            if ($("#netbird\\.initial\\.initsure").prop('checked')) {
                $("#initialAct").prop('disabled', false);
            } else {
                $("#initialAct").prop('disabled', true);
            }
        });

        $("#saveAct").click(function () {
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            saveFormToEndpoint(url = "/api/netbird/settings/set", formid = 'frm_GeneralSettings', callback_ok = function () {
                ajaxCall(url = "/api/netbird/service/reload", sendData = {}, callback = function (data, status) {
                    updateServiceControlUI('netbird');
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

        $("#initialAct").click(function () {
            saveFormToEndpoint(url = "/api/netbird/initial/set", formid = 'frm_InitialUp', callback_ok = function () {
                $("#initialAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url = "/api/netbird/service/initialup", sendData = {}, callback = function (data, status) {
                    $("#initialtxt").html(data.responseText);
                    $("#resultdiv").prop('hidden', false);
                    $("#initialAct").prop('disabled', true);
                    var data_get_map_initial = {'frm_InitialUp': "/api/netbird/initial/get"};
                    mapDataToFormUI(data_get_map_initial)
                    ajaxCall(url = "/api/netbird/service/reload", sendData = {}, callback = function (data, status) {
                        updateServiceControlUI('netbird');
                        $("#initialAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
                });
            }, true);
        });


        let data_get_map = {'frm_GeneralSettings': "/api/netbird/settings/get"};
        mapDataToFormUI(data_get_map).done(function (data) {
            updateServiceControlUI('netbird');
            $('.selectpicker').selectpicker('refresh');
        });

        let data_get_map_initial = {'frm_InitialUp': "/api/netbird/initial/get"};
        mapDataToFormUI(data_get_map_initial)
    });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">

</div>

<div class="col-md-12">
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings']) }}
</div>

<div class="col-md-12">
    <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i>
    </button>
</div>

<div class="col-md-12">
    {{ partial("layout_partials/base_form",['fields':initialUpForm,'id':'frm_InitialUp']) }}
</div>

<div class="col-md-12">
    <button disabled="true" class="btn" id="initialAct" type="button"><b>{{ lang._('Setup') }}</b><i
                id="initialAct_progress"></i>
    </button>
</div>

<div class="col-md-12" id="resultdiv" hidden="true">
    <h2>{{ lang._('Result') }}</h2>
    <section id="initialtxt" class="col-xs-11">
    </section>
</div>
