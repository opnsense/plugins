<script>
$(document).ready(function () {
    function renderSummary(summary) {
        $('#summaryInterfaces').text(summary.interfaces || 0);
        $('#summaryHosts').text(summary.hosts || 0);
        $('#summaryNeighbors').text(summary.neighbors || 0);
        $('#summaryNodes').text(summary.nodes || 0);
        $('#summaryLinks').text(summary.links || 0);
        $('#summaryGeoPoints').text(summary.geoPoints || 0);
    }

    function renderTable(selector, rows, cols) {
        var html = '<table class="table table-striped __nomb"><thead><tr>';
        for (var i = 0; i < cols.length; i++) {
            html += '<th>' + cols[i].label + '</th>';
        }
        html += '</tr></thead><tbody>';

        for (var r = 0; r < rows.length; r++) {
            html += '<tr>';
            for (var c = 0; c < cols.length; c++) {
                var key = cols[c].key;
                var value = rows[r][key] || '';
                html += '<td>' + $('<div/>').text(value).html() + '</td>';
            }
            html += '</tr>';
        }

        html += '</tbody></table>';
        $(selector).html(html);
    }

    function loadData() {
        ajaxCall('/api/topologymap/service/discover', {}, function (data, status) {
            if (status !== 'success' || data['status'] !== 'ok') {
                $('#responseMsg').removeClass('hidden alert-info').addClass('alert-danger').html(data['message'] || '{{ lang._('Unable to load topology data.') }}');
                return;
            }

            $('#responseMsg').addClass('hidden').removeClass('alert-danger').html('');
            var summary = data['summary'] || {};
            summary.geoPoints = (data['meta'] && data['meta']['geo_points']) ? data['meta']['geo_points'] : 0;
            renderSummary(summary);

            var nodes = (data['topology'] && data['topology']['nodes']) ? data['topology']['nodes'] : [];
            var links = (data['topology'] && data['topology']['links']) ? data['topology']['links'] : [];

            renderTable('#nodesTable', nodes, [
                {key: 'label', label: '{{ lang._('Node') }}'},
                {key: 'type', label: '{{ lang._('Type') }}'},
                {key: 'ip', label: '{{ lang._('IP') }}'},
                {key: 'mac', label: '{{ lang._('MAC') }}'},
                {key: 'source', label: '{{ lang._('Source') }}'}
            ]);

            renderTable('#linksTable', links, [
                {key: 'from', label: '{{ lang._('From') }}'},
                {key: 'to', label: '{{ lang._('To') }}'},
                {key: 'type', label: '{{ lang._('Type') }}'}
            ]);
        });
    }

    mapDataToFormUI({'frm_topologymap': '/api/topologymap/settings/get'});

    $('#saveAct').click(function () {
        saveFormToEndpoint('/api/topologymap/settings/set', 'frm_topologymap', function () {
            $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html('{{ lang._('Settings saved.') }}');
            loadData();
        });
    });

    $('#refreshAct').click(function () {
        loadData();
    });

    loadData();
});
</script>

<div class="alert alert-info" role="alert">
    {{ lang._('Automatic topology mapping using LLDP, ARP and NDP discovery. Geo map output is available for future dashboard/map widgets.') }}
</div>

<div class="alert hidden" role="alert" id="responseMsg"></div>

<div class="row">
    <div class="col-md-12">
        <div class="content-box tab-content table-responsive">
            <table class="table table-striped __nomb" style="margin-bottom:0">
                <tr><th class="listtopic">{{ lang._('Discovery Summary') }}</th></tr>
                <tr>
                    <td>
                        <strong>{{ lang._('Interfaces') }}:</strong> <span id="summaryInterfaces">0</span> |
                        <strong>{{ lang._('Hosts') }}:</strong> <span id="summaryHosts">0</span> |
                        <strong>{{ lang._('LLDP Neighbors') }}:</strong> <span id="summaryNeighbors">0</span> |
                        <strong>{{ lang._('Nodes') }}:</strong> <span id="summaryNodes">0</span> |
                        <strong>{{ lang._('Links') }}:</strong> <span id="summaryLinks">0</span> |
                        <strong>{{ lang._('Geo Points') }}:</strong> <span id="summaryGeoPoints">0</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="col-md-12" style="margin-top: 10px;">
    {{ partial("layout_partials/base_form",['fields':settings,'id':'frm_topologymap'])}}
</div>

<div class="col-md-12" style="margin-top: 10px;">
    <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
    <button class="btn btn-default" id="refreshAct" type="button"><b>{{ lang._('Refresh Discovery') }}</b></button>
</div>

<div class="row" style="margin-top: 10px;">
    <div class="col-md-6">
        <div class="content-box tab-content table-responsive">
            <table class="table table-striped __nomb" style="margin-bottom:0">
                <tr><th class="listtopic">{{ lang._('Nodes') }}</th></tr>
                <tr><td id="nodesTable"></td></tr>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="content-box tab-content table-responsive">
            <table class="table table-striped __nomb" style="margin-bottom:0">
                <tr><th class="listtopic">{{ lang._('Links') }}</th></tr>
                <tr><td id="linksTable"></td></tr>
            </table>
        </div>
    </div>
</div>
