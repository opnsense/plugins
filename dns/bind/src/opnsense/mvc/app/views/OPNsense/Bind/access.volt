{#
 #
 # Copyright (c) 2014-2019 Deciso B.V.
 # Copyright (c) 2018-2019 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#acls">{{ lang._('ACLs') }}</a></li>
    <li><a data-toggle="tab" href="#tsig-keys">{{ lang._('TSIG Keys') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="acls" class="tab-pane fade in active">
        <div id="acls-area" class="table-responsive">
            <table id="grid-acls" class="table table-condensed table-hover table-striped" data-editDialog="dialogEditBindAcl">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                        <th data-column-id="networks" data-type="string" data-visible="true" data-css-class="long-str">{{ lang._('Networks') }}</th>
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
                            <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_acl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_acl_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="tsig-keys" class="tab-pane fade in">
        <span id="keygen_div" style="display:none" class="pull-right">
            <button id="keygen" type="button" class="btn btn-secondary" title="{{ lang._('Generate a random base64 key.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        <div id="tsig-keys-area" class="table-responsive">
            <table id="grid-tsig-keys" class="table table-condensed table-hover table-striped" data-editDialog="dialogEditBindTsig">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                        <th data-column-id="algorithm" data-type="string" data-visible="true">{{ lang._('Algorithm') }}</th>
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
                            <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_tsig" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_tsig_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindAcl,'id':'dialogEditBindAcl','label':lang._('Edit ACL')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindTsig,'id':'dialogEditBindTsig','label':lang._('Edit TSIG Key')])}}

<style>
    .long-str {
        word-break: break-word;
    }
</style>
<script>
$(document).ready(function() {
    updateServiceControlUI('bind');

    $("#grid-acls").UIBootgrid({
        'search': '/api/bind/acl/search_acl',
        'get': '/api/bind/acl/get_acl/',
        'set': '/api/bind/acl/set_acl/',
        'add': '/api/bind/acl/add_acl/',
        'del': '/api/bind/acl/del_acl/',
        'toggle': '/api/bind/acl/toggle_acl/'
    });

    $("#grid-tsig-keys").UIBootgrid({
        'search': '/api/bind/tsig/search_key',
        'get': '/api/bind/tsig/get_key/',
        'set': '/api/bind/tsig/set_key/',
        'add': '/api/bind/tsig/add_key/',
        'del': '/api/bind/tsig/del_key/',
        'toggle': '/api/bind/tsig/toggle_key/'
    });

    $("#saveAct_acl").click(function() {
        $("#saveAct_acl_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
            updateServiceControlUI('bind');
            $("#saveAct_acl_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    $("#saveAct_tsig").click(function() {
        $("#saveAct_tsig_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
            updateServiceControlUI('bind');
            $("#saveAct_tsig_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    // move "Generate Key" button into the TSIG dialog's secret field label
    $("#control_label_key\\.secret").append($("#keygen_div").detach().show());
    $("#keygen").click(function(){
        ajaxGet("/api/bind/tsig/generate/", {}, function(data, status){
            if (data && data.secret) {
                $("#key\\.secret").val(data.secret).trigger('change');
            }
        });
    });

    // update history on tab state and implement navigation
    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        history.pushState(null, null, e.target.hash);
    });
});
</script>
