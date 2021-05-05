{#

Copyright © 2018 by EURO-LOG AG
Copyright (c) 2021 Deciso B.V.
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
      /**
       * create status and start/stop buttons
       **/
      function getControlButtons(status, id, nodeType) {
          let status_btn = $('<button class="label label-opnsense label-opnsense-xs"/>');
          let action_btn = $('<button class="btn btn-xs btn-default"/>').attr('data-nodeid', id).attr('data-nodetype', nodeType);

          if (status.substring(0, 6) === 'active' || status === 'up') {
             status_btn.addClass('label-success').append($('<i class="fa fa-play fa-fw"/>'));
             action_btn.addClass('node_action').append($('<i class="fa fa-stop fa-fw"/>')).attr('data-nodeaction', 'disable');
          } else if (status === 'disabled') {
             status_btn.addClass('label-danger').append($('<i class="fa fa-stop fa-fw"/>'));
             action_btn.addClass('node_action').append($('<i class="fa fa-play fa-fw"/>')).attr('data-nodeaction', 'enable');
          } else {
             status_btn.addClass('label-danger').append($('<i class="fa fa-stop fa-fw"/>'));
             action_btn.append($('<i class="fa fa-play fa-fw"/>')).attr('disabled', 'disabled');
          }
          // no action for relays; see relayctl(8)
          if (nodeType === 'relay') {
             action_btn.removeClass('node_action').attr('disabled', 'disabled');
          }
          return [
              status_btn, action_btn
          ];
      };

      /**
       * apply provided filters on selected data, reformat virtual server and table groups with borders
       */
      function apply_filters() {
          let prev_tr = null;
          let filter_vs = $("#filter_virtualserver").val();
          let filter_table = $("#filter_table").val();
          let filter_host = $("#filter_host").val();

          $('#tableStatus > tbody > tr').each(function(){
              let vs_td = $(this).find("td.relayd_virtualserver");
              let tbl_td = $(this).find("td.relayd_table");
              let host_td = $(this).find("td.relayd_host");
              let is_visible_row = true;

              // filter;
              if (filter_vs !== "" && vs_td.data("name") !== undefined && vs_td.data("name").toUpperCase().indexOf(filter_vs.toUpperCase()) == -1) {
                  is_visible_row=false;
              } else if (filter_table !== "" && tbl_td.data("name") !== undefined && tbl_td.data("name").toUpperCase().indexOf(filter_table.toUpperCase()) == -1) {
                  is_visible_row=false;
              } else if (filter_host !== "" && host_td.data("name") !== undefined && host_td.data("name").toUpperCase().indexOf(filter_host.toUpperCase()) == -1) {
                  is_visible_row=false;
              }
              if (is_visible_row) {
                  $(this).show();
                  if (prev_tr !== null && prev_tr.find("td.relayd_virtualserver").data('id') === vs_td.data('id')){
                      vs_td.find("div.object_container").hide();
                      vs_td.css('border-top-style', 'hidden');
                  } else {
                      vs_td.find("div.object_container").show();
                      vs_td.css('border-top-style', 'solid');
                  }

                  if (prev_tr !== null && prev_tr.find("td.relayd_table").data('id') === tbl_td.data('id')){
                      tbl_td.find("div.object_container").hide();
                      tbl_td.css('border-top-style', 'hidden');
                  } else {
                      tbl_td.find("div.object_container").show();
                      tbl_td.css('border-top-style', 'solid');
                  }
                  prev_tr = $(this);
              } else {
                  $(this).hide();
              }
          });
      }

      /**
       * bind form events
       */
      updateServiceControlUI('relayd');

      // get status and build the table body
      $('#btnRefresh').click(function(event) {
        event.preventDefault();
        if ($("#btnRefreshProgress").hasClass("fa-spinner")) {
            return;
        }
        // do not wait for relayctl output on consecutive calls
        let api_wait = $('#tableStatus > tbody > tr').length > 0 ? 1 : 0;
        $("#btnRefreshProgress").addClass("fa-spinner fa-pulse");
        ajaxCall("/api/relayd/status/sum/"+api_wait, {}, function(result, status) {
            $('#tableStatus > tbody').empty();
            if (status == "success" && result.result === 'ok') {
                /* create a table row for each host and combine
                  virtualserver/table fields afterwards (hide repeating items and align borders accordingly) */
                $.each(result.rows, function (vkey, virtualserver) {
                    let $vs_td = $('<td data-id="'+vkey+'" data-name="'+virtualserver.name+'" class="relayd_virtualserver"/>');
                    $vs_td.append(
                        $('<div class="object_container"/>').append(
                            getControlButtons(virtualserver.status, virtualserver.id, virtualserver.type),
                            virtualserver.name + ' (' + virtualserver.type + '): ' + virtualserver.status
                        )
                    );
                    if (virtualserver.tables) {
                        $.each(virtualserver.tables, function(tkey, table) {
                            let $tbl_td = $('<td data-id="'+tkey+'" data-name="'+table.name+'" class="relayd_table"/>');
                            $tbl_td.append(
                                $('<div class="object_container"/>').append(
                                    getControlButtons(table.status, tkey, 'table'),
                                    table.name + ' ' + table.status
                                )
                            );
                            if (table.hosts) {
                                $.each(table.hosts, function(hkey, host) {
                                    let $host_td = $('<td data-id="'+hkey+'" data-name="'+host.name+'" class="relayd_host"/>');
                                    $host_td.append(
                                        $('<div class="object_container"/>').append(
                                            getControlButtons(host.status, hkey, 'host'),
                                            host.name + ' ' + host.status
                                        )
                                    );
                                    $('#tableStatus > tbody').append(
                                        $("<tr/>").append(
                                            $vs_td.clone(),
                                            $tbl_td.clone(),
                                            $host_td
                                        )
                                    );
                                });
                            } else {
                                $('#tableStatus > tbody').append(
                                    $("<tr/>").append(
                                        $vs_td.clone(),
                                        $tbl_td.clone(),
                                        $("<td/>")
                                    )
                                );
                            }
                        });
                    } else {
                        $('#tableStatus > tbody').append(
                            $("<tr/>").append(
                                $vs_td.clone(),
                                $("<td/>"),
                                $("<td/>")
                              )
                           );
                    }
                });
                apply_filters();
                $(".node_action").click(function(){
                    ajaxCall("/api/relayd/status/toggle/" + $(this).data('nodetype') + "/" + $(this).data('nodeid') + "/" +  $(this).data('nodeaction'), {}, function(result, status) {
                        $("#btnRefresh").click();
                    });
                });
            } else {
               $("#tableStatus").html("<tr><td><br/>{{ lang._('The status could not be fetched. Is Relayd running?') }}</td></tr>");
            }
            $("#btnRefreshProgress").removeClass("fa-spinner fa-pulse");
         });
      });
      $(".filter_item").keyup(apply_filters);

      // initial load
      $("#btnRefresh").click();

   });

</script>

<div class="content-box">
   <table id="tableStatus" class="table table-condensed">
      <thead>
          <tr>
              <th>{{ lang._('Virtual Server') }}</th>
              <th>{{ lang._('Table') }}</th>
              <th>{{ lang._('Host') }}</th>
          </tr>
          <tr>
              <th><input type="text" id="filter_virtualserver" class="input-sm filter_item" autocomplete="off"></th>
              <th><input type="text" id="filter_table" class="input-sm filter_item" autocomplete="off"></th>
              <th><input type="text" id="filter_host" class="input-sm filter_item" autocomplete="off"></th>
          </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
           <td colspan="3">
              <div class="pull-right">
                 <button class="btn btn-primary" id="btnRefresh" type="button"><b>{{ lang._('Refresh') }}</b>
                   <span id="btnRefreshProgress" class="fa fa-refresh"> </span>
                 </button>
              </div>
           </td>
        </tr>
      </tfoot>
   </table>
</div>
