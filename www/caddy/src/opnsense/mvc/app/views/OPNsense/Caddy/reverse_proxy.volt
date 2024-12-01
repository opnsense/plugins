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
        // Bootgrid Setup
        $("#reverseProxyGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchReverseProxy/',
            get:'/api/caddy/ReverseProxy/getReverseProxy/',
            set:'/api/caddy/ReverseProxy/setReverseProxy/',
            add:'/api/caddy/ReverseProxy/addReverseProxy/',
            del:'/api/caddy/ReverseProxy/delReverseProxy/',
            toggle:'/api/caddy/ReverseProxy/toggleReverseProxy/',
            options: {
                requestHandler: addDomainFilterToRequest,
                rowCount: [4,7,14,20,50,100,-1]
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
                requestHandler: addDomainFilterToRequest,
                rowCount: [4,7,14,20,50,100,-1]
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
            options:{
                rowCount: [4,7,14,20,50,100,-1]
            }
        });

        $("#basicAuthGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchBasicAuth/',
            get:'/api/caddy/ReverseProxy/getBasicAuth/',
            set:'/api/caddy/ReverseProxy/setBasicAuth/',
            add:'/api/caddy/ReverseProxy/addBasicAuth/',
            del:'/api/caddy/ReverseProxy/delBasicAuth/',
            options:{
                rowCount: [4,7,14,20,50,100,-1]
            }
        });

        $("#reverseHeaderGrid").UIBootgrid({
            search:'/api/caddy/ReverseProxy/searchHeader/',
            get:'/api/caddy/ReverseProxy/getHeader/',
            set:'/api/caddy/ReverseProxy/setHeader/',
            add:'/api/caddy/ReverseProxy/addHeader/',
            del:'/api/caddy/ReverseProxy/delHeader/',
        });

        /**
         * Modifies the search request to include domain filter.
         *
         * @param {Object} request - The original request object.
         * @returns {Object} The modified request object with domain filter.
         */
        function addDomainFilterToRequest(request) {
            let selectedDomains = $('#reverseFilter').val();
            if (selectedDomains && selectedDomains.length > 0) {
                request['reverseUuids'] = selectedDomains.join(',');
            }
            return request;
        }

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

        /**
         * Loads domain filters from the server and populates the filter dropdown.
         */
        function loadDomainFilters() {
            ajaxGet('/api/caddy/ReverseProxy/getAllReverseDomains', null, function(data, status) {
                let select = $('#reverseFilter');
                select.empty();
                if (status === "success" && data && data.rows) {
                    data.rows.forEach(function(item) {
                        select.append($('<option>').val(item.id).text(item.domainPort));
                    });
                } else {
                    select.html('<option value="">{{ lang._('Failed to load data') }}</option>');
                }
                select.selectpicker('refresh');
            }).fail(function() {
                $('#reverseFilter').html('<option value="">{{ lang._('Failed to load data') }}</option>').selectpicker('refresh');
            });
        }

        /**
         * Controls the visibility of the selectpicker and add buttons based on the active tab.
         *
         * @param {string} tab - The currently active tab.
         */
        function toggleVisibility(tab) {
            if (tab === 'handlesTab' || tab === 'domainsTab') {
                $("#addDomainBtn").show();
                $("#addHandleBtn").show();
                $('.common-filter').show();
            } else {
                $("#addDomainBtn").hide();
                $("#addHandleBtn").hide();
                $('.common-filter').hide();
            }
        }

        function reloadGrids() {
            $("#reverseProxyGrid").bootgrid("reload");
            $("#reverseSubdomainGrid").bootgrid("reload");
            $("#reverseHandleGrid").bootgrid("reload");
        }

        // Hide message area when starting new actions
        $('input, select, textarea').on('change', function() {
            $("#messageArea").hide();
        });

        // Reconfigure button with custom validation
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();

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

        $("#clearDomains").on("click", function(e) {
            e.preventDefault();
            $('#reverseFilter').val([]);
            $('#reverseFilter').selectpicker('refresh');

            reloadGrids();
        });

        // Reload Bootgrid on filter change
        $('#reverseFilter').on('changed.bs.select', function() {
            reloadGrids();
        });

        // Initialize visibility based on the active tab on page load
        let activeTab = $('#maintabs .active a').attr('href').replace('#', '');
        toggleVisibility(activeTab);

        // Change event when switching tabs
        $('#maintabs a').on('click', function (e) {
            let currentTab = $(this).attr('href').replace('#', '');
            toggleVisibility(currentTab);
        });

        // Add click event listener for "Add HTTP Handler" button
        $("#addHandleBtn").on("click", function() {
            if ($('#maintabs .active a').attr('href') === "#handlesTab") {
                $("#addReverseHandleBtn").click();
            } else {
                $('#maintabs a[href="#handlesTab"]').tab('show').one('shown.bs.tab', function(e) {
                    $("#addReverseHandleBtn").click();
                });
            }
        });

        // Add click event listener for "Add Domain" button
        $("#addDomainBtn").on("click", function() {
            if ($('#maintabs .active a').attr('href') === "#domainsTab") {
                $("#addReverseProxyBtn").click();
            } else {
                $('#maintabs a[href="#domainsTab"]').tab('show').one('shown.bs.tab', function(e) {
                    $("#addReverseProxyBtn").click();
                });
            }
        });

        // Hide TLS specific options when http or h2c is selected
        $("#handle\\.HttpTls").change(function() {
            if ($(this).val() != "1") {
                $(".style_tls").closest('tr').hide();
            } else {
                $(".style_tls").closest('tr').show();
            }
        });

        $("#handle\\.HandleDirective").change(function() {
            if ($(this).val() === "redir") {
                $(".style_reverse_proxy").prop('disabled', true);
                $("#handle\\.header").selectpicker('refresh');
            } else {
                $(".style_reverse_proxy").prop('disabled', false);
                $("#handle\\.header").selectpicker('refresh');
            }
        });

        // Hide TLS specific options when http is selected
        $("#reverse\\.DisableTls").change(function() {
            if ($(this).val() === "1") {
                $(".style_tls").closest('tr').hide();
            } else {
                $(".style_tls").closest('tr').show();
            }
        });

        $("#layer4\\.Matchers").change(function() {
            if ($(this).val() !== "tlssni" && $(this).val() !== "httphost") {
                $(".style_matchers").closest('tr').hide();
            } else {
                $(".style_matchers").closest('tr').show();
            }
        });

        updateServiceControlUI('caddy');
        loadDomainFilters();
    });
</script>

<style>
    .common-filter {
        align-items: center;
        margin-top: 20px;
        margin-right: 5px;
        padding: 0 15px;
    }

    .filter-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    #clearDomains {
        margin-top: 5px;
    }

    .custom-header {
        font-weight: 800;
        font-size: 16px;
        font-style: italic;
    }

</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li id="tab-domains" class="active"><a data-toggle="tab" href="#domainsTab">{{ lang._('Domains') }}</a></li>
    <li id="tab-handlers"><a data-toggle="tab" href="#handlesTab">{{ lang._('HTTP Handlers') }}</a></li>
    <li id="tab-access"><a data-toggle="tab" href="#accessTab">{{ lang._('HTTP Access') }}</a></li>
    <li id="tab-headers"><a data-toggle="tab" href="#headerTab">{{ lang._('HTTP Headers') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- Container using flexbox -->
    <div class="form-group common-filter" style="display: flex; justify-content: space-between; align-items: center;">
        <!-- Button group on the left -->
        <div>
            <button id="addDomainBtn" type="button" class="btn btn-secondary">{{ lang._('Step 1: Add Domain') }}</button>
            <button id="addHandleBtn" type="button" class="btn btn-secondary">{{ lang._('Step 2: Add HTTP Handler') }}</button>
        </div>
        <!-- Selectpicker and Clear All on the right -->
        <div class="filter-actions" style="display: flex; flex-direction: column; align-items: flex-end;">
            <select id="reverseFilter" class="selectpicker form-control" multiple data-live-search="true" data-width="348px" data-size="7" title="{{ lang._('Filter by Domain') }}">
            </select>
            <a href="#" class="text-danger" id="clearDomains" style="margin-top: 5px;">
                <i class="fa fa-times-circle"></i> <small>Clear All</small>
            </a>
        </div>
    </div>

    <!-- Combined Domains Tab -->
    <div id="domainsTab" class="tab-pane fade in active">
        <div style="padding-left: 16px;">
            <!-- Reverse Proxy -->
            <h1 class="custom-header">{{ lang._('Domains') }}</h1>
            <div style="display: block;"> <!-- Common container -->
                <table id="reverseProxyGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogReverseProxy" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="DisableTls" data-type="string">{{ lang._('Protocol') }}</th>
                            <th data-column-id="FromDomain" data-type="string">{{ lang._('Domain') }}</th>
                            <th data-column-id="FromPort" data-type="string">{{ lang._('Port') }}</th>
                            <th data-column-id="accesslist" data-type="string" data-visible="false">{{ lang._('Access List') }}</th>
                            <th data-column-id="basicauth" data-type="string" data-visible="false">{{ lang._('Basic Auth') }}</th>
                            <th data-column-id="DnsChallenge" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('DNS-01 Challenge') }}</th>
                            <th data-column-id="DynDns" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('Dynamic DNS') }}</th>
                            <th data-column-id="AccessLog" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('HTTP Access Log') }}</th>
                            <th data-column-id="CustomCertificate" data-type="string" data-visible="false">{{ lang._('Certificate') }}</th>
                            <th data-column-id="AcmePassthrough" data-type="string" data-visible="false">{{ lang._('HTTP-01 Challenge Redirection') }}</th>
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
                                <button id="addReverseProxyBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Subdomains Tab -->
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Subdomains') }}</h1>
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
                            <th data-column-id="AcmePassthrough" data-type="string" data-visible="false">{{ lang._('HTTP-01 Challenge Redirection') }}</th>
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
                                <button id="addSubdomainBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
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
            <h1 class="custom-header">{{ lang._('HTTP Handlers') }}</h1>
            <div style="display: block;"> <!-- Common container -->
                <table id="reverseHandleGrid" class="table table-condensed table-hover table-striped" data-editDialog="DialogHandle" data-editAlert="ConfigurationChangeMessage">
                    <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="boolean" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="reverse" data-type="string">{{ lang._('Domain') }}</th>
                            <th data-column-id="subdomain" data-type="string">{{ lang._('Subdomain') }}</th>
                            <th data-column-id="HandleType" data-type="string" data-visible="false">{{ lang._('Handler') }}</th>
                            <th data-column-id="HandlePath" data-type="string" data-visible="false">{{ lang._('Path') }}</th>
                            <th data-column-id="header" data-type="string" data-visible="false">{{ lang._('HTTP Headers') }}</th>
                            <th data-column-id="HandleDirective" data-type="string">{{ lang._('Directive') }}</th>
                            <th data-column-id="HttpTls" data-type="string" data-visible="false">{{ lang._('Protocol') }}</th>
                            <th data-column-id="ToDomain" data-type="string">{{ lang._('Upstream Domain') }}</th>
                            <th data-column-id="ToPort" data-type="string">{{ lang._('Upstream Port') }}</th>
                            <th data-column-id="ToPath" data-type="string" data-visible="false">{{ lang._('Upstream Path') }}</th>
                            <th data-column-id="ForwardAuth" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('Forward Auth') }}</th>
                            <th data-column-id="accesslist" data-type="string" data-visible="false">{{ lang._('Access List') }}</th>
                            <th data-column-id="HttpVersion" data-type="string" data-visible="false">{{ lang._('HTTP Version') }}</th>
                            <th data-column-id="HttpKeepalive" data-type="string" data-visible="false">{{ lang._('HTTP Keepalive') }}</th>
                            <th data-column-id="HttpTlsTrustedCaCerts" data-type="string" data-visible="false">{{ lang._('TLS Trust Pool') }}</th>
                            <th data-column-id="HttpTlsServerName" data-type="string" data-visible="false">{{ lang._('TLS Server Name') }}</th>
                            <th data-column-id="HttpNtlm" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('NTLM') }}</th>
                            <th data-column-id="HttpTlsInsecureSkipVerify" data-type="boolean" data-formatter="boolean" data-visible="false">{{ lang._('TLS Insecure Skip Verify') }}</th>
                            <th data-column-id="PassiveHealthFailDuration" data-type="string" data-visible="false">{{ lang._('Upstream Fail Duration') }}</th>
                            <th data-column-id="lb_policy" data-type="string" data-visible="false">{{ lang._('Load Balance Policy') }}</th>
                            <th data-column-id="lb_retries" data-type="string" data-visible="false">{{ lang._('Load Balance Retries') }}</th>
                            <th data-column-id="lb_try_duration" data-type="string" data-visible="false">{{ lang._('Load Balance Try Duration') }}</th>
                            <th data-column-id="lb_try_interval" data-type="string" data-visible="false">{{ lang._('Load Balance Try Interval') }}</th>
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
                                <button id="addReverseHandleBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Combined Access Tab -->
    <div id="accessTab" class="tab-pane fade">
        <!-- Access Lists Section -->
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Access Lists') }}</h1>
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
                                <button id="addAccessListBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Basic Auth Section -->
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Basic Auth') }}</h1>
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
                                <button id="addBasicAuthBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
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
            <h1 class="custom-header">{{ lang._('HTTP Headers') }}</h1>
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
                                <button id="addReverseHeaderBtn" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button>
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

{{ partial("layout_partials/base_dialog",['fields':formDialogReverseProxy,'id':'DialogReverseProxy','label':lang._('Edit Domain')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubdomain,'id':'DialogSubdomain','label':lang._('Edit Subdomain')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHandle,'id':'DialogHandle','label':lang._('Edit HTTP Handler')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAccessList,'id':'DialogAccessList','label':lang._('Edit Access List')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogBasicAuth,'id':'DialogBasicAuth','label':lang._('Edit Basic Auth')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHeader,'id':'DialogHeader','label':lang._('Edit HTTP Header')])}}
