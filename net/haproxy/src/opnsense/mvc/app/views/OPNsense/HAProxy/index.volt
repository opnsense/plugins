{#

Copyright (C) 2016 Frank Wall
OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
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

<script type="text/javascript">

    $( document ).ready(function() {

        var data_get_map = {'frm_haproxy':"/api/haproxy/settings/get"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // request service status on load and update status box
            ajaxCall(url="/api/haproxy/service/status", sendData={}, callback=function(data,status) {
                updateServiceStatusUI(data['status']);
            });
        });

        /***********************************************************************
         * link grid actions
         **********************************************************************/

        $("#grid-frontends").UIBootgrid(
            {   search:'/api/haproxy/settings/searchFrontends',
                get:'/api/haproxy/settings/getFrontend/',
                set:'/api/haproxy/settings/setFrontend/',
                add:'/api/haproxy/settings/addFrontend/',
                del:'/api/haproxy/settings/delFrontend/',
                toggle:'/api/haproxy/settings/toggleFrontend/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-backends").UIBootgrid(
            {   search:'/api/haproxy/settings/searchBackends',
                get:'/api/haproxy/settings/getBackend/',
                set:'/api/haproxy/settings/setBackend/',
                add:'/api/haproxy/settings/addBackend/',
                del:'/api/haproxy/settings/delBackend/',
                toggle:'/api/haproxy/settings/toggleBackend/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-servers").UIBootgrid(
            {   search:'/api/haproxy/settings/searchServers',
                get:'/api/haproxy/settings/getServer/',
                set:'/api/haproxy/settings/setServer/',
                add:'/api/haproxy/settings/addServer/',
                del:'/api/haproxy/settings/delServer/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-healthchecks").UIBootgrid(
            {   search:'/api/haproxy/settings/searchHealthchecks',
                get:'/api/haproxy/settings/getHealthcheck/',
                set:'/api/haproxy/settings/setHealthcheck/',
                add:'/api/haproxy/settings/addHealthcheck/',
                del:'/api/haproxy/settings/delHealthcheck/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-actions").UIBootgrid(
            {   search:'/api/haproxy/settings/searchActions',
                get:'/api/haproxy/settings/getAction/',
                set:'/api/haproxy/settings/setAction/',
                add:'/api/haproxy/settings/addAction/',
                del:'/api/haproxy/settings/delAction/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-acls").UIBootgrid(
            {   search:'/api/haproxy/settings/searchAcls',
                get:'/api/haproxy/settings/getAcl/',
                set:'/api/haproxy/settings/setAcl/',
                add:'/api/haproxy/settings/addAcl/',
                del:'/api/haproxy/settings/delAcl/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-luas").UIBootgrid(
            {   search:'/api/haproxy/settings/searchLuas',
                get:'/api/haproxy/settings/getLua/',
                set:'/api/haproxy/settings/setLua/',
                add:'/api/haproxy/settings/addLua/',
                del:'/api/haproxy/settings/delLua/',
                toggle:'/api/haproxy/settings/toggleLua/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-errorfiles").UIBootgrid(
            {   search:'/api/haproxy/settings/searchErrorfiles',
                get:'/api/haproxy/settings/getErrorfile/',
                set:'/api/haproxy/settings/setErrorfile/',
                add:'/api/haproxy/settings/addErrorfile/',
                del:'/api/haproxy/settings/delErrorfile/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        // hook into on-show event for dialog to extend layout.
        $('#DialogAcl').on('shown.bs.modal', function (e) {
            $("#acl\\.expression").change(function(){
                var service_id = 'table_' + $(this).val();
                console.log("[debug_X] " + service_id + " | " + "\n")
                $(".expression_table").hide();
                // $(".table_"+$(this).val()).show();
                $("."+service_id).show();
            });
            $("#acl\\.expression").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogAction').on('shown.bs.modal', function (e) {
            $("#action\\.type").change(function(){
                var service_id = 'table_' + $(this).val();
                console.log("[debug_Y] " + service_id + " | " + "\n")
                $(".type_table").hide();
                // $(".table_"+$(this).val()).show();
                $("."+service_id).show();
            });
            $("#action\\.type").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogHealthcheck').on('shown.bs.modal', function (e) {
            $("#healthcheck\\.type").change(function(){
                var service_id = 'table_' + $(this).val();
                console.log("[debug_Z] " + service_id + " | " + "\n")
                $(".type_table").hide();
                // $(".table_"+$(this).val()).show();
                $("."+service_id).show();
            });
            $("#healthcheck\\.type").change();
        })

        /***********************************************************************
         * Commands
         **********************************************************************/

        // Reconfigure haproxy - activate changes
        $('[id*="reconfigureAct"]').each(function(){
            $(this).click(function(){

            // set progress animation
            $('[id*="reconfigureAct_progress"]').each(function(){
                $(this).addClass("fa fa-spinner fa-pulse");
            });
            // first run syntax check to catch critical errors
            ajaxCall(url="/api/haproxy/service/configtest", sendData={}, callback=function(data,status) {
                // show warning in case of critical errors
                if (data['result'].indexOf('ALERT') > -1) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_DANGER,
                        title: "{{ lang._('HAProxy config contains critical errors') }}",
                        message: "{{ lang._('The HAProxy service may not be able to start due to critical errors. Try anyway?') }}",
                        buttons: [{
                            label: '{{ lang._('Continue') }}',
                            cssClass: 'btn-primary',
                            action: function(dlg){
                                ajaxCall(url="/api/haproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                                    if (status != "success" || data['status'] != 'ok') {
                                        BootstrapDialog.show({
                                            type: BootstrapDialog.TYPE_WARNING,
                                            title: "{{ lang._('Error reconfiguring HAProxy') }}",
                                            message: data['status'],
                                            draggable: true
                                        });
                                    }
                                });
                                // when done, disable progress animation
                                $('[id*="reconfigureAct_progress"]').each(function(){
                                    $(this).removeClass("fa fa-spinner fa-pulse");
                                });
                                dlg.close();
                            }
                        }, {
                            icon: 'fa fa-trash-o',
                            label: '{{ lang._('Abort') }}',
                            action: function(dlg){
                                // when done, disable progress animation
                                $('[id*="reconfigureAct_progress"]').each(function(){
                                    $(this).removeClass("fa fa-spinner fa-pulse");
                                });
                                dlg.close();
                            }
                        }]
                    });
                } else {
                    ajaxCall(url="/api/haproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                        if (status != "success" || data['status'] != 'ok') {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_WARNING,
                                title: "{{ lang._('Error reconfiguring HAProxy') }}",
                                message: data['status'],
                                draggable: true
                            });
                        }
                        // when done, disable progress animation
                        $('[id*="reconfigureAct_progress"]').each(function(){
                            $(this).removeClass("fa fa-spinner fa-pulse");
                        });
                    });
                }
            });
            });
        });

        // Test configuration file
        $('[id*="configtestAct"]').each(function(){
            $(this).click(function(){

            // set progress animation
            $('[id*="configtestAct_progress"]').each(function(){
                $(this).addClass("fa fa-spinner fa-pulse");
            });

            ajaxCall(url="/api/haproxy/service/configtest", sendData={}, callback=function(data,status) {
                // when done, disable progress animation
                $('[id*="configtestAct_progress"]').each(function(){
                    $(this).removeClass("fa fa-spinner fa-pulse");
                });

                if (data['result'].indexOf('ALERT') > -1) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_DANGER,
                        title: "{{ lang._('HAProxy config contains critical errors') }}",
                        message: data['result'],
                        draggable: true
                    });
                } else if (data['result'].indexOf('WARNING') > -1) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('HAProxy config contains minor errors') }}",
                        message: data['result'],
                        draggable: true
                    });
                } else {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('HAProxy config test result') }}",
                        message: "{{ lang._('Your HAProxy config contains no errors.') }}",
                        draggable: true
                    });
                }
            });
            });
        });

        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function(){
                var frm_id = $(this).closest("form").attr("id");
                var frm_title = $(this).closest("form").attr("data-title");
                // save data for tab
                saveFormToEndpoint(url="/api/haproxy/settings/set",formid=frm_id,callback_ok=function(){
                    // set progress animation when reloading
                    $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                    // on correct save, perform reconfigure
                    ajaxCall(url="/api/haproxy/service/reconfigure", sendData={}, callback=function(data,status){
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
                            // request service status after successful save and update status box (wait a few seconds before update)
                            setTimeout(function(){
                                ajaxCall(url="/api/haproxy/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                                });
                            },3000);
                        }
                    });
                });
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

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
{% for tab in mainForm['tabs']|default([]) %}
    {% if tab['subtabs']|default(false) %}
        {# Tab with dropdown #}

        {# Find active subtab #}
            {% set active_subtab="" %}
            {% for subtab in tab['subtabs']|default({}) %}
                {% if subtab[0]==mainForm['activetab']|default("") %}
                    {% set active_subtab=subtab[0] %}
                {% endif %}
            {% endfor %}

        <li role="presentation" class="dropdown {% if mainForm['activetab']|default("") == active_subtab %}active{% endif %}">
            <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button" style="border-left: 1px dashed lightgray;">
                <b><span class="caret"></span></b>
            </a>
            <a data-toggle="tab" href="#subtab_{{tab['subtabs'][0][0]}}" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{tab[1]}}</b></a>
            <ul class="dropdown-menu" role="menu">
                {% for subtab in tab['subtabs']|default({})%}
                <li class="{% if mainForm['activetab']|default("") == subtab[0] %}active{% endif %}"><a data-toggle="tab" href="#subtab_{{subtab[0]}}"><i class="fa fa-check-square"></i> {{subtab[1]}}</a></li>
                {% endfor %}
            </ul>
        </li>
    {% else %}
        {# Standard Tab #}
        <li {% if mainForm['activetab']|default("") == tab[0] %} class="active" {% endif %}>
                <a data-toggle="tab" href="#tab_{{tab[0]}}">
                    <b>{{tab[1]}}</b>
                </a>
        </li>
    {% endif %}
{% endfor %}
    {# add custom content #}
    <li><a data-toggle="tab" href="#frontends"><b>{{ lang._('Frontends') }}</b></a></li>
    <li><a data-toggle="tab" href="#backends"><b>{{ lang._('Backends') }}</b></a></li>
    <li><a data-toggle="tab" href="#servers"><b>{{ lang._('Servers') }}</b></a></li>
    <li><a data-toggle="tab" href="#healthchecks"><b>{{ lang._('Health Checks') }}</b></a></li>
    <li><a data-toggle="tab" href="#actions"><b>{{ lang._('Actions') }}</b></a></li>
    <li><a data-toggle="tab" href="#acls"><b>{{ lang._('ACLs') }}</b></a></li>
    <li><a data-toggle="tab" href="#luas"><b>{{ lang._('Lua Scripts') }}</b></a></li>
    <li><a data-toggle="tab" href="#errorfiles"><b>{{ lang._('Error Files') }}</b></a></li>
</ul>

<div class="content-box tab-content">
    {% for tab in mainForm['tabs']|default([]) %}
        {% if tab['subtabs']|default(false) %}
            {# Tab with dropdown #}
            {% for subtab in tab['subtabs']|default({})%}
                <div id="subtab_{{subtab[0]}}" class="tab-pane fade{% if mainForm['activetab']|default("") == subtab[0] %} in active {% endif %}">
                    {{ partial("layout_partials/base_form",['fields':subtab[2],'id':'frm_'~subtab[0],'data_title':subtab[1],'apply_btn_id':'save_'~subtab[0]])}}
                </div>
            {% endfor %}
        {% endif %}
        {% if tab['subtabs']|default(false)==false %}
            <div id="tab_{{tab[0]}}" class="tab-pane fade{% if mainForm['activetab']|default("") == tab[0] %} in active {% endif %}">
                {{ partial("layout_partials/base_form",['fields':tab[2],'id':'frm_'~tab[0],'apply_btn_id':'save_'~tab[0]])}}
            </div>
        {% endif %}
    {% endfor %}

    <div id="frontends" class="tab-pane fade">
        <!-- tab page "frontends" -->
        <table id="grid-frontends" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogFrontend">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="frontendid" data-type="number"  data-visible="false">{{ lang._('Frontend ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Frontend Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-frontends" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-frontends" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="backends" class="tab-pane fade">
        <!-- tab page "backends" -->
        <table id="grid-backends" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogBackend">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="backendid" data-type="number"  data-visible="false">{{ lang._('Backend ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Backend Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-backends" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-backends" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="servers" class="tab-pane fade">
        <!-- tab page "servers" -->
        <table id="grid-servers" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogServer">
            <thead>
            <tr>
                <th data-column-id="serverid" data-type="number"  data-visible="false">{{ lang._('Server id') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Server Name') }}</th>
                <th data-column-id="address" data-type="string">{{ lang._('Server Address') }}</th>
                <th data-column-id="port" data-type="string">{{ lang._('Server Port') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-servers" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-servers" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="healthchecks" class="tab-pane fade">
        <!-- tab page "healthchecks" -->
        <table id="grid-healthchecks" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogHealthcheck">
            <thead>
            <tr>
                <th data-column-id="healthcheckid" data-type="number"  data-visible="false">{{ lang._('Health Check ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Health Check Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-healthchecks" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-healthchecks" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="actions" class="tab-pane fade">
        <!-- tab page "actions" -->
        <table id="grid-actions" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAction">
            <thead>
            <tr>
                <th data-column-id="actionid" data-type="number"  data-visible="false">{{ lang._('Action ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Action Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-actions" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-actions" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="acls" class="tab-pane fade">
        <!-- tab page "acls" -->
        <table id="grid-acls" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAcl">
            <thead>
            <tr>
                <th data-column-id="aclid" data-type="number"  data-visible="false">{{ lang._('ACL id') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('ACL Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-acls" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-acls" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="luas" class="tab-pane fade">
        <!-- tab page "luas" -->
        <table id="grid-luas" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogLua">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="luaid" data-type="number"  data-visible="false">{{ lang._('Lua ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Lua Script Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-luas" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-luas" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>

    <div id="errorfiles" class="tab-pane fade">
        <!-- tab page "errorfiles" -->
        <table id="grid-errorfiles" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogErrorfile">
            <thead>
            <tr>
                <th data-column-id="errorfileid" data-type="number"  data-visible="false">{{ lang._('Error File ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <!-- apply button -->
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct-errorfiles" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
            <button class="btn btn-primary" id="configtestAct-errorfiles" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>
</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogFrontend,'id':'DialogFrontend','label':lang._('Edit Frontend')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogBackend,'id':'DialogBackend','label':lang._('Edit Backend')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogServer,'id':'DialogServer','label':lang._('Edit Server')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHealthcheck,'id':'DialogHealthcheck','label':lang._('Edit Health Check')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAction,'id':'DialogAction','label':lang._('Edit Action')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAcl,'id':'DialogAcl','label':lang._('Edit ACL')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogLua,'id':'DialogLua','label':lang._('Edit Lua Script')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogErrorfile,'id':'DialogErrorfile','label':lang._('Edit Error File')])}}
