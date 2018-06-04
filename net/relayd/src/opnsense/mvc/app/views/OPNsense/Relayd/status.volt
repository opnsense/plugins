{#

Copyright © 2018 by EURO-LOG AG
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script type="text/javascript">
   $( document ).ready(function() {
      updateServiceControlUI('relayd');
      // get status and build the table body
      $('#btnRefresh').unbind('click').click(function() {
         ajaxCall(url = "/api/relayd/status/sum", sendData={}, callback = function(result, status) {
            if (status == "success" && result.result === 'ok') {
               $('#tableStatus > tbody').empty();
               $('#tableStatus > tbody').attr('style', 'display:none;');
               /* create a table row for each host and combine
                  virtualserver/table fields afterwards via rowspan */
               $.each(result.rows, function (vkey, virtualserver) {
                  var vrowspan = 0;
                  var trowspan = [];
                  var html = '<tr class="vrow"><td id="virtualServer' + vkey + '">';
                  html += getControlButtons(virtualserver.status, virtualserver.id, virtualserver.type);
                  html += virtualserver.name + ' (' + virtualserver.type + '): ' + virtualserver.status + '</td>';
                  var tfirst = true;
                  $.each(virtualserver.tables, function(tkey, table) {
                     trowspan[tkey] = 0;
                     if (tfirst == true) {
                        tfirst = false;
                     } else {
                        html += '<tr>';
                     }
                     html += '<td id="table' + tkey + '">';
                     html += getControlButtons(table.status, tkey, 'table');
                     html += table.name + ' ' + table.status + '</td>';
                     var hfirst = true;
                     $.each(table.hosts, function(hkey, host) {
                        if (hfirst == true) {
                           hfirst = false;
                        } else {
                           html += '<tr>';
                        }
                        vrowspan++;
                        trowspan[tkey]++;
                        html += '<td>';
                        html += getControlButtons(host.status, hkey, 'host');
                        html += host.name + ' ' + host.status + '</td></tr>';
                     });
                     // dummy host for disabled tables
                     if (hfirst == true) {
                        vrowspan++;
                        html += '<td></td>';
                     }
                  });

                  $('#tableStatus > tbody').append(html);
                  $('#virtualServer' + vkey).attr('rowspan', vrowspan);
                  $.each(trowspan, function(tid, trowspan) {
                     $('#table' + tid).attr('rowspan', trowspan);
                  });
                  $('#tableStatus > tbody > tr.vrow > td').css('border-top', '2px solid #ddd');
                  $('#tableStatus > tbody').fadeIn();
               });
            } else {
               $("#tableStatus").html("<tr><td><br/>{{ lang._('The status could not be fetched. Is Relayd running?') }}</td></tr>");
            }
            $('#btnRefresh').blur();
         });
      });

      // initial load
      $("#btnRefresh").click();

   });

   // create status and start/stop buttons
   function getControlButtons(status, id, nodeType) {
       var statusClass = "btn btn-xs glyphicon ";
       var controlClass = "btn btn-xs btn-default glyphicon ";
       var action;

       if (status.substring(0, 6) === 'active' || status === 'up') {
          statusClass += "btn-success glyphicon-play";
          controlClass += "glyphicon-stop";
          controlTitle = "Stop this " + nodeType;
          action = 'onclick="toggleNode(\'' + nodeType + '\', ' + id + ', \'disable\')""';
       } else if (status === 'disabled') {
          statusClass += "btn-danger glyphicon-stop";
          controlClass += "glyphicon-play";
          controlTitle = "Start this " + nodeType;
          action = 'onclick="toggleNode(\'' + nodeType + '\', ' + id + ', \'enable\')"';
       } else {
          statusClass += "btn-danger glyphicon-stop";
          controlClass += "glyphicon-play";
          action = 'disabled="disabled"';
       }
       // no action for relays; see relayctl(8)
       if (nodeType === 'relay') {
          action = 'disabled="disabled"';
       }
       var html = '<span class="' + statusClass + '" style="cursor: default;"> </span>&nbsp;';
       html += '<span ' + action + ' class="' + controlClass + '" title="' + controlTitle + '"> </span>&nbsp;';
       return html;
    };

    // enable/disable redirects, tables or hosts
    function toggleNode(nodeType, id, action) {
        ajaxCall(url = "/api/relayd/status/toggle/" + nodeType + "/" + id + "/" + action, callback = function(result, status) {
           $("#btnRefresh").click();
        });
     };

</script>

<div class="content-box">
   <table id="tableStatus" class="table table-condensed">
      <thead><tr><th>{{ lang._('Virtual Server') }}</th><th>{{ lang._('Table') }}</th><th>{{ lang._('Host') }}</th></tr></thead>
      <tbody style="display:none;"></tbody>
   </table>
   <div  class="col-sm-12">
      <div class="row">
         <table class="table">
            <tr>
               <td>
                  <div class="pull-right">
                     <button class="btn btn-primary" id="btnRefresh" type="button"><b>{{ lang._('Refresh') }}</b> <span id="btnRefreshProgress" class="fa fa-refresh"> </span></button>
                  </div>
               </td>
            </tr>
         </table>
      </div>
   </div>
</div>
