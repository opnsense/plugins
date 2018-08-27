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
                    $("#responseMsg").html(data['message']);
                    });
                });
            });
        });
</script>
<div class="alert alert-info hidden" role="alert" id="responseMsg"> </div>
<div class="col-md-6"> {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
    <br>
    <br>
    <button class="btn btn-success" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
</div>
<div class="col-md-6">
    <h2>Welcome to OPNblock!</h2>
    <p> OPNblock helps you easily manage ad-blocking blackholes for use with <a href="../services_unbound.php">Unbound DNS</a>. Simply fill out the whitelist with regex entries (separated by a space) and paste in your blacklist URL's (also separated by a space). Please make sure you enable 'full help' to ensure your experience is smooth.
        <br>
        <br> <b>REQUIRED:</b> You must include <span style="font-family: Courier; font-size: 13px; border-radius: 5px 5px 5px 5px; background-color: #000; color: #fff; padding: 5px; margin: 5px;">include:/var/unbound/opnblock.conf</span> in your <a href="../services_unbound.php">Unbound DNS</a> configuration for OPNblock to have an affect.
        <br>
        <br> Coming soon:
        <br>
        <ul>
            <li>cron jobs!</li>
            <li>enter your own ip to replace '0.0.0.0'</li>
            <li>preset lists</li>
            <li>statistics</li>
            <li>proper error handling</li>
            <li>whatever else you suggest?</li>
        </ul>
    </p>
    <br> <span style="font-family: Courier; font-size: 13px; border-radius: 5px 5px 5px 5px; background-color: #fff; color: #000; padding: 5px; margin: 5px;">You're using OPNblock version: 0.1-dev</span>
    <br>
    <br> </div>
