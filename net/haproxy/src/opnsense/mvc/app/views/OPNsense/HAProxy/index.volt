{#

Copyright (C) 2016-2021 Frank Wall
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

<script>

    $( document ).ready(function() {

        // get general HAProxy settings
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
                toggle:'/api/haproxy/settings/toggleServer/',
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

        $("#grid-users").UIBootgrid(
            {   search:'/api/haproxy/settings/searchUsers',
                get:'/api/haproxy/settings/getUser/',
                set:'/api/haproxy/settings/setUser/',
                add:'/api/haproxy/settings/addUser/',
                del:'/api/haproxy/settings/delUser/',
                toggle:'/api/haproxy/settings/toggleUser/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-groups").UIBootgrid(
            {   search:'/api/haproxy/settings/searchGroups',
                get:'/api/haproxy/settings/getGroup/',
                set:'/api/haproxy/settings/setGroup/',
                add:'/api/haproxy/settings/addGroup/',
                del:'/api/haproxy/settings/delGroup/',
                toggle:'/api/haproxy/settings/toggleGroup/',
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

        $("#grid-fcgis").UIBootgrid(
            {   search:'/api/haproxy/settings/searchFcgis',
                get:'/api/haproxy/settings/getFcgi/',
                set:'/api/haproxy/settings/setFcgi/',
                add:'/api/haproxy/settings/addFcgi/',
                del:'/api/haproxy/settings/delFcgi/',
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

        $("#grid-mapfiles").UIBootgrid(
            {   search:'/api/haproxy/settings/searchMapfiles',
                get:'/api/haproxy/settings/getMapfile/',
                set:'/api/haproxy/settings/setMapfile/',
                add:'/api/haproxy/settings/addMapfile/',
                del:'/api/haproxy/settings/delMapfile/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-cpus").UIBootgrid(
            {   search:'/api/haproxy/settings/searchCpus',
                get:'/api/haproxy/settings/getCpu/',
                set:'/api/haproxy/settings/setCpu/',
                add:'/api/haproxy/settings/addCpu/',
                del:'/api/haproxy/settings/delCpu/',
                toggle:'/api/haproxy/settings/toggleCpu/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-resolvers").UIBootgrid(
            {   search:'/api/haproxy/settings/searchResolvers',
                get:'/api/haproxy/settings/getResolver/',
                set:'/api/haproxy/settings/setResolver/',
                add:'/api/haproxy/settings/addResolver/',
                del:'/api/haproxy/settings/delResolver/',
                toggle:'/api/haproxy/settings/toggleResolver/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        $("#grid-mailers").UIBootgrid(
            {   search:'/api/haproxy/settings/searchMailers',
                get:'/api/haproxy/settings/getMailer/',
                set:'/api/haproxy/settings/setMailer/',
                add:'/api/haproxy/settings/addMailer/',
                del:'/api/haproxy/settings/delMailer/',
                toggle:'/api/haproxy/settings/toggleMailer/',
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
                $("."+service_id).show();
            });
            $("#acl\\.expression").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogAction').on('shown.bs.modal', function (e) {
            $("#action\\.type").change(function(){
                var service_id = 'table_' + $(this).val();
                $(".type_table").hide();
                $("."+service_id).show();
            });
            $("#action\\.type").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogBackend').on('shown.bs.modal', function (e) {
            $("#backend\\.mode").change(function(){
                var service_id = 'table_' + $(this).val();
                $(".mode_table").hide();
                $("."+service_id).show();
            });
            $("#backend\\.mode").change();

            $("#backend\\.healthCheckEnabled").change(function(){
                var service_id = 'table_healthcheck_' + $(this).is(':checked');
                $(".healthcheck_table").hide();
                $("."+service_id).show();
            });
            $("#backend\\.healthCheckEnabled").change();

            $("#backend\\.persistence").change(function(){
                var persistence_id = 'table_persistence_' + $(this).val();
                $(".persistence_table").hide();
                $("."+persistence_id).show();
            });
            $("#backend\\.persistence").change();
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
                $("."+service_id).show();
            });
            $("#healthcheck\\.type").change();
        })

        // hook into on-show event for dialog to extend layout.
        $('#DialogServer').on('shown.bs.modal', function (e) {
            $("#server\\.type").change(function(){
                var service_id = 'table_server_type_' + $(this).val();
                $(".table_server_type").hide();
                $("."+service_id).show();
            });
            $("#server\\.type").change();
        })

        /***********************************************************************
         * Commands
         **********************************************************************/

        // reconfigure haproxy to activate changes
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
                            title: "{{ lang._('HAProxy configtest found critical errors') }}",
                            message: "{{ lang._('The HAProxy service may not be able to start due to critical errors. Run syntax check for further details or review the changes in the %sConfiguration Diff%s.')|format('<a href=\"/ui/haproxy/export#diff\">','</a>') }}",
                            buttons: [{
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
                            } else {
                                // reload page to hide pending changes reminder
                                setTimeout(function () {
                                    window.location.reload(true)
                                }, 300);
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

        // test configuration file
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
                            title: "{{ lang._('HAProxy configtest found critical errors') }}",
                            message: data['result'],
                            draggable: true
                        });
                    } else if (data['result'].indexOf('WARNING') > -1) {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: "{{ lang._('HAProxy configtest found minor errors') }}",
                            message: data['result'],
                            draggable: true
                        });
                    } else {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: "{{ lang._('HAProxy configtest result') }}",
                            message: "{{ lang._('Your HAProxy config contains no errors.') }}",
                            draggable: true
                        });
                    }
                });
            });
        });

        // save general settings and perform a config test
        $('[id*="saveAndTestAct"]').each(function(){
            $(this).click(function(){
                // extract the form id from the button id
                var frm_id = "frm_" + $(this).attr("id").split('_')[1]

                // save data for this tab
                saveFormToEndpoint(url="/api/haproxy/settings/set",formid=frm_id,callback_ok=function(){
                    // set progress animation
                    $('[id*="saveAndTestAct_progress"]').each(function(){
                        $(this).addClass("fa fa-spinner fa-pulse");
                    });

                    // on correct save, perform config test
                    ajaxCall(url="/api/haproxy/service/configtest", sendData={}, callback=function(data,status) {
                        if (data['result'].indexOf('ALERT') > -1) {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_DANGER,
                                title: "{{ lang._('HAProxy configtest found critical errors') }}",
                                message: data['result'],
                                draggable: true
                            });
                        } else if (data['result'].indexOf('WARNING') > -1) {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_WARNING,
                                title: "{{ lang._('HAProxy configtest found minor errors') }}",
                                message: data['result'],
                                draggable: true
                            });
                        } else {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_WARNING,
                                title: "{{ lang._('HAProxy configtest result') }}",
                                message: "{{ lang._('Your HAProxy config contains no errors.') }}",
                                draggable: true
                            });
                        }

                        // when done, disable progress animation
                        $('[id*="saveAndTestAct_progress"]').each(function(){
                            $(this).removeClass("fa fa-spinner fa-pulse");
                        });
                    });
                });
            });
        });

        // save general settings and reconfigure HAProxy
        $('[id*="saveAndReconfigureAct"]').each(function(){
            $(this).click(function(){
                // extract the form id from the button id
                var frm_id = "frm_" + $(this).attr("id").split('_')[1]

                // save data for this tab
                saveFormToEndpoint(url="/api/haproxy/settings/set",formid=frm_id,callback_ok=function(){
                    // set progress animation
                    $('[id*="saveAndReconfigureAct_progress"]').each(function(){
                        $(this).addClass("fa fa-spinner fa-pulse");
                    });

                    // on correct save, perform config test
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
                                        $('[id*="saveAndReconfigureAct_progress"]').each(function(){
                                            $(this).removeClass("fa fa-spinner fa-pulse");
                                        });
                                        dlg.close();
                                    }
                                }, {
                                    icon: 'fa fa-trash-o',
                                    label: '{{ lang._('Abort') }}',
                                    action: function(dlg){
                                        // when done, disable progress animation
                                        $('[id*="saveAndReconfigureAct_progress"]').each(function(){
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
                                } else {
                                    // reload page to hide pending changes reminder
                                    setTimeout(function () {
                                        window.location.reload(true)
                                    }, 300);
                                }
                                // when done, disable progress animation
                                $('[id*="saveAndReconfigureAct_progress"]').each(function(){
                                    $(this).removeClass("fa fa-spinner fa-pulse");
                                });
                            });
                        }
                    });

                });
            });
        });

        /***********************************************************************
         * UI tricks
         **********************************************************************/

        // show reminder when config has pending changes
        function pending_changes_reminder() {
            ajaxCall(url="/api/haproxy/export/diff/", sendData={}, callback=function(data,status) {
                if (data['response'] && data['response'].trim()) {
                    $("#haproxyPendingReminder").show();
                } else {
                    $("#haproxyPendingReminder").hide();
                }
            });
        }
        pending_changes_reminder();

        // show hint after every config change
        function add_apply_reminder() {
            hint_msg = "{{ lang._('After changing settings, please remember to test and apply them with the buttons below.') }}"
            $('[id*="haproxyChangeMessage"]').each(function(){
                $(this).append(hint_msg);
            });

        };
        add_apply_reminder();

        // show or hide the correct buttons depending on which tab is shown
        // NOTE: This does not work on already shown tabs, so this event must
        // fire first.
        $('.nav-tabs a').on('show.bs.tab', function (e) {
            if (/^\#general/.test(e.target.hash)) {
              $("#haproxyCommonButtons").hide();
            } else {
              $("#haproxyCommonButtons").show();
            }
        });

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

    });

</script>

<style>
    textarea {
        white-space: pre;
    }
</style>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    {# manually add tabs #}
    <li class="active"><a data-toggle="tab" href="#introduction"><b>{{ lang._('Introduction') }}</b></a></li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#{% if showIntro|default('0')=='1' %}real-servers-introduction{% else %}servers-tab{% endif %}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Real Servers') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            {% if showIntro|default('0')=='1' %}
            <li><a data-toggle="tab" id="real-servers-introduction" href="#subtab_haproxy-real-servers-introduction">{{ lang._('Introduction') }}</a></li>
            {% endif %}
            <li><a data-toggle="tab" id="servers-tab" href="#servers">{{ lang._('Real Servers') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#{% if showIntro|default('0')=='1' %}virtual-services-introduction{% else %}backends-tab{% endif %}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Virtual Services') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            {% if showIntro|default('0')=='1' %}
            <li><a data-toggle="tab" id="virtual-services-introduction" href="#subtab_haproxy-virtual-services-introduction">{{ lang._('Introduction') }}</a></li>
            {% endif %}
            <li><a data-toggle="tab" id="backends-tab" href="#backends">{{ lang._('Backend Pools') }}</a></li>
            <li><a data-toggle="tab" href="#frontends">{{ lang._('Public Services') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#{% if showIntro|default('0')=='1' %}rules-checks-introduction{% else %}healthchecks-tab{% endif %}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Rules & Checks') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            {% if showIntro|default('0')=='1' %}
            <li><a data-toggle="tab" id="rules-checks-introduction" href="#subtab_haproxy-rules-checks-introduction">{{ lang._('Introduction') }}</a></li>
            {% endif %}
            <li><a data-toggle="tab" id="healthchecks-tab" href="#healthchecks">{{ lang._('Health Monitors') }}</a></li>
            <li><a data-toggle="tab" href="#acls">{{ lang._('Conditions') }}</a></li>
            <li><a data-toggle="tab" href="#actions">{{ lang._('Rules') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#{% if showIntro|default('0')=='1' %}user-management-introduction{% else %}users-tab{% endif %}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('User Management') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            {% if showIntro|default('0')=='1' %}
            <li><a data-toggle="tab" id="user-management-introduction" href="#subtab_haproxy-user-management-introduction">{{ lang._('Introduction') }}</a></li>
            {% endif %}
            <li><a data-toggle="tab" id="users-tab" href="#users">{{ lang._('Users') }}</a></li>
            <li><a data-toggle="tab" href="#groups">{{ lang._('Groups') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#{% if showIntro|default('0')=='1' %}general-introduction{% else %}settings-tab{% endif %}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Settings') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            {% if showIntro|default('0')=='1' %}
            <li><a data-toggle="tab" id="general-introduction" href="#subtab_haproxy-general-introduction">{{ lang._('Introduction') }}</a></li>
            {% endif %}
            <li><a data-toggle="tab" id="general-settings-tab" href="#general-settings">{{ lang._('Service') }}</a></li>
            <li><a data-toggle="tab" href="#general-tuning">{{ lang._('Global Parameters') }}</a></li>
            <li><a data-toggle="tab" href="#general-defaults">{{ lang._('Default Parameters') }}</a></li>
            <li><a data-toggle="tab" href="#general-logging">{{ lang._('Logging') }}</a></li>
            <li><a data-toggle="tab" href="#general-stats">{{ lang._('Statistics') }}</a></li>
            <li><a data-toggle="tab" href="#general-cache">{{ lang._('Cache') }}</a></li>
            <li><a data-toggle="tab" href="#general-peers">{{ lang._('Peers') }}</a></li>
        </ul>
    </li>

    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#{% if showIntro|default('0')=='1' %}advanced-introduction{% else %}errorfiles-tab{% endif %}').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Advanced') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            {% if showIntro|default('0')=='1' %}
            <li><a data-toggle="tab" id="advanced-introduction" href="#subtab_haproxy-advanced-introduction">{{ lang._('Introduction') }}</a></li>
            {% endif %}
            <li><a data-toggle="tab" id="errorfiles-tab" href="#errorfiles">{{ lang._('Error Messages') }}</a></li>
            <li><a data-toggle="tab" href="#fcgis">{{ lang._('FastCGI Applications') }}</a></li>
            <li><a data-toggle="tab" href="#luas">{{ lang._('Lua Scripts') }}</a></li>
            <li><a data-toggle="tab" href="#mapfiles">{{ lang._('Map Files') }}</a></li>
            <li><a data-toggle="tab" href="#cpus">{{ lang._('CPU Affinity Rules') }}</a></li>
            <li><a data-toggle="tab" href="#resolvers">{{ lang._('Resolvers') }}</a></li>
            <li><a data-toggle="tab" href="#mailers">{{ lang._('E-Mail Alerts') }}</a></li>
        </ul>
    </li>
</ul>

<div class="content-box tab-content">
    <div id="introduction" class="tab-pane fade in active">
        <div class="col-md-12">
            <h1>{{ lang._('Quick Start Guide') }}</h1>
            <p>{{ lang._('Welcome to the HAProxy plugin! This plugin is designed to offer all the features and flexibility HAProxy is famous for. If you are using HAProxy for the first time, please take some time to get familiar with it. The following information should help you to get started.')}}</p>
            <p>{{ lang._('Note that you should configure HAProxy in the following order:') }}</p>
            <ul>
              <li>{{ lang._('Add %sReal Servers:%s All physical or virtual servers that HAProxy should use to load balance between or proxy to.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('Add %sBackend Pools:%s Group the previously added servers to build a server farm. All servers in a group usually deliver the same content. The Backend Pool takes care of health monitoring and load distribution. A Backend Pool must be configured even if you only have a single server.') | format('<b>', '</b>')}}</li>
              <li>{{ lang._('Add %sPublic Services:%s The Public Service listens for client connections, optionally applies rules and forwards client request data to the selected Backend Pool for load balancing or proxying.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('Lastly, enable HAProxy using the %sService%s settings page.') | format('<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._('Please be aware that you need to %smanually%s add the required firewall rules for all configured services.') | format('<b>', '</b>') }}</p>
            <p>{{ lang._('Further information is available in the %sofficial HAProxy documentation%s. Be sure to report bugs and request features on our %sGitHub issue page%s. Code contributions are also very welcome!') | format('<a href="http://docs.haproxy.org/2.8/configuration.html" target="_blank">', '</a>', '<a href="https://github.com/opnsense/plugins/issues/" target="_blank">', '</a>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-real-servers-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>{{ lang._('Real Servers') }}</h1>
            <p>{{ lang._('HAProxy needs to know which servers should be used to serve content. Either add a static server configuration or use a template to initialize multiple servers at once. The latter one can also be used to discover the available services via DNS SRV records. The following minimum information must be provided for each server:') }}</p>
            <ul>
              <li>{{ lang._('%sStatic Servers:%s The IP address or fully-qualified domain name that should be used when communicating with your server. Additionally the TCP or UDP port that should be used. If unset, the same port the client connected to will be used.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('%sServer Templates:%s A prefix is required to build the server names. Additionally a service name or FQDN is required to identify the servers this template initializes') | format('<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("Please note that advanced mode settings allow you to adjust a server's weight relative to other servers in the same Backend Pool, in addition to fine-grained health check options.") }}</p>
            <p>{{ lang._('Note that it is possible to directly add options to the HAProxy configuration by using the "option pass-through", a setting that is available for several configuration items. It allows you to implement configurations that are currently not officially supported by this plugin. It is strongly discouraged to rely on this feature. Please report missing features on our GitHub page!') | format('<b>', '</b>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-virtual-services-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>{{ lang._('Virtual Services') }}</h1>
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
            <h1>{{ lang._('Rules and Checks') }}</h1>
            <p>{{ lang._("After getting acquainted with HAProxy the following optional features may prove useful:") }}</p>
            <ul>
              <li>{{ lang._('%sHealth Monitors:%s The HAProxy "health checks". Health Monitors are used by %sBackend Pools%s to determine if a server is still able to respond to client requests. If a server fails a health check it will automatically be removed from a Backend Pool and healthy servers are automatically re-added.') | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._('%sConditions:%s HAProxy is capable of extracting data from requests, responses and other connection data and match it against predefined patterns. Use these powerful patterns to compose a condition that may be used in multiple Rules.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('%sRules:%s Perform a large set of actions if one or more %sConditions%s match. These Rules may be used in %sBackend Pools%s as well as %sPublic Services%s.') | format('<b>', '</b>', '<b>', '</b>', '<b>', '</b>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("For more information on HAProxy's %sACL feature%s see the %sofficial documentation%s.") | format('<b>', '</b>', '<a href="http://docs.haproxy.org/2.8/configuration.html#7" target="_blank">', '</a>') }}</p>
            <p>{{ lang._('Note that it is possible to directly add options to the HAProxy configuration by using the "option pass-through", a setting that is available for several configuration items. It allows you to implement configurations that are currently not officially supported by this plugin. It is strongly discouraged to rely on this feature. Please report missing features on our GitHub page!') | format('<b>', '</b>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-user-management-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>{{ lang._('User Management') }}</h1>
            <p>{{ lang._("Optionally HAProxy manages an internal list of users and groups, which can be used for HTTP Basic Authentication as well as access to HAProxy's internal statistic pages.") }}</p>
            <ul>
              <li>{{ lang._('%sUser:%s A username/password combination. Both secure (encrypted) and insecure (unencrypted) passwords can be used.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('%sGroup:%s A optional list containing one or more users. Groups usually make it easier to manage permissions for a large number of users') | format('<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._('Note that users and groups must be selected from the Backend Pool or Public Service configuration in order to be used for authentication. In addition to this users and groups may also be used in Rules/Conditions.') }}</p>
            <p>{{ lang._("For more information on HAProxy's %suser/group management%s see the %sofficial documentation%s.") | format('<b>', '</b>', '<a href="http://docs.haproxy.org/2.8/configuration.html#3.4" target="_blank">', '</a>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-general-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>{{ lang._('Settings') }}</h1>
            <p>{{ lang._("Manage HAProxy core configuration:") }}</p>
            <ul>
              <li>{{ lang._("%sService:%s Basic service management and options to control HAProxy's restart behaviour.") | format('<b>', '</b>') }}</li>
              <li>{{ lang._("%sGlobal Parameters:%s Tuning parameters and global defaults that cannot be overriden elsewhere.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sDefault Parameters:%s Define default parameters for all %sPublic Services%s, %sBackend Pools%s and %sReal Servers%s here. They may be overriden elsewhere.") | format('<b>', '</b>', '<b>', '</b>', '<b>', '</b>', '<b>', '</b>', '<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sLogging:%s Configure HAProxy's logging behaviour and enable remote logging.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sStatistics:%s This manages HAProxy's internal statistics reporting.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sCache:%s HAProxy's cache which was designed to perform cache on small objects (favicon, css, etc.). This is a minimalist low-maintenance cache which runs in RAM.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sPeers:%s Configure a communication channel between two HAProxy instances. This will propagate entries of any data-types in stick-tables between these HAProxy instances over TCP connections in a multi-master fashion. Useful when aiming for a seamless failover in a HA setup.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("For more details visit HAProxy's official documentation regarding the %sStatistics%s, %sCache%s and %sPeers%s features.") | format('<a href="http://docs.haproxy.org/2.8/configuration.html#stats%20enable" target="_blank">', '</a>', '<a href="http://docs.haproxy.org/2.8/configuration.html#10" target="_blank">', '</a>', '<a href="http://docs.haproxy.org/2.8/configuration.html#3.5" target="_blank">', '</a>') }}</p>
            <br/>
        </div>
    </div>

    <div id="subtab_haproxy-advanced-introduction" class="tab-pane fade">
        <div class="col-md-12">
            <h1>{{ lang._('Advanced Features') }}</h1>
            <p>{{ lang._("Most of the time these features are not required, but in certain situations they will be handy:") }}</p>
            <ul>
              <li>{{ lang._("%sError Messages:%s Return a custom message instead of errors generated by HAProxy. Useful to overwrite HAProxy's internal error messages. The message must represent the full HTTP response and include required HTTP headers.") | format('<b>', '</b>') }}</li>
              <li>{{ lang._("%sFastCGI Applications:%s HAProxy can be configured to send requests to FastCGI applications. After configuring a FastCGI application, it needs to be enabled in a %sBackend Pool%s.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sLua scripts:%s Include your own Lua code/scripts to extend HAProxy's functionality. The Lua code can be used in certain %sRules%s, for example.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sMap Files:%s A map allows to map a data in input to an other one on output. For example, this makes it possible to map a large number of domains to backend pools without using the GUI. Map files need to be used in %sRules%s, otherwise they are ignored.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sCPU Affinity Rules:%s This feature makes it possible to bind HAProxy's processes/threads to a specific CPU (or a CPU set). Furthermore it is possible to select CPU Affinity Rules in %sPublic Services%s to restrict them to a certain set of processes/threads/CPUs.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sResolvers:%s This feature allows in-depth configuration of how HAProxy handles name resolution and interacts with name resolvers (DNS). Each resolver configuration can be used in %sBackend Pools%s to apply individual name resolution configurations.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
              <li>{{ lang._("%sE-Mail Alerts:%s It is possible to send email alerts when the state of servers changes. Each configuration can be used in %sBackend Pools%s to send e-mail alerts to the configured recipient.") | format('<b>', '</b>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._("For more details visit HAProxy's official documentation regarding the %sError Messages%s, %sLua Script%s and the %sMap Files%s features. More information on HAProxy's CPU Affinity is also available %shere%s, %shere%s and %shere%s. A detailed explanation of the resolvers feature can be found %shere%s.") | format('<a href="http://docs.haproxy.org/2.8/configuration.html#4-errorfile" target="_blank">', '</a>', '<a href="http://docs.haproxy.org/2.8/configuration.html#lua-load" target="_blank">', '</a>', '<a href="http://docs.haproxy.org/2.8/configuration.html#map" target="_blank">', '</a>' ,'<a href="http://docs.haproxy.org/2.8/configuration.html#cpu-map" target="_blank">', '</a>' ,'<a href="http://docs.haproxy.org/2.8/configuration.html#bind-process" target="_blank">', '</a>' ,'<a href="http://docs.haproxy.org/2.8/configuration.html#process" target="_blank">', '</a>','<a href="http://docs.haproxy.org/2.8/configuration.html#5.3.2" target="_blank">', '</a>') }}</p>
            <br/>
        </div>
    </div>

    <div id="frontends" class="tab-pane fade">
        <table id="grid-frontends" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogFrontend" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="backends" class="tab-pane fade">
        <table id="grid-backends" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogBackend" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="servers" class="tab-pane fade">
        <table id="grid-servers" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogServer" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="serverid" data-type="number"  data-visible="false">{{ lang._('Server ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Server Name') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
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
    </div>

    <div id="healthchecks" class="tab-pane fade">
        <table id="grid-healthchecks" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogHealthcheck" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="actions" class="tab-pane fade">
        <table id="grid-actions" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAction" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="acls" class="tab-pane fade">
        <table id="grid-acls" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAcl" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="users" class="tab-pane fade">
        <table id="grid-users" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogUser" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="userid" data-type="number"  data-visible="false">{{ lang._('User ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Username') }}</th>
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
    </div>

    <div id="groups" class="tab-pane fade">
        <table id="grid-groups" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogGroup" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="groupid" data-type="number"  data-visible="false">{{ lang._('Group ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Group') }}</th>
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
    </div>

    <div id="luas" class="tab-pane fade">
        <table id="grid-luas" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogLua" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="fcgis" class="tab-pane fade">
        <table id="grid-fcgis" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogFcgi" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="fcgiid" data-type="number"  data-visible="false">{{ lang._('FastCGI ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('FastCGI Application Name') }}</th>
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
    </div>

    <div id="errorfiles" class="tab-pane fade">
        <table id="grid-errorfiles" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogErrorfile" data-editAlert="haproxyChangeMessage">
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
    </div>

    <div id="mapfiles" class="tab-pane fade">
        <table id="grid-mapfiles" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogMapfile" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="mapfileid" data-type="number"  data-visible="false">{{ lang._('Map File ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Map File Name') }}</th>
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
    </div>

    <div id="cpus" class="tab-pane fade">
        <table id="grid-cpus" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCpu" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="cpuid" data-type="number"  data-visible="false">{{ lang._('CPU Rule ID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="thread_id" data-type="string">{{ lang._('Thread ID') }}</th>
                <th data-column-id="cpu_id" data-type="string">{{ lang._('CPU ID') }}</th>
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
    </div>

    <div id="resolvers" class="tab-pane fade">
        <table id="grid-resolvers" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogResolver" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="resolverid" data-type="number"  data-visible="false">{{ lang._('Resolver ID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="nameservers" data-type="string">{{ lang._('Nameservers') }}</th>
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
    </div>

    <div id="mailers" class="tab-pane fade">
        <table id="grid-mailers" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogMailer" data-editAlert="haproxyChangeMessage">
            <thead>
            <tr>
                <th data-column-id="mailerid" data-type="number"  data-visible="false">{{ lang._('Mailer ID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="Sender" data-type="string">{{ lang._('Sender') }}</th>
                <th data-column-id="Recipient" data-type="string">{{ lang._('Recipient') }}</th>
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
    </div>

    <!-- subtabs for general "Settings" tab below -->
    <div id="general-settings" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalSettingsForm,'id':'frm_haproxy-general-settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-settings" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct_haproxy-general-settings" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <div id="general-tuning" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalTuningForm,'id':'frm_haproxy-general-tuning'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-tuning" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct_haproxy-general-tuning" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <div id="general-defaults" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalDefaultsForm,'id':'frm_haproxy-general-defaults'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-defaults" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct_haproxy-general-defaults" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <div id="general-logging" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalLoggingForm,'id':'frm_haproxy-general-logging'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-logging" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct_haproxy-general-logging" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <div id="general-stats" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalStatsForm,'id':'frm_haproxy-general-stats'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-stats" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct_haproxy-general-stats" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <div id="general-cache" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalCacheForm,'id':'frm_haproxy-general-cache'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-cache" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct-haproxy-general-cache" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <div id="general-peers" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalPeersForm,'id':'frm_haproxy-general-peers'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_haproxy-general-peers" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
                <button class="btn btn-primary" id="saveAndTestAct_haproxy-general-peers" type="button"><b>{{ lang._('Save & Test syntax') }}</b><i id="saveAndTestAct_progress" class=""></i></button>
            </div>
        </div>
    </div>

    <!-- buttons for all grid pages -->
    <div class="col-md-12" id="haproxyCommonButtons" style="display: none">
        <div id="haproxyPendingReminder" class="alert alert-warning" style="display: none" role="alert">
            {{ lang._("There are pending configuration changes that must be applied in order for them to take effect. To review them visit the %sConfig Diff%s page.") | format('<a href="/ui/haproxy/export#diff" class="alert-link" target="_blank">', '</a>') }}
        </div>
        <div id="haproxyChangeMessage" class="alert alert-info" style="display: none" role="alert">
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct-common" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
        <button class="btn btn-primary" id="configtestAct-common" type="button"><b>{{ lang._('Test syntax') }}</b><i id="configtestAct_progress" class=""></i></button>
        <br/><br/>
    </div>

</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogFrontend,'id':'DialogFrontend','label':lang._('Edit Public Service')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogBackend,'id':'DialogBackend','label':lang._('Edit Backend Pool')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogServer,'id':'DialogServer','label':lang._('Edit Server')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHealthcheck,'id':'DialogHealthcheck','label':lang._('Edit Health Monitor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAction,'id':'DialogAction','label':lang._('Edit Rule')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAcl,'id':'DialogAcl','label':lang._('Edit Condition')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogUser,'id':'DialogUser','label':lang._('Edit User')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogGroup,'id':'DialogGroup','label':lang._('Edit Group')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogLua,'id':'DialogLua','label':lang._('Edit Lua Script')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogErrorfile,'id':'DialogErrorfile','label':lang._('Edit Error Message')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogFcgi,'id':'DialogFcgi','label':lang._('Edit FastCGI Application')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogMapfile,'id':'DialogMapfile','label':lang._('Edit Map File')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogCpu,'id':'DialogCpu','label':lang._('Edit CPU Affinity Rule')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogResolver,'id':'DialogResolver','label':lang._('Edit Resolver')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogMailer,'id':'DialogMailer','label':lang._('Edit E-Mail Alert')])}}
