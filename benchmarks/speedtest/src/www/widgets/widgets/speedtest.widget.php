<script>
    $(window).on("load", function() {
        ajaxCall("/api/speedtest/service/showstat", {}, function(l,status) {
            $('#stat_samples').html("<b>"+l.samples+"<\/b>")
            $('#stat_latency').html("<b>"+l.latency.avg+" ms<\/b><small> (min: "+l.latency.min+" ms, max: "+l.latency.max +" ms)</small>")
            $('#stat_download').html("<b>"+l.download.avg+" Mbps<\/b><small> (min: "+l.download.min+" Mbps, max: "+l.download.max +" Mbps)</small>")
            $('#stat_upload').html("<b>"+l.upload.avg+" Mbps<\/b><small> (min: "+l.upload.min+" Mbps, max: "+l.upload.max +" Mbps)</small>")
        });
    });
</script>
<table id="speedtest_widget_table" class="table table-striped table-condensed">
  <tr><td style="width:25%">Avg Latency:</td><td><div id="stat_latency">0.00 ms (min: 0.00 ms, max: 0.00 ms)</div></td></tr>
  <tr><td>Avg Download:</td><td><div id="stat_download">0 Mbps (min: 0 Mbps, max: 0 Mbps)</div></td></tr>
  <tr><td>Avg Upload:</td><td><div id="stat_upload">0 Mbps (min: 0 Mbps, max: 0 Mbps)</div></td></tr>
</table>
