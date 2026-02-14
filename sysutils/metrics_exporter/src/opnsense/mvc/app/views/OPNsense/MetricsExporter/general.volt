<script>
    $(document).ready(function() {
        var data_get_map = {'frm_general_settings':"/api/metricsexporter/general/get"};
        mapDataToFormUI(data_get_map).done(function(data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        updateServiceControlUI('metricsexporter');

        // Load available collectors and render checkboxes
        ajaxCall("/api/metricsexporter/general/collectors", {}, function(data, status) {
            if (status === "success" && data['collectors']) {
                var tbody = $("#collectorsTableBody");
                tbody.empty();
                $.each(data['collectors'], function(idx, col) {
                    var checked = col.enabled ? ' checked="checked"' : '';
                    var row = '<tr>' +
                        '<td>' +
                        '<div class="control-label">' +
                        '<i class="fa fa-info-circle text-muted"></i> ' +
                        '<b>' + $('<span>').text(col.name).html() + '</b>' +
                        '</div>' +
                        '</td>' +
                        '<td>' +
                        '<input type="checkbox" class="collector-toggle" ' +
                        'data-type="' + $('<span>').text(col.type).html() + '"' +
                        checked + ' />' +
                        '</td>' +
                        '<td></td>' +
                        '</tr>';
                    tbody.append(row);
                });
            }
        });

        ajaxCall("/api/metricsexporter/status/collector", {}, function(data, status) {
            if (status === "success" && data['node_exporter_installed'] === false) {
                $("#node_exporter_warning").show();
            }
        });

        $("#saveAct").click(function() {
            saveFormToEndpoint("/api/metricsexporter/general/set", 'frm_general_settings', function() {
                // Gather collector states
                var collectors = {};
                $(".collector-toggle").each(function() {
                    var type = $(this).data('type');
                    collectors[type] = $(this).is(':checked') ? 1 : 0;
                });

                // Save collector states
                ajaxCall("/api/metricsexporter/general/saveCollectors", {'collectors': collectors}, function() {
                    $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall("/api/metricsexporter/service/reconfigure", {}, function(data, status) {
                        updateServiceControlUI('metricsexporter');
                        $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
                });
            });
        });
    });
</script>

<div class="alert alert-warning" role="alert" id="node_exporter_warning" style="display:none;">
    <b>{{ lang._('Warning:') }}</b>
    {{ lang._('The Prometheus Exporter plugin (os-node_exporter) is not installed. The metrics exporter writes metrics to the node_exporter textfile collector directory, which requires os-node_exporter to be installed and enabled.') }}
</div>

<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form", ['fields':generalForm,'id':'frm_general_settings']) }}

    <div class="table-responsive">
        <table class="table table-striped table-condensed" style="table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 25%;" />
                <col style="width: 40%;" />
                <col style="width: 35%;" />
            </colgroup>
            <thead style="cursor: pointer;">
                <tr>
                    <th colspan="3">
                        <div style="padding-bottom: 5px; padding-top: 5px; font-size: 16px;">
                            <i class="fa fa-angle-down" aria-hidden="true"></i>
                            &nbsp;
                            <b>{{ lang._('Collectors') }}</b>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="collectorsTableBody">
                <tr><td colspan="3" class="text-muted">{{ lang._('Loading collectors...') }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
    </div>
</div>
