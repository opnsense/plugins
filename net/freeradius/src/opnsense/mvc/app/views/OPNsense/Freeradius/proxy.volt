{#

OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

<script>
$( document ).ready(function() {
    var data_get_map = {'frm_zabbixagent':"/api/freeradius/proxy/get"};

    // load initial data
    mapDataToFormUI(data_get_map).done(function(){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('freeradius');
    });

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-homeservers").UIBootgrid(
            {   'search':'/api/freeradius/proxy/searchHomeserver',
                'get':'/api/freeradius/proxy/getHomeserver/',
                'set':'/api/freeradius/proxy/setHomeserver/',
                'add':'/api/freeradius/proxy/addHomeserver/',
                'del':'/api/freeradius/proxy/delHomeserver/',
                'toggle':'/api/freeradius/proxy/toggleHomeserver/',
            options: {
                rowCount:[10,25,50,100,500,1000]
                }
            }
        );
        $("#grid-homeserverpools").UIBootgrid(
            {   'search':'/api/freeradius/proxy/searchHomeserverpool',
                'get':'/api/freeradius/proxy/getHomeserverpool/',
                'set':'/api/freeradius/proxy/setHomeserverpool/',
                'add':'/api/freeradius/proxy/addHomeserverpool/',
                'del':'/api/freeradius/proxy/delHomeserverpool/',
                'toggle':'/api/freeradius/proxy/toggleHomeserverpool/',
            options: {
                rowCount:[10,25,50,100,500,1000]
                }
            }
        );
        $("#grid-realms").UIBootgrid(
            {   'search':'/api/freeradius/proxy/searchRealm',
                'get':'/api/freeradius/proxy/getRealm/',
                'set':'/api/freeradius/proxy/setRealm/',
                'add':'/api/freeradius/proxy/addRealm/',
                'del':'/api/freeradius/proxy/delRealm/',
                'toggle':'/api/freeradius/proxy/toggleRealm/',
            options: {
                rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

    // form save event handlers for all defined forms
    $('[id*="save_"]').each(function(){
        $(this).click(function(){
            var frm_id = $(this).closest("form").attr("id");
            var frm_title = $(this).closest("form").attr("data-title");

            // save data for tab
            saveFormToEndpoint(url="/api/freeradius/proxy/set",formid=frm_id,callback_ok=function(){
                // set progress animation when reloading
                $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                // on correct save, perform restart
                ajaxCall(url="/api/freeradius/proxy/reconfigure", sendData={}, callback=function(data,status){
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
                        updateServiceControlUI('freeradius');
                    }
                    // when done, disable progress animation.
                    $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });

        /**
         * Reconfigure
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/freeradius/service/reconfigure", sendData={}, callback=function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
                updateServiceControlUI('freeradius');
                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error reconfiguring FreeRADIUS') }}",
                        message: data['status'],
                        draggable: true
                    });
                } else {
                    ajaxCall(url="/api/freeradius/service/reconfigure", sendData={});
                }
            });
        });
    });
</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
            <li class= "active"><a data-toggle="tab" id="homeservers-tab" href="#homeservers">{{ lang._('Home Servers') }}</a></li>
            <li><a data-toggle="tab" id="homeserverpools-tab" href="#homeserverpools">{{ lang._('Home Servers Pool') }}</a></li>
            <li><a data-toggle="tab" id="realms-tab" href="#realms">{{ lang._('Realms') }}</a></li>
</ul>

<div class="content-box tab-content col-xs-12 __mb">
    {# manually add tab content #}
    <div id="homeservers" class="tab-pane fade in active">
        <!-- tab page "homeservers" -->
        <table id="grid-homeservers" class="table table-condensed table-hover table-striped" data-editDialog="dialogEditFreeRADIUSHomeserver" data-editAlert="OverrideChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    <div id="homeserverpools" class="tab-pane fade in">
        <!-- tab page "homeserverpools" -->
        <table id="grid-homeserverpools" class="table table-condensed table-hover table-striped" data-editDialog="dialogEditFreeRADIUSHomeserverpool"  data-editAlert="OverrideChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="type" data-type="dropdown">{{ lang._('Type') }}</th>
                <th data-column-id="virtualserver" data-type="string">{{ lang._('Virtual Server') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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

    <div id="realms" class="tab-pane fade in">
        <!-- tab page "realms" -->
        <table id="grid-realms" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFreeRADIUSRealm"  data-editAlert="OverrideChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                <th data-column-id="auth_pool" data-type="string" data-visible="true">{{ lang._('Authentication Pool') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
<div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
        <br/><br/>
</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSHomeserver,'id':'dialogEditFreeRADIUSHomeserver','label':lang._('Edit Homeservers')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSHomeserverpool,'id':'dialogEditFreeRADIUSHomeserverpool','label':lang._('Edit Homeserverpools')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSRealm,'id':'dialogEditFreeRADIUSRealm','label':lang._('Edit Realms')])}}
