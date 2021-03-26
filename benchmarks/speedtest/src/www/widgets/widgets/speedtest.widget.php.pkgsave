<script>
    function stat_reload(){
        ajaxCall(url="/api/speedtest/service/stat", sendData={}, callback=function(data,status) {
            let l = JSON.parse(data['response'])
            $('#stat_samples').html("<b>"+l.samples+"<\/b>")
            $('#stat_latency').html("<b>"+l.latency.avg+" ms<\/b><small> (min: "+l.latency.min+" ms, max: "+l.latency.max +" ms)</small>")
            $('#stat_download').html("<b>"+l.download.avg+" Mbps<\/b><small> (min: "+l.download.min+" Mbps, max: "+l.download.max +" Mbps)</small>")
            $('#stat_upload').html("<b>"+l.upload.avg+" Mbps<\/b><small> (min: "+l.upload.min+" Mbps, max: "+l.upload.max +" Mbps)</small>")
        });
    };
    $(window).on("load", function() {
        stat_reload();
    });
</script>

<!-- gateway table -->
<table id="speedtest_widget_table" class="table table-striped table-condensed">
  <tr>
      <td>Avg Latency:</td>
      <td><div id="stat_latency">0.00 ms (min: 0.00 ms, max: 0.00 ms)</div>
      </td>
  </tr>
  <tr>
      <td>Avg Download:</td>
      <td><div id="stat_download">0 Mbps (min: 0 Mbps, max: 0 Mbps)</div>
      </td>
  </tr>
  <tr>
      <td>Avg Upload:</td>
      <td><div id="stat_upload">0 Mbps (min: 0 Mbps, max: 0 Mbps)</div>
        </td>
  </tr>
</table>
