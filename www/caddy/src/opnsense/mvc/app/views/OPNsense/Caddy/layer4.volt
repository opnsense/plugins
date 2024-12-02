{#
 # Copyright (c) 2024 Cedrik Pischem
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
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
    $(document).ready(function() {
        // Bootgrid Setup
        $("#Layer4Grid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchLayer4/',
            get:'/api/caddy/ReverseProxy/getLayer4/',
            set:'/api/caddy/ReverseProxy/setLayer4/',
            add:'/api/caddy/ReverseProxy/addLayer4/',
            del:'/api/caddy/ReverseProxy/delLayer4/',
            toggle:'/api/caddy/ReverseProxy/toggleLayer4/',
        });

        $("#Layer4OpenvpnGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchLayer4Openvpn/',
            get:'/api/caddy/ReverseProxy/getLayer4Openvpn/',
            set:'/api/caddy/ReverseProxy/setLayer4Openvpn/',
            add:'/api/caddy/ReverseProxy/addLayer4Openvpn/',
            del:'/api/caddy/ReverseProxy/delLayer4Openvpn/',
            toggle:'/api/caddy/ReverseProxy/toggleLayer4Openvpn/',
        });

        /**
         * Displays an alert message to the user.
         *
         * @param {string} message - The message to display.
         * @param {string} [type="error"] - The type of alert (error or success).
         */
        function showAlert(message, type = "error") {
            const alertClass = type === "error" ? "alert-danger" : "alert-success";
            const messageArea = $("#messageArea");

            messageArea.stop(true, true).hide();
            messageArea.removeClass("alert-success alert-danger").addClass(alertClass).html(message);
            messageArea.fadeIn(500).delay(15000).fadeOut(500, function() {
                $(this).html('');
            });
        }

        $('input, select, textarea').on('change', function() {
            $("#messageArea").hide();
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();

                // Perform configuration validation
                ajaxGet("/api/caddy/service/validate", null, function(data, status) {
                    if (status === "success" && data && data['status'].toLowerCase() === 'ok') {
                        dfObj.resolve();
                    } else {
                        showAlert(data['message'], "error");
                        dfObj.reject();
                    }
                }).fail(function(xhr, status, error) {
                    showAlert("{{ lang._('Validation request failed: ') }}" + error, "error");
                    dfObj.reject();
                });

                return dfObj.promise();
            },
            onAction: function(data, status) {
                if (status === "success" && data && data['status'].toLowerCase() === 'ok') {
                    showAlert("{{ lang._('Configuration applied successfully.') }}", "{{ lang._('Apply Success') }}");
                    updateServiceControlUI('caddy');
                } else {
                    showAlert("{{ lang._('Action was not successful or an error occurred.') }}", "error");
                }
            }
        });

        $("#layer4\\.Matchers").change(function() {
            $(".style_matchers").closest('tr').hide();
            const selectedVal = $(this).val();

            if (selectedVal === "tlssni" || selectedVal === "httphost" || selectedVal === "quicsni") {
                $(".matchers_domain").closest('tr').show();
            } else if (selectedVal === "openvpn") {
                $(".matchers_openvpn").closest('tr').show();
            }
        });

        $("#layer4\\.Type").change(function() {
            if ($(this).val() === "global") {
                $(".style_type").closest('tr').show();
            } else {
                $(".style_type").closest('tr').hide();
            }
        });

        updateServiceControlUI('caddy');
    });
</script>

<style>
    .custom-header {
        font-weight: 800;
        font-size: 16px;
        font-style: italic;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li id="tab-layer4" class="active"><a data-toggle="tab" href="#layer4Tab">{{ lang._('Layer4 Routes') }}</a></li>
    <li id="tab-matcher"><a data-toggle="tab" href="#matcherTab">{{ lang._('Layer7 Matcher Settings') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- Layer4 Tab -->
    <div id="layer4Tab" class="tab-pane fade active in">
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Layer4 Routes') }}</h1>
            <div style="display: block;">
                <table id="Layer4Grid" class="table table-condensed table-hover table-striped" data-editDialog="DialogLayer4" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="Sequence" data-type="string">{{ lang._('Sequence') }}</th>
                            <th data-column-id="Type" data-type="string" data-visible="false">{{ lang._('Routing Type') }}</th>
                            <th data-column-id="Protocol" data-type="string">{{ lang._('Protocol') }}</th>
                            <th data-column-id="FromPort" data-type="string" data-visible="false">{{ lang._('Local Port') }}</th>
                            <th data-column-id="Matchers" data-type="string">{{ lang._('Matchers') }}</th>
                            <th data-column-id="InvertMatchers" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('Invert Matchers') }}</th>
                            <th data-column-id="TerminateTls" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('Terminate TLS') }}</th>
                            <th data-column-id="FromDomain" data-type="string">{{ lang._('Domain') }}</th>
                            <th data-column-id="FromOpenvpnModes" data-type="string" data-visible="false">{{ lang._('OpenVPN Modes') }}</th>
                            <th data-column-id="FromOpenvpnStaticKey" data-type="string" data-visible="false">{{ lang._('OpenVPN Static Key') }}</th>
                            <th data-column-id="ToDomain" data-type="string">{{ lang._('Upstream Domain') }}</th>
                            <th data-column-id="ToPort" data-type="string">{{ lang._('Upstream Port') }}</th>
                            <th data-column-id="RemoteIp" data-type="string" data-visible="false">{{ lang._('Remote IP') }}</th>
                            <th data-column-id="lb_policy" data-type="string" data-visible="false">{{ lang._('Load Balance Policy') }}</th>
                            <th data-column-id="PassiveHealthFailDuration" data-type="string" data-visible="false">{{ lang._('Passive Health Fail Duration') }}</th>
                            <th data-column-id="PassiveHealthMaxFails" data-type="string" data-visible="false">{{ lang._('Passive Health Max Fails') }}</th>
                            <th data-column-id="ProxyProtocol" data-type="string" data-visible="false">{{ lang._('Proxy Protocol') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td>
                                <button id="addLayer4Btn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Layer7 Tab -->
    <div id="matcherTab" class="tab-pane fade">
        <div style="padding-left: 16px;">
            <!-- OpenVPN Matcher -->
            <h1 class="custom-header">{{ lang._('OpenVPN Static Keys') }}</h1>
            <div style="display: block;">
                <table id="Layer4OpenvpnGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogLayer4Openvpn" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td>
                                <button id="addLayer4OpenvpnBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- Reconfigure Button -->
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint="/api/caddy/service/reconfigure"
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring Caddy') }}"
                    type="button"
            ></button>
            <br/><br/>
            <!-- Message Area for error/success messages -->
            <div id="messageArea" class="alert alert-info" style="display: none;"></div>
            <!-- Message Area to hint user to apply changes when data is changed in bootgrids -->
            <div id="ConfigurationChangeMessage" class="alert alert-info" style="display: none;">
            {{ lang._('Please do not forget to apply the configuration.') }}
            </div>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogLayer4,'id':'DialogLayer4','label':lang._('Edit Layer4 Route')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogLayer4Openvpn,'id':'DialogLayer4Openvpn','label':lang._('Edit OpenVPN Static Key')])}}
