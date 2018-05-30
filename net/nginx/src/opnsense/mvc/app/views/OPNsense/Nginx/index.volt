{#
 # Copyright (C) 2017-2018 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 #  1. Redistributions of source code must retain the above copyright notice,
 #   this list of conditions and the following disclaimer.
 #
 #  2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script>
$( document ).ready(function() {

    var data_get_map = {'frm_nginx':'/api/nginx/settings/get'};

    // load initial data
    mapDataToFormUI(data_get_map).done(function(){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('nginx');
    });

    // update history on tab state and implement navigation
    if(window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });
    $('#nginx\\.general\\.enable_redis_plugin').change(function (evt) {
        $('#missing_redis_plugin').hide();
        if (!window.redis_installed && $(this).is(':checked')) {
            $('#missing_redis_plugin').show();
        }
    });



    // form save event handlers for all defined forms
    $('[id*="save_"]').each(function(){
        $(this).click(function() {
            var frm_id = $(this).closest("form").attr("id");
            var frm_title = $(this).closest("form").attr("data-title");
            // save data for General TAB
            saveFormToEndpoint(url="/api/nginx/settings/set", formid=frm_id, callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                ajaxCall(url="/api/nginx/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");

                    if (status != "success" || data['status'] != 'ok' ) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: frm_title,
                            message: JSON.stringify(data),
                            draggable: true
                        });
                    } else {
                        updateServiceControlUI('nginx');
                    }
                });
            });
        });
    });
    ['upstream', 'upstreamserver', 'location', 'credential', 'userlist', 'httpserver'].forEach(function(element) {
        $("#grid-" + element).UIBootgrid(
            { 'search':'/api/nginx/settings/search' + element,
              'get':'/api/nginx/settings/get' + element + '/',
              'set':'/api/nginx/settings/set' + element + '/',
              'add':'/api/nginx/settings/add' + element + '/',
              'del':'/api/nginx/settings/del' + element + '/',
              // 'toggle':'/api/nginx/settings/toggle' + element + '/',
              'options':{selection:false, multiSelect:false}
            }
        );
    });

});


</script>


<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settings]) }}
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-http-location').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('HTTP(S)')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-location" href="#subtab_nginx-http-location">{{ lang._('Location')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-credential" href="#subtab_nginx-http-credential">{{ lang._('Credential')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-userlist" href="#subtab_nginx-http-userlist">{{ lang._('User List')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-upstream-server" href="#subtab_nginx-http-upstream-server">{{ lang._('Upstream Server')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-upstream" href="#subtab_nginx-http-upstream">{{ lang._('Upstream')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-server" href="#subtab_nginx-http-httpserver">{{ lang._('HTTP Server')}}</a>
            </li>
        </ul>
    </li>
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':settings]) }}
    <div id="subtab_nginx-http-location" class="tab-pane fade">
        <table id="grid-location" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="locationdlg">
            <thead>
            <tr>
                <!--<th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="6em">{{ lang._('Enabled') }}</th>-->
                <th data-column-id="urlpattern" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('URL Pattern') }}</th>
                <th data-column-id="matchtype" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Match Type') }}</th>
                <th data-column-id="enable_secrules" data-type="boolean" data-sortable="true"  data-visible="true">{{ lang._('WAF Enabled') }}</th>
                <th data-column-id="force_https" data-type="boolean" data-sortable="true"  data-visible="true">{{ lang._('Force HTTPS') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div id="subtab_nginx-http-upstream-server" class="tab-pane fade">
        <table id="grid-upstreamserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="upstreamserverdlg">
            <thead>
            <tr>
                <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="server" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Server') }}</th>
                <th data-column-id="priority" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Priority') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    
    
    <div id="subtab_nginx-http-upstream" class="tab-pane fade">
        <table id="grid-upstream" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="upstreamdlg">
            <thead>
            <tr>
                <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="serverentries" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Servers') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-credential" class="tab-pane fade">
        <table id="grid-credential" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="credentialdlg">
            <thead>
            <tr>
                <th data-column-id="username" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-userlist" class="tab-pane fade">
        <table id="grid-userlist" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="userlistdlg">
            <thead>
            <tr>
                <th data-column-id="name" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Name') }}</th>
                <th data-column-id="users" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Users') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-httpserver" class="tab-pane fade">
        <table id="grid-httpserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="httpserverdlg">
            <thead>
                <tr>
                    <th data-column-id="servername" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Servername') }}</th>
                    <th data-column-id="https_only" data-type="boolean" data-sortable="true" data-visible="true">{{ lang._('HTTPS Only') }}</th>
                    <th data-column-id="certificate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Certificate') }}</th>
                    <th data-column-id="listen_http_port" data-type="string" data-sortable="true" data-visible="true">{{ lang._('HTTP Port') }}</th>
                    <th data-column-id="listen_https_port" data-type="string" data-sortable="true" data-visible="true">{{ lang._('HTTPS Port') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>



{{ partial("layout_partials/base_dialog",['fields': upstream,'id':'upstreamdlg', 'label':lang._('Edit Upstream')]) }}
{{ partial("layout_partials/base_dialog",['fields': upstream_server,'id':'upstreamserverdlg', 'label':lang._('Edit Upstream')]) }}
{{ partial("layout_partials/base_dialog",['fields': location,'id':'locationdlg', 'label':lang._('Edit Location')]) }}
{{ partial("layout_partials/base_dialog",['fields': credential,'id':'credentialdlg', 'label':lang._('Edit Credential')]) }}
{{ partial("layout_partials/base_dialog",['fields': userlist,'id':'userlistdlg', 'label':lang._('Edit User List')]) }}
{{ partial("layout_partials/base_dialog",['fields': httpserver,'id':'httpserverdlg', 'label':lang._('Edit HTTP Server')]) }}

