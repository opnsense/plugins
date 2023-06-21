{#

Copyright (C) 2017-2021 Frank Wall
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
                                    // reload page to show or hide links to cron edit page
                                    setTimeout(function () {
                                        window.location.reload(true)
                                    }, 300);
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
                                    // reload page to show or hide links to cron edit page
                                    setTimeout(function () {
                                        window.location.reload(true)
                                    }, 300);
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
                message: "{{ lang._('This will remove ALL certificates, private keys, CSRs from ACME Client and reset all certificate and account states. However, existing certificates will remain in OPNsense trust storage. The ACME Client will automatically regenerate everything on its next scheduled run. This is most useful when importing a config backup to a new firewall. Continue?') }}",
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

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

    });

</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li {% if showIntro|default('0')=='1' %}class="active"{% endif %}><a data-toggle="tab" id="settings-introduction" href="#subtab_settings-introduction"><b>{{ lang._('Introduction') }}</b></a></li>
    <li {% if showIntro|default('0')=='0' %}class="active"{% endif %}><a data-toggle="tab" id="settings-tab" href="#settings"><b>{{ lang._('Settings') }}</b></a></li>
    <li><a href="" id="scheduled_updates" style="display:none">{{ lang._('Update Schedule') }}</a></li>
</ul>

<div class="content-box tab-content">

    <div id="subtab_settings-introduction" class="tab-pane fade {% if showIntro|default('0')=='1' %}in active{% endif %}">
        <div class="col-md-12">
            <h1>{{ lang._('Quick Start Guide') }}</h1>
            <p>{{ lang._("Welcome to the ACME Client plugin! This plugin allows you to create SSL certificates by using one of the following Certificate Authorities (CAs):") }}</p>
            <ul>
              <li>{{ lang._("%sLet's Encrypt:%s A free, automated, and open certificate authority, run for the public's benefit. It is a service provided by the Internet Security Research Group (ISRG). Read more about the ACME protocol in %stheir documentation%s.") | format('<b>', '</b>', '<a href="https://letsencrypt.org/how-it-works/" target="_blank">', '</a>') }}</li>
              <li>{{ lang._('%sBuypass:%s A commercial, european certificate authority, based in Norway. Check out %stheir documentation%s for details about rate-limits and the usage policy.') | format('<b>', '</b>', '<a href="https://www.buypass.com/ssl/resources/go-ssl-technical-specification" target="_blank">', '</a>') }}</li>
              <li>{{ lang._('%sGoogle:%s A commercial certificate authority. More information is available from %stheir documentation%s.') | format('<b>', '</b>', '<a href="https://cloud.google.com/certificate-manager/docs/overview" target="_blank">', '</a>') }}</li>
              <li>{{ lang._('%sSSL.com:%s A commercial, globally trusted certificate authority. They provide an %sextensive guide%s for using their paid services with the ACME protocol.') | format('<b>', '</b>', '<a href="https://www.ssl.com/guide/ssl-tls-certificate-issuance-and-revocation-with-acme/" target="_blank">', '</a>') }}</li>
              <li>{{ lang._("%sZeroSSL:%s A commercial, european certificate authority, based in Austria. They provide a feature overview on %stheir website%s for users of Let's Encrypt.") | format('<b>', '</b>', '<a href="https://zerossl.com/letsencrypt-alternative/" target="_blank">', '</a>') }}</li>
            </ul>
            <p>{{ lang._("Setting up this plugin for the first time involves the following steps") }}</p>
            <ul>
              <li>{{ lang._('%sEnable%s the plugin: When enabling this plugin on the %ssettings%s page, a lightweight service is started and a cron job is added to run periodic tasks.') | format('<b>', '</b>', '<a href="/ui/acmeclient#settings">', '</a>') }}</li>
              <li>{{ lang._('Create an %saccount%s: An %saccount%s is required. It determines which CA will be used for all associated certificates.') | format('<b>', '</b>', '<a href="/ui/acmeclient/accounts">', '</a>') }}</li>
              <li>{{ lang._('Set up a %schallenge type%s: Choose the %schallenge type%s that works best for you and if necessary, add the credentials for your DNS provider.') | format('<b>', '</b>', '<a href="/ui/acmeclient/validations">', '</a>') }}</li>
              <li>{{ lang._('Add %sautomations%s: This is optional, but recommended when using short-lived certificates. %sAutomations%s allow to automatically run tasks when a certificate was created or renewed.') | format('<b>', '</b>', '<a href="/ui/acmeclient/actions">', '</a>') }}</li>
              <li>{{ lang._('Create %scertificates%s: Finally create the %scertificates%s and let the CA complete the validation process.') | format('<b>', '</b>', '<a href="/ui/acmeclient/certificates">', '</a>') }}</li>
            </ul>
            <p><b>{{ lang._("Please read the official documentation for the preferred CA before using this plugin. It should give you a good overview about how their implementation of the ACME protocol works, so you do not hit their %srate limits%s and avoid common misconfigurations. Otherwise all attempts to issue a certificate would most likely fail. ") | format('<a href="https://letsencrypt.org/docs/rate-limits/">', '</a>') }}</b>{{ lang._("Ensure to use a %stest CA%s when using this plugin for the first time or while testing a new challenge type. Note that you will have to reissue your certificates when switching from a test CA to a production CA to get valid certificates.") | format('<a href="https://letsencrypt.org/docs/staging-environment/">', '</a>') }}</p>
            <p>{{ lang._('Please use the %sissue tracker%s to report bugs or request new features. Note that some CAs offer paid services. These services are not affiliated to this plugin. The maintainers and developers of this plugin will not provide support for paid services.') | format('<a href="https://github.com/opnsense/plugins/issues">', '</a>') }}</p>
        </div>
    </div>

    <div id="settings" class="tab-pane fade {% if showIntro|default('0')=='0' %}in active{% endif %}">
{{ partial("layout_partials/base_form",['fields':settingsForm,'id':'frm_settings'])}}
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
        <button class="btn btn-primary" id="configtestAct" type="button"><b>{{ lang._('Test Config') }}</b><i id="configtestAct_progress" class=""></i></button>
        <button class="btn btn-primary" id="resetAct" type="button"><b>{{ lang._('Reset ACME Client') }}</b><i id="resetAct_progress" class=""></i></button>
        <br/>
    </div>
    <div class="col-md-12">
        <br/>
        <p>{{ lang._('This plugin includes code from the %s project.') | format('<a href="https://github.com/acmesh-official/acme.sh">acmesh-official/acme.sh</a>' ) }} {{ lang._('Licensed under %sGPLv3%s.') | format('<a href="https://github.com/acmesh-official/acme.sh/blob/master/LICENSE.md">', '</a>' ) }}<br/>{{ lang._("Let's Encrypt(tm) is a trademark of the Internet Security Research Group. All rights reserved.") }}</p>
        <br/>
    </div>

</div>
