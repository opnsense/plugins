<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>
<div class="row">
<div class="col-lg-8 col-md-12">
   <div class="content-box">
      {{ partial("layout_partials/base_form",['fields':general,'id':'frm_general_settings'])}}
      <hr />
      <button class="btn btn-primary" style="margin-bottom: 15px; margin-left: 15px;" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button><i style="margin-left: 15px" id="saveAct_progress"></i>
   </div>
</div>
<div class="col-lg-4 col-md-12">
<div class="content-box" style="margin-top: 3px;">
   <div style="padding: 15px;">
      <h3><i class="fa fa-wrench" style="margin-right: 7px;"></i> {{ lang._('Configuration') }}</h3>
      <p>{{ lang._('To activate DNSBL, go to:') }}</p>
      <p><a href="/services_unbound.php">{{ lang._('Unbound DNS') }}</a> &rarr; {{ lang._('General') }} &rarr; {{ lang._('Custom options') }} </p>
      <p>{{ lang._('And add the following configuration line:') }}</p>
      <p><span style="font-family: Courier; font-size: 12px; border-radius: 5px 5px 5px 5px; background-color: #f3f3f3; color: #000; padding: 5px;">include:/var/unbound/UnboundBL.conf</span> </p>
      <hr />
      <h3><i class="fa fa-question" style="margin-right: 7px;"></i> {{ lang._('Troubleshooting') }}</h3>
      <p>{{ lang._('Only use blacklist files, which will appear to be lists of domain names in a .txt format. Do not enter specific hostnames, IP address nor domains in the blacklist field.') }}</p>
      <p>{{ lang._('For whitelisting, enter a specific domain names which you would like removed from the blacklist entries.') }}</p>
   </div>
</div>
<script>
    $(document).ready(function () {
        var data_get_map = {
            'frm_general_settings': "/api/unboundbl/general/get"
        };
        mapDataToFormUI(data_get_map).done(function (data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        mapDataToFormUI(data_get_map).done(function (data) {});
        $("#saveAct").click(function () {
            saveFormToEndpoint(url = "/api/unboundbl/general/set", formid = 'frm_general_settings', callback_ok = function () {
                $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url = "/api/unboundbl/service/refresh", sendData = {}, callback = function (data, status) {
                    ajaxCall(url = "/api/unboundbl/service/reload", sendData = {}, callback = function (data, status) {
                        $("#responseMsg").text(data['message']);
                    });
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });
</script>
