{#

Copyright (C) 2016-2017 Frank Wall
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
                $(".type_table").hide();
                // $(".table_"+$(this).val()).show();
                $("."+service_id).show();
            });
            $("#action\\.type").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogBackend').on('shown.bs.modal', function (e) {
            $("#backend\\.healthCheckEnabled").change(function(){
                var service_id = 'table_healthcheck_' + $(this).is(':checked');
                $(".healthcheck_table").hide();
                $("."+service_id).show();
            });
            $("#backend\\.healthCheckEnabled").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogFrontend').on('shown.bs.modal', function (e) {
            $("#frontend\\.mode").change(function(){
                var service_id = 'table_' + $(this).val();
                $(".mode_table").hide();
                $("."+service_id).show();
            });
            $("#frontend\\.mode").change();

            // show/hide SSL offloading
            $("#frontend\\.ssl_enabled").change(function(){
                var service_id = 'table_ssl_' + $(this).is(':checked');
                $(".table_ssl").hide();
                $("."+service_id).show();
            });
            $("#frontend\\.ssl_enabled").change();

            // show/hide advanced SSL settings
            $("#frontend\\.ssl_advancedEnabled").change(function(){
                var service_id = 'table_ssl_advanced_' + $(this).is(':checked');
                $(".table_ssl_advanced").hide();
                $("."+service_id).show();
            });
            $("#frontend\\.ssl_advancedEnabled").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogHealthcheck').on('shown.bs.modal', function (e) {
            $("#healthcheck\\.type").change(function(){
                var service_id = 'table_' + $(this).val();
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
                            ajaxCall(url="/api/haproxy/service/status", sendData={}, callback=function(data,status) {
                                updateServiceStatusUI(data['status']);
                            });
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
    {# manually add tabs #}
    <li class="active"><a data-toggle="tab" href="#introduction"><b>{{ lang._('Introduction') }}</b></a></li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#real-servers-introduction').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Real Servers') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li><a data-toggle="tab" id="real-servers-introduction" href="#subtab_haproxy-real-servers-introduction">{{ lang._('Introduction') }}</a></li>
            <li><a data-toggle="tab" href="#servers">{{ lang._('Real Servers') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#virtual-services-introduction').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Virtual Services') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li><a data-toggle="tab" id="virtual-services-introduction" href="#subtab_haproxy-virtual-services-introduction">{{ lang._('Introduction') }}</a></li>
            <li><a data-toggle="tab" href="#backends">{{ lang._('Backend Pools') }}</a></li>
            <li><a data-toggle="tab" href="#frontends">{{ lang._('Public Services') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#rules-checks-introduction').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Rules & Checks') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li><a data-toggle="tab" id="rules-checks-introduction" href="#subtab_haproxy-rules-checks-introduction">{{ lang._('Introduction') }}</a></li>
            <li><a data-toggle="tab" href="#healthchecks">{{ lang._('Health Monitors') }}</a></li>
            <li><a data-toggle="tab" href="#acls">{{ lang._('Conditions') }}</a></li>
            <li><a data-toggle="tab" href="#actions">{{ lang._('Rules') }}</a></li>
        </ul>
    </li>

    {# add automatically generated tabs #}
    {% for tab in mainForm['tabs']|default([]) %}
        {% if tab['subtabs']|default(false) %}
        {# Tab with dropdown #}
        <li role="presentation" class="dropdown">
            <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
                <b><span class="caret"></span></b>
            </a>
            <a data-toggle="tab" onclick="$('#subtab_item_{{tab['subtabs'][0][0]}}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{tab[1]}}</b></a>
            <ul class="dropdown-menu" role="menu">
                {% for subtab in tab['subtabs']|default({})%}
                <li><a data-toggle="tab" id="subtab_item_{{subtab[0]}}" href="#subtab_{{subtab[0]}}">{{subtab[1]}}</a></li>
                {% endfor %}
            </ul>
        </li>
        {% else %}
        {# Standard Tab #}
        <li>
                <a data-toggle="tab" href="#tab_{{tab[0]}}">
                    <b>{{tab[1]}}</b>
                </a>
        </li>
        {% endif %}
    {% endfor %}

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#advanced-introduction').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Advanced') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li><a data-toggle="tab" id="advanced-introduction" href="#subtab_haproxy-rules-checks-introduction">{{ lang._('Introduction') }}</a></li>
            <li><a data-toggle="tab" href="#errorfiles">{{ lang._('Error Messages') }}</a></li>
            <li><a data-toggle="tab" href="#luas">{{ lang._('Lua Scripts') }}</a></li>
        </ul>
    </li>
</ul>

<div class="content-box tab-content">
    <div id="introduction" class="tab-pane fade in active">
        <div class="col-md-12">
            <h1>Quick Start Guide</h1>
            <p>{{ lang._('Welcome to the HAProxy plugin! This plugin is designed to offer all the features and flexibility HAProxy is famous for. If you are using HAProxy for the first time, please take some time to get familiar with it. The following information should help you to get started.')}}</p>
            <p>{{ lang._('Note that you should configure HAProxy in the following order:') }}</p>
            <ul>
              <li>{{ lang._('Add %sReal Servers:%s All physical or virtual servers that HAProxy should use to load balance between or proxy to.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('Add %sBackend Pools:%s Group the previously added servers to build a server farm. All servers in a group usually deliver the same content. The Backend Pool takes care of health monitoring and load distribution. A Backend Pool must be configured even if you only have a single server.') | format('<b>', '</b>')}}</li>
              <li>{{ lang._('Add %sPublic Services:%s The Public Service listens for client connections, optionally applies rules and forwards client request data to the selected Backend Pool for load balancing or proxying.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('Lastly, enable HAProxy using the %sService Settings%s.') | format('<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._('Please be aware that you need to %smanually%s add the required firewall rules for all configured services.') | format('<b>', '</b>') }}</p>
            <p>{{ lang._('Further information is available in our %sHAProxy plugin documentation%s and of course in the %sofficial HAProxy documentation%s. Be sure to report bugs and request features on our %sGitHub issue page%s. Code contributions are also very welcome!') | format('<a href="https://docs.opnsense.org/manual/how-tos/haproxy.html" target="_blank">', '</a>', '<a href="http://cbonte.github.io/haproxy-dconv/1.7/configuration.html" target="_blank">', '</a>', '<a href="https://github.com/opnsense/plugins/issues/" target="_blank">', '</a>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-real-servers-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>Real Servers</h1>
            <p>{{ lang._('HAProxy needs to know which servers should be used to serve content. The following minimum information must be provided for each server:') }}</p>
            <ul>
              <li>{{ lang._('%sFQDN or IP:%s The IP address or fully-qualified domain name that should be used when communicating with your server.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('%sPort:%s The TCP or UDP port that should be used. If unset, the same port the client connected to will be used.') | format('<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("Please note that advanced mode settings allow you to disable a certain server or to configure it as a backup server in a Backend Pool. Another neat option is the possibility to adjust a server's weight relative to other servers in the same Backend Pool.") }}</p>
            <p>{{ lang._('Note that it is possible to directly add options to the HAProxy configuration by using the "option pass-through", a setting that is available for several configuration items. It allows you to implement configurations that are currently not officially supported by this plugin. It is strongly discouraged to rely on this feature. Please report missing features on our GitHub page!') | format('<b>', '</b>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-virtual-services-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>Virtual Services</h1>
            <p>{{ lang._("HAProxy requires two virtual services for its load balancing and proxying features. The following virtual services must be configured for everything that should be served by HAProxy:") }}</p>
            <ul>
              <li>{{ lang._('%sBackend Pools:%s The HAProxy backend. Group the %spreviously added servers%s to build a server farm. All servers in a group usually deliver the same content. The Backend Pool cares for health monitoring and load distribution. A Backend Pool must also be configured if you only have a single server. The same Backend Pool may be used for multiple Public Services.') | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._('%sPublic Services:%s The HAProxy frontend. The Public Service listens for client connections, optionally applies rules and forwards client request data to the selected Backend Pool for load balancing or proxying. Every Public Service needs to be connected to a %spreviously created Backend Pool%s.') | format('<b>', '</b>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._('Remember to add firewall rules for all configured Public Services.') }}</p>
            <p>{{ lang._('Note that it is possible to directly add options to the HAProxy configuration by using the "option pass-through", a setting that is available for several configuration items. It allows you to implement configurations that are currently not officially supported by this plugin. It is strongly discouraged to rely on this feature. Please report missing features on our GitHub page!') | format('<b>', '</b>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-rules-checks-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>Rules &amp; Checks</h1>
            <p>{{ lang._("After getting acquainted with HAProxy the following optional features may prove useful:") }}</p>
            <ul>
              <li>{{ lang._('%sHealth Monitors:%s The HAProxy "health checks". Health Monitors are used by %sBackend Pools%s to determine if a server is still able to respond to client requests. If a server fails a health check it will automatically be removed from a Backend Pool and healthy servers are automatically re-added.') | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._('%sConditions:%s HAProxy is capable of extracting data from requests, responses and other connection data and match it against predefined patterns. Use these powerful patterns to compose a condition that may be used in multiple Rules.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('%sRules:%s Perform a large set of actions if one or more %sConditions%s match. These Rules may be used in %sBackend Pools%s as well as %sPublic Services%s.') | format('<b>', '</b>', '<b>', '</b>', '<b>', '</b>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("For more information on HAProxy's %sACL feature%s see the %sofficial documentation%s.") | format('<b>', '</b>', '<a href="http://cbonte.github.io/haproxy-dconv/1.7/configuration.html#7" target="_blank">', '</a>') }}</p>
            <p>{{ lang._('Note that it is possible to directly add options to the HAProxy configuration by using the "option pass-through", a setting that is available for several configuration items. It allows you to implement configurations that are currently not officially supported by this plugin. It is strongly discouraged to rely on this feature. Please report missing features on our GitHub page!') | format('<b>', '</b>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-advanced-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>Advanced Features</h1>
            <p>{{ lang._("Most of the time these features are not required, but in certain situations they will be handy:") }}</p>
            <ul>
              <li>{{ lang._("%sError Messages:%s Return a custom message instead of errors generated by HAProxy. Useful to overwrite HAProxy's internal error messages. The message must represent the full HTTP response and include required HTTP headers.") | format('<b>', '</b>') }}</li>
              <li>{{ lang._("%sLua scripts:%s Include your own Lua code/scripts to extend HAProxy's functionality. The Lua code can be used in certain %sRules%s, for example.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("For more details visit HAProxy's official documentation regarding the %sError Messages%s and the %sLua Script%s features.") | format('<a href="http://cbonte.github.io/haproxy-dconv/1.7/configuration.html#4-errorfile" target="_blank">', '</a>', '<a href="http://cbonte.github.io/haproxy-dconv/1.7/configuration.html#lua-load" target="_blank">', '</a>') }}</p>
            <br/>
        </div>
    </div>

    {# add automatically generated tabs #}
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
                <th data-column-id="frontendid" data-type="number"  data-visible="false">{{ lang._('Public Service ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Public Service Name') }}</th>
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
                <th data-column-id="backendid" data-type="number"  data-visible="false">{{ lang._('Backend Pool ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Backend Pool Name') }}</th>
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
                <th data-column-id="serverid" data-type="number"  data-visible="false">{{ lang._('Server ID') }}</th>
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
                <th data-column-id="healthcheckid" data-type="number"  data-visible="false">{{ lang._('Health Monitor ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Health Monitor Name') }}</th>
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
                <th data-column-id="actionid" data-type="number"  data-visible="false">{{ lang._('Rule ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Rule Name') }}</th>
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
                <th data-column-id="aclid" data-type="number"  data-visible="false">{{ lang._('Condition ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Condition Name') }}</th>
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
                <th data-column-id="luaid" data-type="number"  data-visible="false">{{ lang._('Lua Script ID') }}</th>
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
                <th data-column-id="errorfileid" data-type="number"  data-visible="false">{{ lang._('Error Message ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Error Message Name') }}</th>
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
{{ partial("layout_partials/base_dialog",['fields':formDialogFrontend,'id':'DialogFrontend','label':lang._('Edit Public Service')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogBackend,'id':'DialogBackend','label':lang._('Edit Backend Pool')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogServer,'id':'DialogServer','label':lang._('Edit Server')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHealthcheck,'id':'DialogHealthcheck','label':lang._('Edit Health Monitor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAction,'id':'DialogAction','label':lang._('Edit Rule')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAcl,'id':'DialogAcl','label':lang._('Edit Condition')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogLua,'id':'DialogLua','label':lang._('Edit Lua Script')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogErrorfile,'id':'DialogErrorfile','label':lang._('Edit Error Message')])}}
