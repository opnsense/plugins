{#

Copyright (C) 2017-2019 Frank Wall
OPNsense® is Copyright © 2014-2015 by Deciso B.V.
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

        // request service status on load and update status box
        ajaxCall(url="/api/acmeclient/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });

        var data_get_map = {'frm_settings':"/api/acmeclient/settings/get"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(data){
                // set schedule updates link to cron
                $.each(data.frm_settings.acmeclient.settings.UpdateCron, function(key, value) {
                    if (value.selected == 1) {
                        $("#scheduled_updates").attr("href","/ui/cron/item/open/"+key);
                        $("#scheduled_updates").show();
                    }
                });
                formatTokenizersUI();
                $('.selectpicker').selectpicker('refresh');

        });

        // Save & reconfigure acme-client to activate changes
        $("#reconfigureAct").click(function(){
            // TODO: reload the page afterwards to show/hide the "Schedule" tab

            // set progress animation
            $('[id*="reconfigureAct_progress"]').each(function(){
                $(this).addClass("fa fa-spinner fa-pulse");
            });

            // save configuration
            saveFormToEndpoint(url="/api/acmeclient/settings/set",formid='frm_settings',callback_ok=function(){
            });

            // first run syntax check to catch critical errors
            ajaxCall(url="/api/acmeclient/service/configtest", sendData={}, callback=function(data,status) {
                // show warning in case of critical errors
                if (data['result'].indexOf('ALERT') > -1) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_DANGER,
                        title: "{{ lang._('acme-client config contains critical errors') }}",
                        message: "{{ lang._('The acme-client service may not be able to start due to critical errors. Try anyway?') }}",
                        buttons: [{
                            label: '{{ lang._('Continue') }}',
                            cssClass: 'btn-primary',
                            action: function(dlg){
                                ajaxCall(url="/api/acmeclient/service/reconfigure", sendData={}, callback=function(data,status) {
                                    if (status != "success" || data['status'] != 'ok') {
                                        BootstrapDialog.show({
                                            type: BootstrapDialog.TYPE_WARNING,
                                            title: "{{ lang._('Error reconfiguring acme-client') }}",
                                            message: data['status'],
                                            draggable: true
                                        });
                                    }
                                });

                                // Handle cron integration
                                ajaxCall(url="/api/acmeclient/settings/fetchCronIntegration", sendData={}, callback=function(data,status) {
                                });

                                // Handle HAProxy integration
                                ajaxCall(url="/api/acmeclient/settings/fetchHAProxyIntegration", sendData={}, callback=function(data,status) {
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
                    ajaxCall(url="/api/acmeclient/service/reconfigure", sendData={}, callback=function(data,status) {
                        if (status != "success" || data['status'] != 'ok') {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_WARNING,
                                title: "{{ lang._('Error reconfiguring acme-client') }}",
                                message: data['status'],
                                draggable: true
                            });
                        }

                        // Handle cron integration
                        ajaxCall(url="/api/acmeclient/settings/fetchCronIntegration", sendData={}, callback=function(data,status) {
                            // Handle HAProxy integration
                            ajaxCall(url="/api/acmeclient/settings/fetchHAProxyIntegration", sendData={}, callback=function(data,status) {
                                // when done, disable progress animation
                                $('[id*="reconfigureAct_progress"]').each(function(){
                                    $(this).removeClass("fa fa-spinner fa-pulse");
                                });
                            });
                        });
                    });
                }
            });
        });

        // Test configuration file
        $("#configtestAct").click(function(){

            // set progress animation
            $('[id*="configtestAct_progress"]').each(function(){
                $(this).addClass("fa fa-spinner fa-pulse");
            });

            // save configuration
            saveFormToEndpoint(url="/api/acmeclient/settings/set",formid='frm_settings',callback_ok=function(){
            });

            // run syntax check to catch critical errors
            ajaxCall(url="/api/acmeclient/service/configtest", sendData={}, callback=function(data,status) {
                // when done, disable progress animation
                $('[id*="configtestAct_progress"]').each(function(){
                    $(this).removeClass("fa fa-spinner fa-pulse");
                });

                if (data['result'].indexOf('ALERT') > -1) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_DANGER,
                        title: "{{ lang._('acme-client config contains critical errors') }}",
                        message: data['result'],
                        draggable: true
                    });
                } else if (data['result'].indexOf('WARNING') > -1) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('acme-client config contains minor errors') }}",
                        message: data['result'],
                        draggable: true
                    });
                } else {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_INFO,
                        title: "{{ lang._('acme-client config test result') }}",
                        message: "{{ lang._('Your acme-client config contains no errors.') }}",
                        draggable: true
                    });
                }
            });
        });

        // Reset certificate data (aka wipe everything)
        $("#resetAct").click(function(){

            // set progress animation
            $('[id*="resetAct_progress"]').each(function(){
                $(this).addClass("fa fa-spinner fa-pulse");
            });

            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DANGER,
                title: "{{ lang._('Wipe all certificate and account data') }}",
                message: "{{ lang._('This will remove ALL certificates, private keys, CSRs from acme client and reset all certificate and account states. However, existing certificates will remain in OPNsense trust storage. The acme client will automatically regenerate everything on its next scheduled run. This is most useful when importing a config backup to a new firewall. Continue?') }}",
                buttons: [{
                    label: '{{ lang._('Continue') }}',
                    cssClass: 'btn-primary',
                    action: function(dlg){
                        ajaxCall(url="/api/acmeclient/service/reset", sendData={}, callback=function(data,status) {
                        });

                        dlg.close();
                    }
                }, {
                    icon: 'fa fa-trash-o',
                    label: '{{ lang._('Abort') }}',
                    action: function(dlg){
                        dlg.close();
                    }
                }]
            });

            // when done, disable progress animation
            $('[id*="resetAct_progress"]').each(function(){
                $(this).removeClass("fa fa-spinner fa-pulse");
            });

        });
    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
    <li><a href="" id="scheduled_updates" style="display:none">{{ lang._('Update Schedule') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in active">
{{ partial("layout_partials/base_form",['fields':settingsForm,'id':'frm_settings'])}}
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
        <button class="btn btn-primary" id="configtestAct" type="button"><b>{{ lang._('Test Config') }}</b><i id="configtestAct_progress" class=""></i></button>
        <button class="btn btn-primary" id="resetAct" type="button"><b>{{ lang._('Reset acme client') }}</b><i id="resetAct_progress" class=""></i></button>
        <br/>
    </div>
    <div class="col-md-12">
        <b>{{ lang._("Please read the official %sLet's Encrypt documentation%s before using this plugin. Otherwise you will easily hit its %srate limits%s and thus all your attempts to issue a certificate will fail.") | format('<a href="https://letsencrypt.org/how-it-works/">', '</a>', '<a href="https://letsencrypt.org/docs/rate-limits/">', '</a>') }}</b>{{ lang._("Please use Let's Encrypt's %sstaging servers%s when using this plugin for the first time or while testing a new validation method. You will have to reissue your certificates when switching from staging to production servers to get valid certificates.") | format('<a href="https://letsencrypt.org/docs/staging-environment/">', '</a>') }}
        <br/>
        {{ lang._('Please use the %sissue tracker%s to report bugs or request new features.') | format('<a href="https://github.com/opnsense/plugins/issues">', '</a>') }}
        <br/>
        <br/>
        <p>{{ lang._('This plugin includes code from the %s project.') | format('<a href="https://github.com/Neilpang/acme.sh">Neilpang/acme.sh</a>' ) }} {{ lang._('Licensed under GPLv3.') }}<br/>{{ lang._('Let"s Encrypt(tm) is a trademark of the Internet Security Research Group. All rights reserved.') }}</p>
        <br/>
    </div>
</div>
