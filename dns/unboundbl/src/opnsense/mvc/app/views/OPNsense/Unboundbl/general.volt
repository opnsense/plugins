{#

OPNsense® is Copyright © 2014 – 2018 by Deciso B.V.
This file is Copyright © 2019 by Alec Samuel Armbruster <alectrocute@gmail.com>
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
