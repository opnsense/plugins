{#
 # Copyright (c) 2026 Henry Stern <henry@stern.ca>
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
    'use strict';

    $(document).ready(function () {

        // -----------------------------------------------------------------------
        // Deep-link filter: when arriving from the instance "Firewall rules" (fire)
        // button, the URL carries ?instance=<uuid>. Read it and scope the grid's
        // search endpoint to that instance so only its rules are shown.
        // -----------------------------------------------------------------------
        const deepLinkInstance = new URLSearchParams(window.location.search).get('instance') || '';

        // The instance the grid is currently scoped to. Read by the grid's
        // requestHandler (below) and updated by the dropdown without ever
        // tearing the grid down. Seeded from the deep-link, if present.
        let scopedInstance = deepLinkInstance;

        // Wire the bootgrid change-alert. UIBootgrid.showSaveAlert() (fired after
        // every add/edit/delete/toggle) slides down the element named by the
        // table's data-editAlert attribute — same mechanism core uses on
        // OPNsense/IDS/index.volt (<table data-editAlert="rulesetChangeMessage">).
        // base_bootgrid_table (core-shared) does not forward editAlert, so set it
        // on the table element BEFORE UIBootgrid() captures it as $compatElement.

        // "Match" cell: a newline-delimited summary of the rule's non-empty
        // matchers, e.g. "host=any\ngroup=db". Built from the matcher fields
        // returned by searchItemAction.
        function nebula_fw_match_formatter(column, row) {
            let fields = ['host', 'groups', 'cidr', 'local_cidr', 'ca_name', 'ca_sha'];
            let parts = [];
            fields.forEach(function (f) {
                let v = row[f];
                if (v !== undefined && v !== null && String(v).trim() !== '') {
                    parts.push($('<div/>').text(f + '=' + v).html());
                }
            });
            return parts.join('<br/>');
        }

        // The grid is created ONCE. Instance scoping is applied per-request via
        // a requestHandler that injects the selected uuid into the search POST
        // (mirrors core OPNsense/Firewall/filter_rule.volt, which scopes its
        // grid by interface/category the same way). The search endpoint reads
        // the `instance` param via $request->get() and filters server-side.
        const grid_fw = $("#{{formGridFirewallRule['table_id']}}").UIBootgrid({
            search: '/api/nebula/firewall_rule/search_item',
            get:    '/api/nebula/firewall_rule/get_item/',
            set:    '/api/nebula/firewall_rule/set_item/',
            add:    '/api/nebula/firewall_rule/add_item/',
            del:    '/api/nebula/firewall_rule/del_item/',
            toggle: '/api/nebula/firewall_rule/toggle_item/',
            options: {
                selection: false,
                formatters: { nebula_fw_match: nebula_fw_match_formatter },
                requestHandler: function (request) {
                    if (scopedInstance) {
                        request['instance'] = scopedInstance;
                    }
                    return request;
                }
            }
        });

        // -----------------------------------------------------------------------
        // Instance filter dropdown (above the grid). Populated from the instance
        // API plus an "All" option; changing it re-points the grid search and
        // reloads. Pre-selects the deep-linked instance when present.
        // -----------------------------------------------------------------------
        const $filter = $("#nebula_fw_instance_filter");

        ajaxGet('/api/nebula/instance/search_item', {}, function (data, status) {
            $filter.append($("<option/>").val('').text('{{ lang._('All instances') }}'));
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    let label = row.description ? row.description : row.uuid;
                    $filter.append($("<option/>").val(row.uuid).text(label));
                });
            }
            // Pre-select the deep-linked instance, if any.
            $filter.val(deepLinkInstance);
            if ($filter.hasClass('selectpicker')) {
                $filter.selectpicker('refresh');
            }
        });

        // Changing the dropdown only updates the scope and reloads the existing
        // grid in place — no destroy/recreate, so no orphaned search widgets.
        $filter.on('changed.bs.select change', function () {
            scopedInstance = $(this).val() || '';
            $("#{{formGridFirewallRule['table_id']}}").bootgrid('reload');
        });

        // -----------------------------------------------------------------------
        // CA name typeahead: ca_name may be typed OR picked from the configured
        // CAs. We attach an HTML5 <datalist> of CA names (the cert-embedded cn)
        // to the rule dialog's ca_name input — the input stays free-text, the
        // datalist just offers the known CAs as suggestions. Server-side
        // referential integrity (FirewallRuleController::checkCaReferences) is
        // what actually enforces that a typed ca_name names a configured CA.
        // -----------------------------------------------------------------------
        ajaxGet('/api/nebula/authority/search_item', {}, function (data, status) {
            const $dl = $('<datalist id="nebula_ca_names"></datalist>');
            const seen = {};
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    const cn = row.cn;
                    if (cn && !seen[cn]) {
                        seen[cn] = true;
                        $dl.append($("<option/>").val(cn));
                    }
                });
            }
            $('body').append($dl);
            // base_dialog renders the field with id "rule.ca_name" (escape the dot).
            $("#rule\\.ca_name").attr('list', 'nebula_ca_names');
        });

        // -----------------------------------------------------------------------
        // Apply / reconfigure button — re-render Nebula configs after rule edits.
        // -----------------------------------------------------------------------
        $("#reconfigureAct").SimpleActionButton();

        // Persistent "apply needed": if a prior change has not been reconfigured,
        // raise the change notice on load too (the marker is cleared on Apply).
        ajaxGet('/api/nebula/service/dirty', {}, function (data) {
            if (data && data.isDirty) {
                $(document).trigger('settings-changed');
            }
        });

        // Render the page-header service controls (start/restart/stop) on load —
        // same as core OPNsense/IPsec/tunnels.volt / OPNsense/IDS/index.volt.
        // Without it the controls only appear after an Apply and vanish on reload.
        updateServiceControlUI('nebula');
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#firewall_tab">{{ lang._('Allow List') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="firewall_tab" class="tab-pane fade in active">
        <div class="col-md-12" style="margin-top: 10px; margin-bottom: 10px;">
            <label for="nebula_fw_instance_filter">{{ lang._('Instance') }}</label>
            <select id="nebula_fw_instance_filter" class="selectpicker" data-width="300px"
                    data-live-search="true"></select>
        </div>
        {{ partial('layout_partials/base_bootgrid_table', formGridFirewallRule + {
            'command_width': '160'
        }) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {
    'data_endpoint': '/api/nebula/service/reconfigure',
    'data_service_widget': 'nebula',
    'data_error_title': 'Error reconfiguring Nebula'
}) }}

{# ============================================================================
   Edit dialog (standard CRUD — base_dialog handles save via UIBootgrid)
============================================================================ #}
{{ partial("layout_partials/base_dialog", [
    'fields': formDialogFirewallRule,
    'id':     formGridFirewallRule['edit_dialog_id'],
    'label':  lang._('Edit Firewall Rule')
]) }}
