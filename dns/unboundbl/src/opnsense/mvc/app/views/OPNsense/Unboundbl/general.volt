{#

OPNsense® is Copyright © 2014 – 2018 by Deciso B.V.
This file is Copyright © 2018 by Alec Samuel Armbruster <alecsamuelarmbruster@gmail.com>
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

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':general,'id':'GeneralSettings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i></button>
            </div>
        </div>
        <p> <b>{{ lang._('You must include') }} <span style="font-family: Courier; font-size: 13px; border-radius: 5px 5px 5px 5px; background-color: #000; color: #fff; padding: 5px; margin: 5px;">include:/var/unbound/UnboundBL.conf</span> {{ lang._('in your') }} <a href="../services_unbound.php">Unbound DNS</a> {{ lang._('advanced settings configuration') }}! </p>
    </div>
</div>

<script>
    $(document).ready(function () {
        var data_get_map = {
            'frm_GeneralSettings': "/api/unboundbl/general/get"
        };
        mapDataToFormUI(data_get_map).done(function (data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        mapDataToFormUI(data_get_map).done(function (data) {});
        $("#saveAct").click(function () {
            saveFormToEndpoint(url = "/api/unboundbl/general/set", formid = 'frm_GeneralSettings', callback_ok = function () {
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
