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
    {% set placeholder_txt = 'Click the Show Config button to load the current configuration. Please note that this is not a configuration from the running process memory. ' %}
    {% set placeholder_txt = placeholder_txt ~ 'Also, the displayed configuration may differ from the configuration in OPNsense config if you have made but not applied changes.' %}

    ngnx_config = [];
    function bind_naxsi_rule_dl_button() {
        let naxsi_rule_download_button = $('#naxsiruledownloadbtn');
        naxsi_rule_download_button.click(function () {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: "{{ lang._('Download NAXSI Rules') }}",
                message: "{{ lang._('You are about to download the core rules from the Repository of NAXSI. You have to accept its %slicense%s to download the rules.')|format("<a href='https://github.com/nbs-system/naxsi/blob/master/LICENSE' target='_blank'>", "</a>") }}",
                buttons: [{
                    label: "{{ lang._('Accept And Download') }}",
                    cssClass: 'btn-primary',
                    icon: 'fa fa-download',
                    action: function (dlg) {
                        dlg.close();
                        ajaxCall(url = "/api/nginx/settings/downloadrules", sendData = {}, callback = function (data, status) {
                            $('#naxsiruledownloadalert').hide();
                            // reload view after installing rules
                            $('#grid-naxsirule').bootgrid('reload');
                            $('#grid-custompolicy').bootgrid('reload');
                        });
                    }
                }, {
                    label: '{{ lang._('Reject') }}',
                    action: function (dlg) {
                        dlg.close();
                    }
                }]
            });
        });
    }

    function ngnx_show_conf() {

        $("#nginx_conf tbody").empty().append('<tr><td class="placeholdertd">{{ lang._("Waiting for response..") }}</td></tr>');
        $("#config_help_text").hide();
        // clear existing config in memory (if any)
        ngnx_config = [];
        ajaxCall(url="/api/nginx/settings/showconfig/", sendData={}, callback=function(data,status) {
            if (data['time'] && data['config']) {
                let L = 0;
                let content = [];
                $.each(data['config'], function(index, line) {
                    // use lodash unescape to safely decode html chars in line and store for clipboard copy
                    ngnx_config.push(_.unescape(line));
                    L = line.indexOf('# configuration file ') > -1 ? 0 : L + 1;
                    // line received HTML-encoded. Should be XSS-safe if not decoded before inserting to DOM
                    content.push('<tr><td class="l-number">' + L.toString() + '</td><td class="config-line"><span>' + line + '</span></td></tr>');
                });
                $("#nginx_conf tbody").empty().append(content.join());
                $("#config_help_text").show();
                if ((typeof navigator.clipboard === 'object') && (typeof navigator.clipboard.writeText === 'function')) {
                    $('#nginx_config_copy').show();
                }
                BootstrapDialog.show({
                   type: BootstrapDialog.TYPE_INFO,
                   title: "{{ lang._('NGINX config loaded successfully') }}",
                   message: "{{ lang._('NGINX config loaded. Config file created at') }}" + ": " + (new Date(data['time']*1000)).toLocaleString(),
                   buttons: [{
                       label: '{{ lang._('Ok') }}',
                       action: function(dlg){
                           dlg.close();
                       }
                   }]
                });
            } else {
                  $("#nginx_conf td.placeholdertd").text("{{ lang._('Empty response from the backend. Please check logs.') }}");
            }
        });
    }

    function ngnx_test_conf() {
        ajaxCall(url="/api/nginx/settings/testconfig/", sendData={}, callback=function(data,status) {
            if (data['response'].indexOf('test failed') > -1) {
                 BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('NGINX config test failed') }}",
                    message: data['response'],
                    buttons: [{
                        label: '{{ lang._('Ok') }}',
                        action: function(dlg){
                             dlg.close();
                        }
                    }]
                });
            } else {
                 BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_INFO,
                    title: "{{ lang._('NGINX config test is successful') }}",
                    message: "{{ lang._('NGINX config test is successful') }}",
                    buttons: [{
                        label: '{{ lang._('Ok') }}',
                        action: function(dlg){
                             dlg.close();
                        }
                    }]
                });
            }
        });
    }

    $(function() {
        $("#nginx_config_copy").click(function () {
            if (ngnx_config.length) {
                $(this).fadeOut();
                navigator.clipboard.writeText(ngnx_config.join('\n'));
                $(this).fadeIn();
            }
        });
        $("#subtab_item_nginx-other-config-preview").click(function () {
            $("#nginx_conf tbody").empty().append('<tr><td class="placeholdertd">{{ lang._(placeholder_txt) }}</td></tr>');
        });

        $("#conf_show_btn").click(function () {
            ngnx_show_conf();
        });
        $("#conf_test_btn").click(function () {
            ngnx_test_conf();
        });
    });
</script>
<script src="{{ cache_safe('/ui/js/nginx/lib/lodash.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/nginx/lib/backbone-min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/nginx/dist/configuration.min.js') }}"></script>
<link rel="stylesheet" href="{{ cache_safe('/ui/css/nginx/index.css') }}" type="text/css" />

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settings]) }}
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-http-location').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0;"><b>{{ lang._('HTTP(S)')}}</b></a>
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
                <a data-toggle="tab" id="subtab_item_nginx-http-server" href="#subtab_nginx-http-httpserver">{{ lang._('HTTP Server')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-rewrite" href="#subtab_nginx-http-rewrite">{{ lang._('URL Rewriting')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-custompolicy" href="#subtab_nginx-http-custompolicy">{{ lang._('Naxsi WAF Policy')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-naxsirule" href="#subtab_nginx-http-naxsirule">{{ lang._('Naxsi WAF Rule')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-security_header" href="#subtab_nginx-http-security_header">{{ lang._('Security Headers')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-cache_path" href="#subtab_nginx-http-cache_path">{{ lang._('Cache Path')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-proxy_cache_valid" href="#subtab_nginx-http-proxy_cache_valid">{{ lang._('Response Code Caching')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-errorpages" href="#subtab_nginx-http-errorpages">{{ lang._('Error Pages')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-tls-fingerprint" href="#subtab_nginx-http-tls-fingerprint">{{ lang._('TLS Fingerprint (Advanced)')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-resolver" href="#subtab_nginx-http-resolver">{{ lang._('Resolvers')}}</a>
            </li>
        </ul>
    </li>
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown"
           href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-streams-streamserver').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0px;"><b>{{ lang._('Data Streams')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-streams-streamserver" href="#subtab_nginx-streams-streamserver">{{ lang._('Stream Servers')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-streams-snifwd" href="#subtab_nginx-streams-snifwd">{{ lang._('SNI Based Routing')}}</a>
            </li>
        </ul>
    </li>
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown"
           href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-http-upstream-server').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right: 0;"><b>{{ lang._('Upstream')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-upstream-server" href="#subtab_nginx-http-upstream-server">{{ lang._('Upstream Server')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-upstream" href="#subtab_nginx-http-upstream">{{ lang._('Upstream')}}</a>
            </li>
        </ul>
    </li>
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown"
           href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-access-request-limit').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0px;"><b>{{ lang._('Access')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-access-request-limit" href="#subtab_nginx-access-request-limit">{{ lang._('Limit Zone')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-access-request-limit-connection" href="#subtab_nginx-access-request-limit-connection">{{ lang._('Connection Limits')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-acl-ip" href="#subtab_nginx-acl-ip">{{ lang._('IP ACLs')}}</a>
            </li>
        </ul>
    </li>
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown"
           href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-other-syslog-target').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0px;"><b>{{ lang._('Other')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-other-syslog-target" href="#subtab_nginx-other-syslog-target">{{ lang._('SYSLOG Targets')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-other-config-preview" href="#subtab_nginx-other-config-preview">{{ lang._('Config Preview')}}</a>
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
                <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="urlpattern" data-type="string" data-sortable="true" data-visible="true">{{ lang._('URL Pattern') }}</th>
                <th data-column-id="path_prefix" data-type="string" data-sortable="true" data-visible="true">{{ lang._('URL Path Prefix') }}</th>
                <th data-column-id="matchtype" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Match Type') }}</th>
                <th data-column-id="upstream" data-type="string" data-sortable="true" data-visible="false">{{ lang._('Upstream') }}</th>
                <th data-column-id="waf_status" data-type="string" data-sortable="true" data-visible="true">{{ lang._('WAF Status') }}</th>
                <th data-column-id="xss_block_score" data-type="string" data-sortable="true" data-visible="false">{{ lang._('XSS Score') }}</th>
                <th data-column-id="sqli_block_score" data-type="string" data-sortable="true" data-visible="false">{{ lang._('SQLi Score') }}</th>
                <th data-column-id="custom_policy" data-type="string" data-width="50%" data-sortable="true" data-visible="false">{{ lang._('WAF Policies') }}</th>
                <th data-column-id="force_https" data-type="numeric" data-width="10em" data-sortable="true" data-visible="true" data-formatter="boolean">{{ lang._('Force HTTPS') }}</th>
                <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div id="subtab_nginx-http-upstream-server" class="tab-pane fade">
        <table id="grid-upstreamserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="upstreamserverdlg">
            <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="description" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="server" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Server') }}</th>
                <th data-column-id="port" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Port') }}</th>
                <th data-column-id="priority" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Priority') }}</th>
                <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>


    <div id="subtab_nginx-http-upstream" class="tab-pane fade">
        <table id="grid-upstream" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="upstreamdlg">
            <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="description" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="serverentries" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Servers') }}</th>
                <th data-column-id="load_balancing_algorithm" data-type="string" data-sortable="false" data-visible="false">{{ lang._('Load Balancing') }}</th>
                <th data-column-id="tls_enable" data-type="numeric" data-width="12em" data-sortable="false" data-visible="true" data-formatter="boolean">{{ lang._('TLS Enabled') }}</th>
                <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-credential" class="tab-pane fade">
        <table id="grid-credential" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="credentialdlg">
            <thead>
            <tr>
                <th data-column-id="username" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
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
                <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-httpserver" class="tab-pane fade">
        <table id="grid-httpserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="httpserverdlg">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="servername" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Servername') }}</th>
                    <th data-column-id="locations" data-type="string" data-sortable="true" data-visible="false">{{ lang._('Locations') }}</th>
                    <th data-column-id="root" data-type="string" data-sortable="true" data-visible="false">{{ lang._('File System Root') }}</th>
                    <th data-column-id="certificate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Certificate') }}</th>
                    <th data-column-id="https_only" data-type="numeric" data-width="7em" data-sortable="true" data-visible="true" data-formatter="boolean">{{ lang._('HTTPS Only') }}</th>
                    <th data-column-id="listen_http_address" data-type="string" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('HTTP Address') }}</th>
                    <th data-column-id="listen_https_address" data-type="string" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('HTTPS Address') }}</th>
                    <th data-column-id="default_server" data-type="numeric" data-width="7em" data-sortable="true" data-visible="true" data-formatter="boolean">{{ lang._('Default') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-streams-streamserver" class="tab-pane fade">
        <table id="grid-streamserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="streamserverdlg">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="certificate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Certificate') }}</th>
                    <th data-column-id="udp" data-type="numeric" data-sortable="true" data-visible="true" data-formatter="boolean">{{ lang._('UDP') }}</th>
                    <th data-column-id="listen_address" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Address') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-rewrite" class="tab-pane fade">
        <table id="grid-httprewrite" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="httprewritedlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="source" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Source URL') }}</th>
                    <th data-column-id="destination" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Destination URL') }}</th>
                    <th data-column-id="flag" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Flag') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-custompolicy" class="tab-pane fade">
        {% if (show_naxsi_download_button) %}
        <div class="alert alert-info" id="naxsiruledownloadalert" role="alert" style="vertical-align: middle;display: table;width: 100%;">
            <div style="display: table-cell;vertical-align: middle;">{{ lang._('It looks like you are not having any rules installed. You may want to download the NAXSI core rules.') }}</div>
            <div class="pull-right" style="vertical-align: middle;display: table-cell;">
                <button id="naxsiruledownloadbtn" class="btn btn-primary">
                    <i class="fa fa-download" aria-hidden="true"></i> {{ lang._('Download') }}
                </button>
            </div>
        </div>
        {% endif %}
        <table id="grid-custompolicy" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="custompolicydlg">
            <thead>
                <tr>
                    <th data-column-id="name" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="operator" data-type="string" data-width="12em" data-sortable="true" data-visible="true">{{ lang._('Operator') }}</th>
                    <th data-column-id="value" data-type="string" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('Value') }}</th>
                    <th data-column-id="naxsi_rules" data-type="string" data-sortable="true" data-visible="false">{{ lang._('Rules') }}</th>
                    <th data-column-id="action" data-type="string" data-width="12em" data-sortable="true" data-visible="true">{{ lang._('Action') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-naxsirule" class="tab-pane fade">
        <table id="grid-naxsirule" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="naxsiruledlg">
            <thead>
                <tr>
                    <th data-column-id="identifier" data-type="numeric" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('ID') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="ruletype" data-type="string" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('Rule Type') }}</th>
                    <th data-column-id="match_type" data-type="string" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('Match Type') }}</th>
                    <th data-column-id="score" data-type="numeric" data-width="7em" data-sortable="true" data-visible="true">{{ lang._('Score') }}</th>
                    <th data-column-id="match_value" data-type="string" data-sortable="true" data-visible="false">{{ lang._('Value') }}</th>
                    <th data-column-id="message" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Message') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-security_header" class="tab-pane fade">
        <table id="grid-security_header" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="security_headersdlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="referrer" data-type="string" data-sortable="true" data-visible="false">{{ lang._('Referrer') }}</th>
                    <th data-column-id="xssprotection" data-type="string" data-sortable="true" data-visible="true">{{ lang._('XSS Protection') }}</th>
                    <th data-column-id="hsts" data-type="string" data-sortable="true" data-visible="true">{{ lang._('HSTS') }}</th>
                    <th data-column-id="csp" data-type="string" data-sortable="true" data-visible="true">{{ lang._('CSP') }}</th>
                    <th data-column-id="csp_details" data-type="string" data-sortable="true" data-visible="false">{{ lang._('CSP Rules') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-cache_path" class="tab-pane fade">
        <table id="grid-cache_path" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="cache_pathdlg">
            <thead>
                <tr>
                    <th data-column-id="path" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Path') }}</th>
                    <th data-column-id="size" data-type="numeric" data-sortable="true" data-visible="true">{{ lang._('Size') }}</th>
                    <th data-column-id="inactive" data-type="numeric" data-sortable="true" data-visible="true">{{ lang._('Inactive') }}</th>
                    <th data-column-id="max_size" data-type="numeric" data-sortable="true" data-visible="true">{{ lang._('Max Size') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-proxy_cache_valid" class="tab-pane fade">
        <table id="grid-proxy_cache_valid" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="proxy_cache_validdlg">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="code" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Codes') }}</th>
                    <th data-column-id="valid" data-type="numeric" data-sortable="true" data-visible="true">{{ lang._('Time') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-access-request-limit" class="tab-pane fade">
        <table id="grid-limit_zone" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="limit_zonedlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="key" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Key') }}</th>
                    <th data-column-id="size" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Size') }}</th>
                    <th data-column-id="rate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Rate') }}</th>
                    <th data-column-id="rate_unit" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Rate Unit') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-access-request-limit-connection" class="tab-pane fade">
        <table id="grid-limit_request_connection" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="limit_request_connectiondlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="limit_zone" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Limit Zone') }}</th>
                    <th data-column-id="connection_count" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Connection Count') }}</th>
                    <th data-column-id="burst" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Burst') }}</th>
                    <th data-column-id="nodelay" data-type="string" data-sortable="true" data-visible="true">{{ lang._('No Delay') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-streams-snifwd" class="tab-pane fade">
        <table id="grid-snifwd" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="sni_hostname_mapdlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-acl-ip" class="tab-pane fade">
        <table id="grid-ipacl" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="ipacl_dlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-errorpages" class="tab-pane fade">
        <table id="grid-errorpage" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="errorpage_dlg">
            <thead>
                <tr>
                    <th data-column-id="name" data-width="15%" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="statuscodes" data-type="string" data-formatter="statuscodes" data-sortable="false" data-visible="true">{{ lang._('Status Codes') }}</th>
                    <th data-column-id="response" data-width="13em" data-type="string" data-formatter="response" data-sortable="true">{{ lang._('Response') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-tls-fingerprint" class="tab-pane fade">
        <table id="grid-tls_fingerprint" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="tls_fingerprint_dlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-resolver" class="tab-pane fade">
        <table id="grid-resolver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="resolverdlg">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-sortable="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="address" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Address') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-other-syslog-target" class="tab-pane fade">
        <table id="grid-syslog_target" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="syslog_target_dlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="host" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Host') }}</th>
                    <th data-column-id="facility" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Facility') }}</th>
                    <th data-column-id="severity" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Severity') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-other-config-preview" class="tab-pane fade">
        <div id="nginx_conf_container" class="nginx_table_responsive">
            <table class="ngx_conf_table" id="nginx_conf">
                <tbody class="ngx_conf_table_body"></tbody>
            </table>
            <table class="table table-striped table-condensed">
                <tbody>
                    <tr>
                        <td>
                            <div id="config_help_text" style="display:none">
                                {{ lang._("Configuration files may contain sensitive information, keep it safe.") }}
                                <a id="nginx_config_copy" style="display:none">{{ lang._('Click here to copy to clipboard.') }}</a>
                            </div>
                            <div>
                                <button class="btn btn-primary" id="conf_show_btn" data-type="config" type="button"><b>{{ lang._('Show Config') }}</b></button>
                                <button class="btn btn-primary" id="conf_test_btn" data-type="test" type="button"><b>{{ lang._('Test Config') }}</b></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields': upstream,'id':'upstreamdlg', 'label':lang._('Edit Upstream')]) }}
{{ partial("layout_partials/base_dialog",['fields': upstream_server,'id':'upstreamserverdlg', 'label':lang._('Edit Upstream')]) }}
{{ partial("layout_partials/base_dialog",['fields': location,'id':'locationdlg', 'label':lang._('Edit Location')]) }}
{{ partial("layout_partials/base_dialog",['fields': credential,'id':'credentialdlg', 'label':lang._('Edit Credential')]) }}
{{ partial("layout_partials/base_dialog",['fields': userlist,'id':'userlistdlg', 'label':lang._('Edit User List')]) }}
{{ partial("layout_partials/base_dialog",['fields': httpserver,'id':'httpserverdlg', 'label':lang._('Edit HTTP Server')]) }}
{{ partial("layout_partials/base_dialog",['fields': streamserver,'id':'streamserverdlg', 'label':lang._('Edit Stream Server')]) }}
{{ partial("layout_partials/base_dialog",['fields': httprewrite,'id':'httprewritedlg', 'label':lang._('Edit URL Rewrite')]) }}
{{ partial("layout_partials/base_dialog",['fields': naxsi_custom_policy,'id':'custompolicydlg', 'label':lang._('Edit WAF Policy')]) }}
{{ partial("layout_partials/base_dialog",['fields': naxsi_rule,'id':'naxsiruledlg', 'label':lang._('Edit Naxsi Rule')]) }}
{{ partial("OPNsense/Nginx/tabbed_dialog",['fields': security_headers,'id':'security_headersdlg', 'label':lang._('Edit Security Headers')]) }}
{{ partial("layout_partials/base_dialog",['fields': limit_request_connection,'id':'limit_request_connectiondlg', 'label':lang._('Edit Request Connection Limit')]) }}
{{ partial("layout_partials/base_dialog",['fields': limit_zone,'id':'limit_zonedlg', 'label':lang._('Edit Limit Zone')]) }}
{{ partial("layout_partials/base_dialog",['fields': cache_path,'id':'cache_pathdlg', 'label':lang._('Edit Cache Path')]) }}
{{ partial("layout_partials/base_dialog",['fields': proxy_cache_valid,'id':'proxy_cache_validdlg', 'label':lang._('Edit Response Code Caching')]) }}
{{ partial("layout_partials/base_dialog",['fields': sni_hostname_map,'id':'sni_hostname_mapdlg', 'label':lang._('Edit SNI Hostname Mapping')]) }}
{{ partial("layout_partials/base_dialog",['fields': ipacl,'id':'ipacl_dlg', 'label':lang._('Edit IP ACL')]) }}
{{ partial("layout_partials/base_dialog",['fields': errorpage,'id':'errorpage_dlg', 'label':lang._('Edit Error Page')]) }}
{{ partial("layout_partials/base_dialog",['fields': tls_fingerprint,'id':'tls_fingerprint_dlg', 'label':lang._('Edit TLS Fingerprint')]) }}
{{ partial("layout_partials/base_dialog",['fields': resolver,'id':'resolverdlg', 'label':lang._('Edit Resolver')]) }}
{{ partial("layout_partials/base_dialog",['fields': syslog_target,'id':'syslog_target_dlg', 'label':lang._('Edit SYSLOG Target')]) }}
