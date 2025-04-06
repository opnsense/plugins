{#
 # Copyright (c) 2023-2025 Cedrik Pischem
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
        // Update the URL hash when tabs are clicked
        if (location.hash) {
            $(`#maintabs a[href="${location.hash}"]`).tab('show');
        }

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            const hash = e.target.hash;
            if (history.replaceState) {
                history.replaceState(null, null, hash);
            } else {
                location.hash = hash;
            }
        });

        $(window).on('hashchange', function () {
            $(`#maintabs a[href="${location.hash}"]`).tab('show');
        });

        // Bootgrid Setup
        const all_grids = {};

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            let grid_ids = [];

{% if entrypoint == 'reverse_proxy' %}

            switch (e.target.hash) {
                case '#domains':
                    grid_ids = ["{{ formGridReverseProxy['table_id'] }}", "{{ formGridSubdomain['table_id'] }}"];
                    break;
                case '#handlers':
                    grid_ids = ["{{ formGridHandle['table_id'] }}"];
                    break;
                case '#access':
                    grid_ids = ["{{ formGridAccessList['table_id'] }}", "{{ formGridBasicAuth['table_id'] }}"];
                    break;
                case '#headers':
                    grid_ids = ["{{ formGridHeader['table_id'] }}"];
                    break;
            }

{% elseif entrypoint == 'layer4' %}

            switch (e.target.hash) {
                case '#routes':
                    grid_ids = ["{{ formGridLayer4['table_id'] }}"];
                    break;
                case '#matchers':
                    grid_ids = ["{{ formGridLayer4Openvpn['table_id'] }}"];
                    break;
            }

{% endif %}

            const labels = {
                upstream: "{{ lang._('Upstream') }}",
                domain: '<i class="fa fa-fw fa-globe text-success"></i>' + "{{ lang._('Domain') }}",
                subdomain: '<i class="fa fa-fw fa-globe text-warning"></i>' + "{{ lang._('Subdomain') }}",
            };

            if (grid_ids.length > 0) {
                grid_ids.forEach(function(grid_id) {
                    if (!all_grids[grid_id]) {
                        // Define commands only for the specific grids
                        let commands = {};

{% if entrypoint == 'reverse_proxy' %}

                        if (["{{ formGridReverseProxy['table_id'] }}", "{{ formGridSubdomain['table_id'] }}"].includes(grid_id)) {
                            commands.search_handlers = {
                                method: function () {
                                    const rowUuid = $(this).data("row-id");
                                    if (!rowUuid) return;

                                    $('#reverseFilter')
                                        .selectpicker('val', [rowUuid])
                                        .selectpicker('refresh')
                                        .trigger('change');

                                    $('#maintabs a[href="#handlers"]').tab('show');
                                },
                                classname: 'fa fa-fw fa-search',
                                title: "{{ lang._('Search Handlers') }}",
                                sequence: 20
                            };
                        }

{% endif %}

                        all_grids[grid_id] = $("#" + grid_id)
                        .UIBootgrid({
                            search: `/api/caddy/ReverseProxy/search${grid_id}/`,
                            get: `/api/caddy/ReverseProxy/get${grid_id}/`,
                            set: `/api/caddy/ReverseProxy/set${grid_id}/`,
                            add: `/api/caddy/ReverseProxy/add${grid_id}/`,
                            del: `/api/caddy/ReverseProxy/del${grid_id}/`,
                            toggle: `/api/caddy/ReverseProxy/toggle${grid_id}/`,
                            options: {
                                requestHandler: function (request) {
                                    const selectedDomains = $('#reverseFilter').val();
                                    if (selectedDomains && selectedDomains.length > 0) {
                                        request['domainUuids'] = selectedDomains;
                                    }
                                    return request;
                                },
                                headerFormatters: {
                                    enabled: function (column) { return "" },
                                    ToDomain: function (column) { return labels.upstream; },
                                    FromDomain: function (column) {
                                        if (grid_id === "Subdomain") {
                                            return labels.subdomain;
                                        } else {
                                            return labels.domain;
                                        }
                                    },
                                    reverse: function (column) {
                                        return labels.domain;
                                    },
                                    subdomain: function (column) {
                                        return labels.subdomain;
                                    },
                                },
                                formatters: {
                                    model_relation_domain: function (column, row) {
                                        let result = (row[column.id] || "").trim();
                                        if (column.id === "reverse") {
                                            result = result.replace(" ", ":");
                                            if (!row["subdomain"] && row["HandlePath"]) {
                                                result += row["HandlePath"];
                                            }
                                        } else if (column.id === "subdomain") {
                                            if (row["subdomain"] && row["HandlePath"]) {
                                                result += row["HandlePath"];
                                            }
                                        }
                                        return result;
                                    },
                                    from_domain: function (column, row) {
                                        return (
                                            (row["DisableTls"] || "") +
                                            (row["FromDomain"] || "") +
                                            (row["FromPort"] ? `:${row["FromPort"]}` : "")
                                        );
                                    },
                                    to_domain: function (column, row) {
                                        return (
                                            (row["HttpTls"] || "") +
                                            (row["ToDomain"] || "") +
                                            (row["ToPort"] ? `:${row["ToPort"]}` : "") +
                                            (row["ToPath"] || "")
                                        );
                                    },
                                },
                            },
                            commands: commands
                        });

                        $("#" + grid_id).wrap('<div class="bootgrid-box"></div>');

                    }

{% if entrypoint == 'reverse_proxy' %}

                    // insert buttons and selectpicker
                    if (['{{formGridReverseProxy["table_id"]}}', '{{formGridHandle["table_id"]}}'].includes(grid_id)) {
                        const header = $("#" + grid_id + "-header");
                        const $actionBar = header.find('.actionBar');
                        if ($actionBar.length) {
                            $('#add_filter_container').detach().insertBefore($actionBar.find('.search'));
                            $('#add_filter_container').show();
                        }
                    }

{% endif %}

                });
            }
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

        // Hide message area when starting new actions
        $('input, select, textarea').on('change', function() {
            $("#messageArea").hide();
        });

        // Populate domain filter selectpicker
        $('#reverseFilter').fetch_options('/api/caddy/ReverseProxy/getAllReverseDomains');

        // Clear domain filter selectpicker
        $('#reverseFilterClear').on('click', function () {
            $('#reverseFilter').selectpicker('val', []);
            $('#reverseFilter').selectpicker('refresh');
            $('#reverseFilter').trigger('change');
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
                    updateServiceControlUI('caddy');
                } else {
                    showAlert("{{ lang._('Action was not successful or an error occurred.') }}", "error");
                }
            }
        });

{% if entrypoint == 'reverse_proxy' %}

        // Safe reload on filter change, ensures all grids are initalized beforehand
        $('#reverseFilter').change(function () {
            Object.keys(all_grids).forEach(function (grid_id) {
                if ([
                    '{{ formGridReverseProxy["table_id"] }}',
                    '{{ formGridSubdomain["table_id"] }}',
                    '{{ formGridHandle["table_id"] }}'
                ].includes(grid_id)) {
                    all_grids[grid_id].bootgrid('reload');
                }

            });
        });

        // Autofill domain and subdomain when add dialog is opened
        $('#{{ formGridHandle["edit_dialog_id"] }}, #{{ formGridSubdomain["edit_dialog_id"] }}').on('opnsense_bootgrid_mapped', function(e, actionType) {
            if (actionType === 'add') {
                const selectedDomains = $('#reverseFilter').val();

                if (selectedDomains && selectedDomains.length > 0) {
                    $('#handle\\.reverse, #handle\\.subdomain, #subdomain\\.reverse')
                        .selectpicker('val', selectedDomains)
                        .selectpicker('refresh');
                }
            }
        });

{% endif %}

        $("#handle\\.HttpTls, #handle\\.HandleDirective, #reverse\\.DisableTls, #layer4\\.Matchers, #layer4\\.Type").on("keyup change", function () {
            const http_tls = String($("#handle\\.HttpTls").val() || "")
            const handle_directive = String($("#handle\\.HandleDirective").val() || "")
            const disable_tls = String($("#reverse\\.DisableTls").val() || "")
            const layer4_matchers = String($("#layer4\\.Matchers").val() || "")
            const layer4_type = String($("#layer4\\.Type").val() || "")

            const styleVisibility = [
                {
                    class: "style_tls_reverse",
                    visible: disable_tls === "0"
                },
                {
                    class: "style_tls_handle",
                    visible: http_tls === "1" && handle_directive === "reverse_proxy"
                },
                {
                    class: "style_reverse_proxy",
                    visible: handle_directive === "reverse_proxy"
                },
                {
                    class: "style_domain",
                    visible: layer4_matchers === "tlssni" || layer4_matchers === "httphost"
                },
                {
                    class: "style_openvpn",
                    visible: layer4_matchers === "openvpn"
                },
                {
                    class: "style_type",
                    visible: layer4_type === "global"
                },
            ];

            styleVisibility.forEach(style => {
                // hide/show rows with the class
                const elements = $("." + style.class).closest("tr");
                style.visible ? elements.show() : elements.hide();

                // hide/show thead only if its parent container has the same class
                $(".table-responsive." + style.class).find("thead").each(function () {
                    style.visible ? $(this).show() : $(this).hide();
                });
            });
        });

        updateServiceControlUI('caddy');
        $('<div id="messageArea" class="alert alert-info" style="display: none;"></div>').insertBefore('#change_message_base_form');
        $('a[data-toggle="tab"].active, #maintabs li.active a').trigger('shown.bs.tab');
    });

    // Repopulate the filter selectpicker when domain data changes, keeping user selections intact
    $(document).ajaxSuccess(function(event, xhr, settings) {
        const domain_changed = ['add', 'set', 'del']
            .flatMap(action => ['Subdomain', 'ReverseProxy']
            .map(type => `/api/caddy/ReverseProxy/${action}${type}/`))
            .some(prefix => settings.url.startsWith(prefix));

        if (domain_changed) {
            const $filter = $('#reverseFilter');
            const selected_values = $filter.val() || [];

            $filter
                .fetch_options('/api/caddy/ReverseProxy/getAllReverseDomains')
                .done(function () {
                    // Keep only options that still exist
                    const valid_selections = selected_values.filter(val =>
                        $filter.find(`option[value="${val}"]`).length > 0
                    );

                    // Restore previous selection
                    $filter.selectpicker('val', valid_selections);
                    $filter.selectpicker('refresh');
                    $filter.trigger('change');
                });
        }
    });

</script>

<style>
    #add_filter_container {
        margin-left: 10px;
        margin-right: 20px;
    }
    #add_domain_container {
        float: left;
    }
    #add_handle_container {
        margin-left: 10px;
        float: left;
    }
    .actionBar {
        padding-left: 0px;
    }
    .custom-header {
        font-weight: 800;
        font-size: 16px;
        font-style: italic;
    }
    /* Prevent bootgrid to break out of content box*/
    .content-box {
        overflow-x: auto;
    }
    .bootgrid-header,
    .bootgrid-box,
    .bootgrid-footer {
        width: 100%;
        background: none;
        border: none;
        max-width: 100%;
        /* Prevents the grid from collapsing all dynamic columns completely */
        min-width: 700px;
    }
    /* Not all dropdowns support data-container="body", ensure minimal vertical space for them */
    .bootgrid-box {
        min-height: 150px;
    }
    /* Limit size of grid dropdown */
    .actions .dropdown-menu.pull-right {
        max-height: 200px;
        min-width: max-content;
        overflow-y: auto;
        overflow-x: hidden;
    }
    #reverseFilterClear {
        border-right: none;
    }
    #add_filter_container .bootstrap-select > .dropdown-toggle {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
</style>

<div id="add_filter_container" class="btn-group" style="display: none;">
    <button type="button" id="reverseFilterClear" class="btn btn-default" title="Clear Selection">
        <i class="fa fa-fw fa-times"></i>
    </button>
    <select id="reverseFilter" class="selectpicker form-control" multiple data-live-search="true" data-width="200px" data-size="10" data-container="body" title="{{ lang._('Filter by Domain') }}">
    </select>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">

{% if entrypoint == 'reverse_proxy' %}

    <li id="tab-domains" class="active"><a data-toggle="tab" href="#domains">{{ lang._('Domains') }}</a></li>
    <li id="tab-handlers"><a data-toggle="tab" href="#handlers">{{ lang._('Handlers') }}</a></li>
    <li id="tab-access"><a data-toggle="tab" href="#access">{{ lang._('Access') }}</a></li>
    <li id="tab-headers"><a data-toggle="tab" href="#headers">{{ lang._('Headers') }}</a></li>

{% elseif entrypoint == 'layer4' %}

    <li id="tab-layer4" class="active"><a data-toggle="tab" href="#routes">{{ lang._('Layer4 Routes') }}</a></li>
    <li id="tab-matcher"><a data-toggle="tab" href="#matchers">{{ lang._('Layer7 Matcher Settings') }}</a></li>

{% endif %}

</ul>

<div class="tab-content content-box">

{% if entrypoint == 'reverse_proxy' %}

    <!-- Combined Domains Tab -->
    <div id="domains" class="tab-pane fade in active">
        <div style="padding-left: 16px;">
            <!-- Reverse Proxy -->
            <h1 class="custom-header">{{ lang._('Domains') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridReverseProxy + {'command_width': '9em'})}}
            </div>
        </div>

        <!-- Subdomains Tab -->
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Subdomains') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridSubdomain + {'command_width': '9em'})}}
            </div>
        </div>
    </div>

    <!-- Handle Tab -->
    <div id="handlers" class="tab-pane fade">
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Handlers') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridHandle)}}
            </div>
        </div>
    </div>

    <!-- Combined Access Tab -->
    <div id="access" class="tab-pane fade">
        <!-- Access Lists Section -->
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Access Lists') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridAccessList)}}
            </div>
        </div>

        <!-- Basic Auth Section -->
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Basic Auth') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridBasicAuth)}}
            </div>
        </div>
    </div>

    <!-- Header Tab -->
    <div id="headers" class="tab-pane fade">
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Headers') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridHeader)}}
            </div>
        </div>
    </div>

{% elseif entrypoint == 'layer4' %}

    <!-- Layer4 Tab -->
    <div id="routes" class="tab-pane fade active in">
        <div style="padding-left: 16px;">
            <h1 class="custom-header">{{ lang._('Layer4 Routes') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridLayer4)}}
            </div>
        </div>
    </div>

    <!-- Layer7 Tab -->
    <div id="matchers" class="tab-pane fade">
        <div style="padding-left: 16px;">
            <!-- OpenVPN Matcher -->
            <h1 class="custom-header">{{ lang._('OpenVPN Static Keys') }}</h1>
            <div style="display: block;">
                {{ partial('layout_partials/base_bootgrid_table', formGridLayer4Openvpn)}}
            </div>
        </div>
    </div>

{% endif %}

</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/caddy/service/reconfigure'}) }}

{% if entrypoint == 'reverse_proxy' %}

{{ partial("layout_partials/base_dialog",['fields':formDialogReverseProxy,'id':formGridReverseProxy['edit_dialog_id'],'label':lang._('Edit Domain')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubdomain,'id':formGridSubdomain['edit_dialog_id'],'label':lang._('Edit Subdomain')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHandle,'id':formGridHandle['edit_dialog_id'],'label':lang._('Edit Handler')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAccessList,'id':formGridAccessList['edit_dialog_id'],'label':lang._('Edit Access List')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogBasicAuth,'id':formGridBasicAuth['edit_dialog_id'],'label':lang._('Edit Basic Auth')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHeader,'id':formGridHeader['edit_dialog_id'],'label':lang._('Edit Header')])}}

{% elseif entrypoint == 'layer4' %}

{{ partial("layout_partials/base_dialog",['fields':formDialogLayer4,'id':formGridLayer4['edit_dialog_id'],'label':lang._('Edit Layer4 Route')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogLayer4Openvpn,'id':formGridLayer4Openvpn['edit_dialog_id'],'label':lang._('Edit OpenVPN Static Key')])}}

{% endif %}
