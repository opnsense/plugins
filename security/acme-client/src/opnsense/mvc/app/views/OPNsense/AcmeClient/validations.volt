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

        /***********************************************************************
         * link grid actions
         **********************************************************************/

        $("#grid-validations").UIBootgrid(
            {   search:'/api/acmeclient/validations/search',
                get:'/api/acmeclient/validations/get/',
                set:'/api/acmeclient/validations/update/',
                add:'/api/acmeclient/validations/add/',
                del:'/api/acmeclient/validations/del/',
                toggle:'/api/acmeclient/validations/toggle/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        // hook into on-show event for dialog to extend layout.
        $('#DialogValidation').on('shown.bs.modal', function (e) {
            $("#validation\\.dns_service").change(function(){
                var service_id = 'table_' + $(this).val();
                $(".table_dns").hide();
                if ($("#validation\\.method").val() == 'dns01') {
                    $("."+service_id).show();
                }
                // Show a warning if the Google Cloud SDK plugin is missing.
                ajaxCall(url="/api/acmeclient/settings/getGcloudPluginStatus", sendData={}, callback=function(data,status) {
                    if (data['result'] != 0) {
                        $(".gcloud_plugin_warning").hide();
                    }
                });
                // Show a warning if the BIND plugin is missing.
                ajaxCall(url="/api/acmeclient/settings/getBindPluginStatus", sendData={}, callback=function(data,status) {
                    if (data['result'] != 0) {
                        $(".bind_plugin_warning").hide();
                    }
                });
            });
            $("#validation\\.http_service").change(function(){
                var service_id = 'table_http_' + $(this).val();
                $(".table_http").hide();
                if ($("#validation\\.method").val() == 'http01') {
                    $("."+service_id).show();
                } else {
                }
            });
            $("#validation\\.tlsalpn_service").change(function(){
                var service_id = 'table_tlsalpn_' + $(this).val();
                $(".table_tlsalpn").hide();
                if ($("#validation\\.method").val() == 'tlsalpn01') {
                    $("."+service_id).show();
                } else {
                }
            });
            $("#validation\\.method").change(function(){
                $(".method_table").hide();
                $(".method_table_"+$(this).val()).show();
                $("#validation\\.dns_service").change();
                $("#validation\\.http_service").change();
                $("#validation\\.tlsalpn_service").change();
            });
            $("#validation\\.method").change();

        })
    });

</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li {% if showIntro|default('0')=='1' %}class="active"{% endif %}><a data-toggle="tab" id="validations-introduction" href="#subtab_validations-introduction"><b>{{ lang._('Introduction') }}</b></a></li>
    <li {% if showIntro|default('0')=='0' %}class="active"{% endif %}><a data-toggle="tab" id="validations-tab" href="#validations"><b>{{ lang._('Challenge Types') }}</b></a></li>
</ul>

<div class="content-box tab-content">

    <div id="subtab_validations-introduction" class="tab-pane fade {% if showIntro|default('0')=='1' %}in active{% endif %}">
        <div class="col-md-12">
            <h1>{{ lang._('Challenge Types') }}</h1>
            <p>{{ lang._('As defined by the ACME standard, Certificate Authorities (CAs) must validate that you control a domain name. This is done by using "challenges". The following challenge types are supported:') }}</p>
            <ul>
              <li>{{ lang._('%sDNS-01:%s This is the most reliable challenge type and thus highly recommended when using this plugin. It requires that you control the DNS for your domain name and that your DNS provider is supported both %sby acme.sh%s and this plugin.') | format('<b>', '</b>', '<a href="https://github.com/acmesh-official/acme.sh/wiki/dnsapi" target="_blank">', '</a>') }}</li>
              <li>{{ lang._("%sHTTP-01:%s This challenge type usually requires manual configuration and is not recommended. The DNS name used in the certificate must point to the OPNsense host where the ACME Client plugin is running on. The integrated web service will try to guess the correct settings for your setup, but this may not always work out-of-the-box. Furthermore this challenge type cannot be used to validate %swildcard certificates with Let's Encrypt%s.") | format('<b>', '</b>', '<a href="https://letsencrypt.org/docs/challenge-types/#http-01-challenge" target="_blank">', '</a>') }}</li>
              <li>{{ lang._("%sTLS-ALPN-01:%s This works similar to the HTTP-01 challenge type and has the same requirements. It works if port 80 is unavailable. Other challenge types should be preferred. This challenge type cannot be used to validate %swildcard certificates with Let's Encrypt%s.") | format('<b>', '</b>', '<a href="https://letsencrypt.org/docs/challenge-types/#tls-alpn-01" target="_blank">', '</a>') }}</li>
            </ul>
            <p>{{ lang._('When experiencing issues with a challenge type, try setting the log level to "debug". Please provide full logs when %sreporting issues%s for a challenge type. You should also consider to ask the Certificate Authority for support, if you choose to use a commercial CA.') | format('<a href="https://github.com/opnsense/plugins/issues">', '</a>') }}</p>
        </div>
    </div>

    <div id="validations" class="tab-pane fade {% if showIntro|default('0')=='0' %}in active{% endif %}">
        <table id="grid-validations" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogValidation">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="method" data-type="string">{{ lang._('Challenge Type') }}</th>
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

</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogValidation,'id':'DialogValidation','label':lang._('Edit Challenge Type')])}}
