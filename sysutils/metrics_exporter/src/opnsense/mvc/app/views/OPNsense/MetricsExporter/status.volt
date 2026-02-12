<script>
    $(document).ready(function() {
        updateServiceControlUI('metricsexporter');

        function escapeHtml(str) {
            return $('<span>').text(str).html();
        }

        function loadStatus() {
            $("#btnRefreshProgress").addClass("fa-spinner fa-pulse");
            ajaxCall("/api/metricsexporter/status/collector", {}, function(data, status) {
                $("#collectorsContent").empty();
                if (status === "success" && data['collectors'] !== undefined) {
                    if (data['collectors'].length === 0) {
                        $("#collectorsContent").html(
                            '<p class="text-muted">{{ lang._("No collectors are enabled. Enable collectors in Settings.") }}</p>'
                        );
                    } else {
                        $.each(data['collectors'], function(idx, collector) {
                            var html = '<h3>' + escapeHtml(collector.name) + '</h3>';
                            if (collector.metrics) {
                                html += '<pre style="font-size: 12px; max-height: 500px; overflow-y: auto;">' +
                                    escapeHtml(collector.metrics) + '</pre>';
                            } else {
                                html += '<p class="text-muted">{{ lang._("No metrics available.") }}</p>';
                            }
                            $("#collectorsContent").append(html);
                        });
                    }
                    if (data['node_exporter_installed'] === false) {
                        $("#node_exporter_warning").show();
                    }
                } else {
                    $("#collectorsContent").html(
                        '<p>{{ lang._("Unable to fetch collector status. Is the exporter running?") }}</p>'
                    );
                }
                $("#btnRefreshProgress").removeClass("fa-spinner fa-pulse");
            });
        }

        $("#btnRefresh").click(function(event) {
            event.preventDefault();
            loadStatus();
        });

        loadStatus();
    });
</script>

<div class="alert alert-warning" role="alert" id="node_exporter_warning" style="display:none;">
    <b>{{ lang._('Warning:') }}</b>
    {{ lang._('The Prometheus Exporter plugin (os-node_exporter) is not installed. The metrics exporter writes metrics to the node_exporter textfile collector directory, which requires os-node_exporter to be installed and enabled.') }}
</div>

<div class="content-box">
    <div id="collectorsContent">
    </div>
    <div class="pull-right" style="padding: 10px;">
        <button class="btn btn-primary" id="btnRefresh" type="button">
            <b>{{ lang._('Refresh') }}</b>
            <span id="btnRefreshProgress" class="fa fa-refresh"></span>
        </button>
    </div>
    <div class="clearfix"></div>
</div>
