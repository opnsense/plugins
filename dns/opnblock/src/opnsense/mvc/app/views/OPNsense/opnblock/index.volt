<script>
    $(document).ready(function () {
        var data_get_map = {
            'frm_GeneralSettings': "/api/opnblock/settings/get"
        };
        
        mapDataToFormUI(data_get_map).done(function (data) {
        });
        
        $("#saveAct").click(function () {
            saveFormToEndpoint(url = "/api/opnblock/settings/set", formid = 'frm_GeneralSettings', callback_ok = function () {
                ajaxCall(url = "/api/opnblock/service/refresh", sendData = {}, callback = function (data, status) {
                    $("#responseMsg").text(data['message']);
                    });
                });
            });
        });
</script>
<div class="alert alert-info hidden" role="alert" id="responseMsg"> </div>
<div class="col-md-12"> {{ partial("layout_partials/base_form",['fields':general,'id':'frm_GeneralSettings'])}}
    <button style="margin: 30px 0px 30px 0px"; class="btn btn-success" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
    <p><b>REQUIRED:</b> You must include <span style="font-family: Courier; font-size: 13px; border-radius: 5px 5px 5px 5px; background-color: #000; color: #fff; padding: 5px; margin: 5px;">include:/var/unbound/opnblock.conf</span> in your <a href="../services_unbound.php">Unbound DNS</a> configuration for OPNblock to have an affect.</p>
</div>
