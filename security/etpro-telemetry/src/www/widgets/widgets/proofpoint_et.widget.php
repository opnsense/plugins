<?php
/*
    Copyright (C) 2018-2019 Deciso B.V.
    All rights reserved.
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/
?>

<style>
  .proofpoint_logo {
      height: 30px;
      vertical-align: middle;
  }
  .proofpoint_status_div {
      padding: 2px;
      border: 2px solid black;
      white-space: nowrap;
      display: inline;
  }
</style>
<script src="/ui/js/moment-with-locales.min.js"></script>
<script>
    $(window).load(function() {
        ajaxGet("/api/diagnostics/proofpoint_et/status", {}, function(data){
            $("#proofpoint_status").removeClass("fa-spin");
            $("#proofpoint_status").removeClass("fa-spinner");
            if (data.status == 'ok' && data.sensor_status == 'ACTIVE') {
                $("#proofpoint_status").addClass("fa-check");
            } else {
                $("#proofpoint_status").addClass("fa-close");
            }

            if (data.status == 'ok') {
                var props = {
                    sensor_status: "<?=html_safe(gettext('Status'));?>",
                    event_received: "<?=html_safe(gettext('Last event'));?>",
                    last_rule_download: "<?=html_safe(gettext('Last rule download'));?>",
                    last_heartbeat: "<?=html_safe(gettext('Last heartbeat'));?>"
                };

                $("#proofpoint_status_table > tbody").empty();
                for (var idx in props) {
                    if (idx === 'sensor_status') {
                        var content = data[idx];
                    } else {
                        var content = moment(data[idx]).format('ddd MMM D HH:mm:ss ZZ YYYY')
                    }
                    $("#proofpoint_status_table > tbody").append(
                      $("<tr/>")
                        .append($("<td style='width:30%'/>").text(props[idx]))
                        .append($("<td/>").text(content))
                    );
                }
            }
        });
    });
</script>

<table class="table table-striped table-condensed" id="proofpoint_status_table">
  <thead>
      <tr>
          <th colspan="2">
            <img src="/ui/img/proofpoint.svg" class="proofpoint_logo image_invertible">
            <div class="proofpoint_status_div pull-right">
              <i class="fa fa-spinner fa-2x fa-spin" style="vertical-align: middle;" id="proofpoint_status"></i>
            </div>
          </th>
      </tr>
  </thead>
  <tbody>
  </tbody>
  <tfoot>
      <tr>
          <td colspan="2">
            <?=gettext("For more information, please visit");?>
            <a target="_blank" rel="noopener noreferrer" href="https://docs.opnsense.org/manual/etpro_telemetry.html">docs.opnsense.org</a>
          </td>
      </tr>
  </tfoot>
</table>
