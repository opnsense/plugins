<div class="alert alert-info hidden" role="alert" id="responseMsg"> </div>
<div class="col-md-12"> {{ partial("layout_partials/base_form",['fields':general,'id':'frm_GeneralSettings']) }}
    <button style="margin: 30px 0px 30px 0px;" class="btn btn-success" id="saveAct" type="button"> <b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i> </button>
    <p> <b>{{ lang._('REQUIRED') }}:</b> {{ lang._('You must include') }} <span style="font-family: Courier; font-size: 13px; border-radius: 5px 5px 5px 5px; background-color: #000; color: #fff; padding: 5px; margin: 5px;">include:/var/unbound/UnboundBL.conf</span> {{ lang._('in your') }} <a href="../services_unbound.php">Unbound DNS</a> {{ lang._('advanced settings configuration') }}! </p>
</div>
<script>
    $(document).ready(function () {
        var data_get_map = {
            'frm_GeneralSettings': "/api/UnboundBL/settings/get"
        };
        mapDataToFormUI(data_get_map).done(function (data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        mapDataToFormUI(data_get_map).done(function (data) {});
        $("#saveAct").click(function () {
            saveFormToEndpoint(url = "/api/UnboundBL/settings/set", formid = 'frm_GeneralSettings', callback_ok = function () {
                $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url = "/api/UnboundBL/service/refresh", sendData = {}, callback = function (data, status) {
                    ajaxCall(url = "/api/UnboundBL/service/reload", sendData = {}, callback = function (data, status) {
                        $("#responseMsg").text(data['message']);
                    });
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });
</script>
