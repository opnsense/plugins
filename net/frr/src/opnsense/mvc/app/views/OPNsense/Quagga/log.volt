<div class="content-box">
<table id="logtable" class="table table-condensed table-hover table-striped" style="table-layout: initial;">
    <thead>
        <tr>
            <th data-column-id="date" data-sortable="false" data-type="string">{{ lang._('Date') }}</th>
            <th data-column-id="time" data-sortable="false" data-type="string">{{ lang._('Time') }}</th>
            <th data-column-id="service" data-sortable="false" data-type="string">{{ lang._('Service') }}</th>
            <th data-column-id="message" data-sortable="false" data-type="string">{{ lang._('Message') }}</th>
        </tr>
    </thead>
</table>
</div>

<script>
$("#logtable").bootgrid({
    ajax: true,
    navigation: 0,
    url: "/api/quagga/diagnostics/log",
    ajaxSettings: { "method": "GET", cache: true },
    responseHandler: function(resp) { return {"rows": resp}; },
    sortable: false
});
</script>
