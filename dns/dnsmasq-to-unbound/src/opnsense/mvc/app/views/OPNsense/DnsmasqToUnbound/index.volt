{#
    Copyright (c) 2025 C. Hall (chall37@users.noreply.github.com)
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1.  Redistributions of source code must retain the above copyright notice,
        this list of conditions and the following disclaimer.

    2.  Redistributions in binary form must reproduce the above copyright notice,
        this list of conditions and the following disclaimer in the documentation
        and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
$(document).ready(function() {
    // Track last records hash for change detection
    let lastRecordsHash = null;

    // Load settings form
    mapDataToFormUI({'frm_Settings': "/api/dnsmasqtounbound/settings/get"}).done(function() {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('dnsmasqtounbound');
    });

    // Save button - just save settings
    $("#saveAct").click(function() {
        saveFormToEndpoint("/api/dnsmasqtounbound/settings/set", 'frm_Settings', function(data, status) {
            if (status === "success" && data.status === 'ok') {
                $("#settingsChangeMessage").show();
            }
        }, true);
    });

    // Apply button - reconfigure service
    $("#applyAct").SimpleActionButton({
        onAction: function(data, status) {
            if (status === "success" && data.status === 'ok') {
                $("#settingsChangeMessage").hide();
                updateServiceControlUI('dnsmasqtounbound');
                // Refresh records table after reconfigure
                setTimeout(function() {
                    lastRecordsHash = null;  // Force refresh
                    refreshRecordsIfChanged();
                }, 1000);
            }
        }
    });

    // Track if grid is initialized
    let gridInitialized = false;

    // Initialize records table with UIBootgrid
    function initRecordsGrid() {
        if (gridInitialized) return;
        $("#grid-records").UIBootgrid({
            search: '/api/dnsmasqtounbound/service/searchrecords',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    "typeFormatter": function(column, row) {
                        if (row.type === 'static') {
                            return '<span class="label label-info">static</span>';
                        } else {
                            return '<span class="label label-success">lease</span>';
                        }
                    }
                }
            }
        });
        gridInitialized = true;
    }

    // Reload records table
    function loadRecords() {
        if (gridInitialized) {
            $("#grid-records").bootgrid("reload");
        } else {
            initRecordsGrid();
        }
    }

    // Check hash and refresh only if changed
    function refreshRecordsIfChanged() {
        $.ajax({
            url: '/api/dnsmasqtounbound/service/recordshash',
            method: 'POST',
            dataType: 'json',
            success: function(data) {
                const newHash = data.hash || '';
                if (lastRecordsHash === null || newHash !== lastRecordsHash) {
                    // Data changed or first load - reload table
                    lastRecordsHash = newHash;
                    loadRecords();
                }
                // If hash unchanged, do nothing
            }
        });
    }

    // Auto-refresh records table every 5 seconds when tab is active (only if data changed)
    let refreshInterval = null;

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if ($(e.target).attr('href') === '#records') {
            // Initial load via polling function
            refreshRecordsIfChanged();
            // Start polling for changes
            refreshInterval = setInterval(function() {
                refreshRecordsIfChanged();
            }, 5000);
        } else {
            // Stop auto-refresh when leaving records tab
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
    });

    updateServiceControlUI('dnsmasqtounbound');
});
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings"><b>{{ lang._('Settings') }}</b></a></li>
    <li><a data-toggle="tab" href="#records">{{ lang._('Registered Records') }}</a></li>
</ul>

<div class="content-box tab-content">
    <!-- Settings Tab -->
    <div id="settings" class="tab-pane fade in active">
        <div class="content-box">
            {{ partial("layout_partials/base_form", ['fields': settings, 'id': 'frm_Settings']) }}
        </div>
    </div>

    <!-- Records Tab -->
    <div id="records" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div class="col-sm-12">
                <table id="grid-records" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="fqdn" data-type="string" data-order="asc">{{ lang._('FQDN') }}</th>
                        <th data-column-id="ip" data-type="string">{{ lang._('IP Address') }}</th>
                        <th data-column-id="type" data-type="string" data-formatter="typeFormatter" data-width="8em">{{ lang._('Source') }}</th>
                        <th data-column-id="mac" data-type="string" data-width="12em">{{ lang._('MAC') }}</th>
                        <th data-column-id="expiry" data-type="string" data-width="12em">{{ lang._('Expiry') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <div class="col-md-12" style="padding-top: 10px;">
                    <em>{{ lang._('Table updates automatically when records change (polling every 5 seconds).') }}</em>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="settingsChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
            <button class="btn btn-primary" id="saveAct" type="button">
                <b>{{ lang._('Save') }}</b> <i id="saveAct_progress" class=""></i>
            </button>
            <button class="btn btn-primary" id="applyAct"
                    data-endpoint="/api/dnsmasqtounbound/service/reconfigure"
                    data-label="{{ lang._('Apply') }}"
                    data-service-widget="dnsmasqtounbound"
                    type="button">
                {{ lang._('Apply') }}
            </button>
            <br/><br/>
        </div>
    </div>
</section>
