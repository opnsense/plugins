{#
 # Copyright (c) 2023-2024 Cedrik Pischem
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

        // Function to handle the search filter request modification
        function addDomainFilterToRequest(request) {
            let selectedDomains = $('#reverseFilter').val();
            if (selectedDomains && selectedDomains.length > 0) {
                request['reverseUuids'] = selectedDomains.join(',');
            }
            return request;
        }

        // Bootgrid Setup
        $("#reverseProxyGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchReverseProxy/',
            get:'/api/caddy/ReverseProxy/getReverseProxy/',
            set:'/api/caddy/ReverseProxy/setReverseProxy/',
            add:'/api/caddy/ReverseProxy/addReverseProxy/',
            del:'/api/caddy/ReverseProxy/delReverseProxy/',
            toggle:'/api/caddy/ReverseProxy/toggleReverseProxy/',
            options: {
                requestHandler: addDomainFilterToRequest
            }
        });

        $("#reverseSubdomainGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchSubdomain/',
            get:'/api/caddy/ReverseProxy/getSubdomain/',
            set:'/api/caddy/ReverseProxy/setSubdomain/',
            add:'/api/caddy/ReverseProxy/addSubdomain/',
            del:'/api/caddy/ReverseProxy/delSubdomain/',
            toggle:'/api/caddy/ReverseProxy/toggleSubdomain/',
            options: {
                requestHandler: addDomainFilterToRequest
            }
        });

        $("#reverseHandleGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchHandle/',
            get:'/api/caddy/ReverseProxy/getHandle/',
            set:'/api/caddy/ReverseProxy/setHandle/',
            add:'/api/caddy/ReverseProxy/addHandle/',
            del:'/api/caddy/ReverseProxy/delHandle/',
            toggle:'/api/caddy/ReverseProxy/toggleHandle/',
            options: {
                requestHandler: addDomainFilterToRequest
            }
        });

        $("#accessListGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchAccessList/',
            get:'/api/caddy/ReverseProxy/getAccessList/',
            set:'/api/caddy/ReverseProxy/setAccessList/',
            add:'/api/caddy/ReverseProxy/addAccessList/',
            del:'/api/caddy/ReverseProxy/delAccessList/',
        });

        $("#basicAuthGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchBasicAuth/',
            get:'/api/caddy/ReverseProxy/getBasicAuth/',
            set:'/api/caddy/ReverseProxy/setBasicAuth/',
            add:'/api/caddy/ReverseProxy/addBasicAuth/',
            del:'/api/caddy/ReverseProxy/delBasicAuth/',
        });

        $("#reverseHeaderGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchHeader/',
            get:'/api/caddy/ReverseProxy/getHeader/',
            set:'/api/caddy/ReverseProxy/setHeader/',
            add:'/api/caddy/ReverseProxy/addHeader/',
            del:'/api/caddy/ReverseProxy/delHeader/',
        });

        // Function to show alerts in the HTML message area
        function showAlert(message, type = "error") {
            let alertClass = type === "error" ? "alert-danger" : "alert-success";
            let messageArea = $("#messageArea");

            // Stop any current animation, clear the queue, and immediately hide the element
            messageArea.stop(true, true).hide();

            // Now set the class and message
            messageArea.removeClass("alert-success alert-danger").addClass(alertClass).html(message);

            // Use fadeIn to make the message appear smoothly, then fadeOut after a delay
            messageArea.fadeIn(500).delay(15000).fadeOut(500, function() {
                // Clear the message after fading out to ensure it's clean for the next message
                $(this).html('');
            });
        }

        // Hide message area when starting new actions
        $('input, select, textarea').on('change', function() {
            $("#messageArea").hide();
        });

        // Adjusting the Reconfigure button to include validation in onPreAction
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();

                // Perform configuration validation
                $.ajax({
                    url: "/api/caddy/service/validate",
                    type: "GET",
                    dataType: "json",
                    success: function(data) {
                        if (data && data['status'].toLowerCase() === 'ok') {
                            // If configuration is valid, resolve the Deferred object to proceed
                            dfObj.resolve();
                        } else {
                            // If configuration is invalid, show alert and reject the Deferred object
                            showAlert(data['message'], "{{ lang._('Validation Failed') }}");
                            dfObj.reject();
                        }
                    },
                    error: function(xhr, status, error) {
                        // On AJAX error, show alert and reject the Deferred object
                        showAlert("{{ lang._('Validation request failed: ') }}" + error, "{{ lang._('Error') }}");
                        dfObj.reject();
                    }
                });

                return dfObj.promise();
            },
            onAction: function(data, status) {
                // Check if the action was successful
                if (status === "success" && data && data['status'].toLowerCase() === 'ok') {
                    // Update only the service control UI for 'caddy'
                    showAlert("{{ lang._('Configuration applied successfully.') }}", "{{ lang._('Apply Success') }}");
                    updateServiceControlUI('caddy');
                } else {
                    console.error("{{ lang._('Action was not successful or an error occurred:') }}", data);
                }
            }
        });

        // Initialize the service control UI for 'caddy'
        updateServiceControlUI('caddy');

        // Filter function for domains
        function loadDomainFilters() {
            $.ajax({
                url: '/api/caddy/ReverseProxy/getAllReverseDomains', // custom API endpoint to get uuid and domainport combinations
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    let select = $('#reverseFilter');
                    select.empty(); // Clear current options
                    if (data && data.rows) {
                        data.rows.forEach(function(item) {
                            select.append($('<option>').val(item.id).text(item.domainPort));
                        });
                    }
                    select.selectpicker('refresh'); // Refresh selectpicker to update the UI
                },
                error: function() {
                    $('#reverseFilter').html('<option value="">{{ lang._('Failed to load data') }}</option>').selectpicker('refresh');
                }
            });
        }
        loadDomainFilters();

        // Reload Bootgrid on filter change
        $('#reverseFilter').on('changed.bs.select', function() {
            $("#reverseProxyGrid").bootgrid("reload");
            $("#reverseSubdomainGrid").bootgrid("reload");
            $("#reverseHandleGrid").bootgrid("reload");
        });

        // Control the visibility of selectpicker for filter by domain
        function toggleSelectPicker(tab) {
            if (tab === 'handlesTab' || tab === 'domainsTab') {
                $('.common-filter').show();
            } else {
                $('.common-filter').hide();
            }
        }

        // Initialize visibility based on the active tab on page load
        let activeTab = $('#maintabs .active a').attr('href').replace('#', '');
        toggleSelectPicker(activeTab);

        // Change event when switching tabs
        $('#maintabs a').on('click', function (e) {
            let currentTab = $(this).attr('href').replace('#', '');
            toggleSelectPicker(currentTab);
        });

    });
</script>

<style>
    .common-filter {
        text-align: right;
        margin-top: 20px;
        margin-right: 5px;
        padding: 0 15px;  // Align with the tables
    }

</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#domainsTab">{{ lang._('Domains') }}</a></li>
    <li><a data-toggle="tab" href="#handlesTab">{{ lang._('Handlers') }}</a></li>
    <li><a data-toggle="tab" href="#accessTab">{{ lang._('Access') }}</a></li>
    <li><a data-toggle="tab" href="#headerTab">{{ lang._('Headers') }}</a></li>
</ul>

<div class="tab-content content-box">

    <!-- Selectpicker for filter by domain -->
    <div class="form-group common-filter">
        <select id="reverseFilter" class="selectpicker form-control" multiple data-live-search="true" data-width="348px" data-size="7" title="{{ lang._('Filter by Domain') }}">
            <!-- Options will be populated dynamically using JavaScript/Ajax -->
        </select>
    </div>

    <!-- Reverse Proxy Tab -->
    <div id="domainsTab" class="tab-pane fade in active">
        <div style="padding-left: 16px;">
            <!-- Reverse Proxy -->
            <h1>{{ lang._('Domains') }}</h1>
            <div style="display: block;"> <!-- Common container -->
                <table id="reverseProxyGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogReverseProxy" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="FromDomain" data-type="string">{{ lang._('Domain') }}</th>
                            <th data-column-id="FromPort" data-type="string">{{ lang._('Port') }}</th>
                            <th data-column-id="accesslist" data-type="string" data-visible="false">{{ lang._('Access List') }}</th>
                            <th data-column-id="basicauth" data-type="string" data-visible="false">{{ lang._('Basic Auth') }}</th>
                            <th data-column-id="DnsChallenge" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('DNS-01 challenge') }}</th>
                            <th data-column-id="DynDns" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('Dynamic DNS') }}</th>
                            <th data-column-id="AccessLog" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('HTTP Access Log') }}</th>
                            <th data-column-id="CustomCertificate" data-type="string" data-visible="false">{{ lang._('Custom Certificate') }}</th>
                            <th data-column-id="AcmePassthrough" data-type="string" data-visible="false">{{ lang._('HTTP-01 redirection') }}</th>
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
                                <button id="addReverseProxyBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div style="padding-left: 16px;">
            <!-- Subdomains -->
            <h1>{{ lang._('Subdomains') }}</h1>
            <div style="display: block;"> <!-- Common container -->
                <table id="reverseSubdomainGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogSubdomain" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="reverse" data-type="string">{{ lang._('Domain') }}</th>
                            <th data-column-id="FromDomain" data-type="string">{{ lang._('Subdomain') }}</th>
                            <th data-column-id="accesslist" data-type="string" data-visible="false">{{ lang._('Access List') }}</th>
                            <th data-column-id="basicauth" data-type="string" data-visible="false">{{ lang._('Basic Auth') }}</th>
                            <th data-column-id="DynDns" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('Dynamic DNS') }}</th>
                            <th data-column-id="AcmePassthrough" data-type="string" data-visible="false">{{ lang._('HTTP-01 redirection') }}</th>
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
                                <button id="addSubdomainBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Handle Tab -->
    <div id="handlesTab" class="tab-pane fade">
        <div style="padding-left: 16px;">
            <h1>{{ lang._('Handlers') }}</h1>
            <div style="display: block;"> <!-- Common container -->
                <table id="reverseHandleGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogHandle" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="reverse" data-type="string">{{ lang._('Domain') }}</th>
                            <th data-column-id="subdomain" data-type="string">{{ lang._('Subdomain') }}</th>
                            <th data-column-id="HandleType" data-type="string" data-visible="false">{{ lang._('Handle Type') }}</th>
                            <th data-column-id="HandlePath" data-type="string" data-visible="false">{{ lang._('Handle Path') }}</th>
                            <th data-column-id="header" data-type="string" data-visible="false">{{ lang._('Header') }}</th>
                            <th data-column-id="ToDomain" data-type="string">{{ lang._('Upstream Domain') }}</th>
                            <th data-column-id="ToPort" data-type="string">{{ lang._('Upstream Port') }}</th>
                            <th data-column-id="ToPath" data-type="string" data-visible="false">{{ lang._('Upstream Path') }}</th>
                            <th data-column-id="PassiveHealthFailDuration" data-type="string" data-visible="false">{{ lang._('Fail Duration') }}</th>
                            <th data-column-id="HttpTls" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('TLS') }}</th>
                            <th data-column-id="HttpTlsTrustedCaCerts" data-type="string" data-visible="false">{{ lang._('TLS CA') }}</th>
                            <th data-column-id="HttpTlsServerName" data-type="string" data-visible="false">{{ lang._('TLS Server Name') }}</th>
                            <th data-column-id="HttpNtlm" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('NTLM') }}</th>
                            <th data-column-id="HttpTlsInsecureSkipVerify" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('TLS Insecure Skip Verify') }}</th>
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
                                <button id="addReverseHandleBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- New Combined Access Tab -->
    <div id="accessTab" class="tab-pane fade">
        <!-- Access Lists Section -->
        <div style="padding-left: 16px;">
            <h1>{{ lang._('Access Lists') }}</h1>
            <div style="display: block;">
                <table id="accessListGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogAccessList" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="accesslistName" data-type="string">{{ lang._('Name') }}</th>
                            <th data-column-id="clientIps" data-type="string">{{ lang._('Client IPs') }}</th>
                            <th data-column-id="accesslistInvert" data-type="boolean" data-formatter="boolean">{{ lang._('Invert') }}</th>
                            <th data-column-id="HttpResponseCode" data-type="string" data-visible="false">{{ lang._('HTTP Code') }}</th>
                            <th data-column-id="HttpResponseMessage" data-type="string" data-visible="false">{{ lang._('HTTP Message') }}</th>
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
                                <button id="addAccessListBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Basic Auth Section -->
        <div style="padding-left: 16px;">
            <h1>{{ lang._('Basic Auth') }}</h1>
            <div style="display: block;">
                <table id="basicAuthGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogBasicAuth" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="basicauthuser" data-type="string">{{ lang._('User') }}</th>
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
                                <button id="addBasicAuthBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Header Tab -->
    <div id="headerTab" class="tab-pane fade">
        <div style="padding-left: 16px;">
            <h1>{{ lang._('Headers') }}</h1>
            <div style="display: block;"> <!-- Common container -->
                <table id="reverseHeaderGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogHeader" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="HeaderUpDown" data-type="string">{{ lang._('Header') }}</th>
                            <th data-column-id="HeaderType" data-type="string">{{ lang._('Header Type') }}</th>
                            <th data-column-id="HeaderValue" data-type="string" data-visible="false">{{ lang._('Header Value') }}</th>
                            <th data-column-id="HeaderReplace" data-type="string" data-visible="false">{{ lang._('Header Replace') }}</th>
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
                                <button id="addReverseHeaderBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
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

{{ partial("layout_partials/base_dialog",['fields':formDialogReverseProxy,'id':'DialogReverseProxy','label':lang._('Edit Reverse Proxy Domain')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubdomain,'id':'DialogSubdomain','label':lang._('Edit Reverse Proxy Subdomain')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHandle,'id':'DialogHandle','label':lang._('Edit Handler')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAccessList,'id':'DialogAccessList','label':lang._('Edit Access List')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogBasicAuth,'id':'DialogBasicAuth','label':lang._('Edit Basic Auth')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHeader,'id':'DialogHeader','label':lang._('Edit Header')])}}
