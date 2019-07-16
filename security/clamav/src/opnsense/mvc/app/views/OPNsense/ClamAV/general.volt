{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 – 2019 by Michael Muenz <m.muenz@gmail.com>
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

<div class="alert alert-warning" role="alert" id="dl_sig_alert" style="display:none;min-height:65px;">
    <button class="btn btn-primary pull-right" id="dl_sig" type="button">{{ lang._('Download signatures') }} <i id="dl_sig_progress"></i></button>
    <div style="margin-top: 8px;">{{ lang._('No signature database found, please download before use. The download will take several minutes and this message will disappear when it has been completed.') }}</div>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#lists">{{ lang._('Signatures') }}</a></li>
    <li><a data-toggle="tab" href="#versions">{{ lang._('Versions') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="versions" class="tab-pane fade in">
        <div class="content-box">
            {{ partial("layout_partials/base_form",['fields':versionForm,'id':'frm_version'])}}
        </div>
    </div>
    <div id="lists" class="tab-pane fade in">
        <table id="grid-lists" class="table table-responsive" data-editDialog="dialogEditClamavUrl">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="link" data-type="string" data-visible="true">{{ lang._('URL') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary"  id="saveAct_url" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_url_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditClamavUrl,'id':'dialogEditClamavUrl','label':lang._('Edit Signature URLs')])}}

<script>
function timeoutCheck() {
    ajaxCall(url="/api/clamav/service/freshclam", sendData={}, callback=function(data,status) {
        if (data['status'] == 'done') {
            $("#dl_sig_progress").removeClass("fa fa-spinner fa-pulse");
            $("#dl_sig").prop("disabled", false);
            $('#dl_sig_alert').hide();
        } else {
            setTimeout(timeoutCheck, 2500);
        }
    });
}

$( document ).ready(function() {
    var data_get_map = {'frm_general_settings':"/api/clamav/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    var version_get_map = {'frm_version':"/api/clamav/service/version"};
    mapDataToFormUI(version_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $("#grid-lists").UIBootgrid(
        {   'search':'/api/clamav/url/searchUrl',
            'get':'/api/clamav/url/getUrl/',
            'set':'/api/clamav/url/setUrl/',
            'add':'/api/clamav/url/addUrl/',
            'del':'/api/clamav/url/delUrl/',
            'toggle':'/api/clamav/url/toggleUrl/'
        }
    );

    ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
        updateServiceStatusUI(data['status']);
    });

    ajaxCall(url="/api/clamav/service/freshclam", sendData={}, callback=function(data,status) {
        if (data['status'] != 'done') {
            if (data['status'] == 'running') {
                $("#dl_sig_progress").addClass("fa fa-spinner fa-pulse");
                $("#dl_sig").prop("disabled", true);
                setTimeout(timeoutCheck, 2500);
            }
            $('#dl_sig_alert').show();
        }
    });

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/clamav/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/clamav/service/reconfigure", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['status']);
                });
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_url").click(function(){
        saveFormToEndpoint(url="/api/clamav/url/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_url_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/clamav/service/reconfigure", sendData={}, callback=function(data,status) {
                $("#saveAct_url_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#dl_sig").click(function(){
        $("#dl_sig_progress").addClass("fa fa-spinner fa-pulse");
        $("#dl_sig").prop("disabled", true);
        ajaxCall(url="/api/clamav/service/freshclam", sendData={action:1}, callback_ok=function(){
            setTimeout(timeoutCheck, 2500);
        });
    });

    // update history on tab state and implement navigation
    if(window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });
});
</script>
