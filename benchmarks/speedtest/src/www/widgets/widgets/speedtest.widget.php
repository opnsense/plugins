<?php
/*
 * Copyright 2021 Miha Kralj
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
?>

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
