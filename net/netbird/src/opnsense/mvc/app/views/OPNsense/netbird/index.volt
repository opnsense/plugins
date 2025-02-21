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