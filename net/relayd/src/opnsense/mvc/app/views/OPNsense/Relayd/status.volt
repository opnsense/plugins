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
          let action_btn = $('<button class="btn btn-xs btn-default" data-toggle="tooltip"/>').attr('data-nodeid', id).attr('data-nodetype', nodeType);
          let action_btn_remove = action_btn.clone(true);
          let response = [];

          if (status.substring(0, 6) === 'active' || status === 'up') {
              status_btn.addClass('label-success').append($('<i class="fa fa-play fa-fw"/>'));
              action_btn.addClass('node_action').append($('<i class="fa fa-stop fa-fw"/>')).attr('data-nodeaction', 'disable');
              action_btn.attr('title', $("#stop_" + nodeType + "_label").text());
              response.push(status_btn);
              if (nodeType === 'host') {
                  action_btn_remove.addClass('node_action').append($('<i class="fa fa-times fa-fw"/>')).attr('data-nodeaction', 'remove');
                  action_btn_remove.attr('title', $("#remove_host_label").text());
                  response.push(action_btn_remove);
              }
              response.push(action_btn);
          } else if (['stopped', 'disabled'].includes(status)) {
              status_btn.addClass('label-danger').append($('<i class="fa fa-stop fa-fw"/>'));
              action_btn.addClass('node_action').append($('<i class="fa fa-play fa-fw"/>')).attr('data-nodeaction', 'enable');
              action_btn.attr('title', $("#start_" + nodeType + "_label").text());
              if (status === 'stopped' && nodeType === 'host') {
                 action_btn_remove.addClass('node_action').append($('<i class="fa fa-times fa-fw"/>')).attr('data-nodeaction', 'remove');
                 action_btn_remove.attr('title', $("#remove_host_label").text());
                 response.push(status_btn, action_btn_remove, action_btn);
              } else {
                 response.push(status_btn, action_btn);
              }
          } else if (status === 'down' && nodeType == 'host') {
              // remove host (permanent down)
              status_btn.addClass('label-danger').append($('<i class="fa fa-stop fa-fw"/>'));
              action_btn.addClass('node_action').append($('<i class="fa fa-times fa-fw"/>')).attr('data-nodeaction', 'remove');
              action_btn.attr('title', $("#remove_host_label").text());
              response.push(status_btn, action_btn);
          } else {
              status_btn.addClass('label-danger').append($('<i class="fa fa-stop fa-fw"/>'));
              action_btn.append($('<i class="fa fa-play fa-fw"/>')).attr('disabled', 'disabled');
              response.push(status_btn, action_btn);
          }
          // no action for relays; see relayctl(8)
          if (nodeType === 'relay') {
              action_btn.removeClass('node_action').attr('disabled', 'disabled');
          }
          return response;
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
              if (filter_vs !== "" && vs_td.data('display_name') !== undefined && vs_td.data('display_name').toUpperCase().indexOf(filter_vs.toUpperCase()) == -1) {
                  is_visible_row=false;
              } else if (filter_table !== "" && tbl_td.data('display_name') !== undefined && tbl_td.data('display_name').toUpperCase().indexOf(filter_table.toUpperCase()) == -1) {
                  is_visible_row=false;
              } else if (filter_host !== "" && host_td.data('display_name') !== undefined && host_td.data('display_name').toUpperCase().indexOf(filter_host.toUpperCase()) == -1) {
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
                    let $vs_td = $('<td data-id="'+vkey+'" class="relayd_virtualserver"/>');
                    let listen_str = "";
                    if (virtualserver.listen_address !== undefined) {
                        listen_str = " [" + virtualserver.listen_address + ":" + virtualserver.listen_startport +"] "
                    }
                    $vs_td.append(
                        $('<div class="object_container"/>').append(
                            getControlButtons(virtualserver.status, virtualserver.id, virtualserver.type),
                            virtualserver.name,
                            listen_str,
                            ' (' + virtualserver.type + '): ',
                            virtualserver.status
                        ).data('payload', virtualserver).data('type', 'virtualserver')
                    );
                    $vs_td.data('display_name', virtualserver.name + listen_str);
                    if (virtualserver.tables) {
                        $.each(virtualserver.tables, function(tkey, table) {
                            let $tbl_td = $('<td data-id="'+tkey+'" class="relayd_table"/>');
                            $tbl_td.data('display_name', table.name);
                            $tbl_td.append(
                                $('<div class="object_container"/>').append(
                                    getControlButtons(table.status, tkey, 'table'),
                                    table.name + ' ' + table.status
                                ).data('payload', table).data('type', 'table')
                            );
                            if (table.hosts) {
                                $.each(table.hosts, function(hkey, host) {
                                    let $host_td = $('<td data-id="'+hkey+'" class="relayd_host"/>');
                                    let host_names = [];
                                    let display_name = host.name ;
                                    if (host.properties != undefined) {
                                        for (i=0; i < host.properties.length; i++) {
                                            if (host.properties[i].name !== host.name) {
                                                host_names.push(host.properties[i].name);
                                            }
                                        }
                                        if (host_names.length > 0) {
                                            display_name = display_name + ' [' + host_names.join(',') + '] ';
                                        }
                                    }
                                    $host_td.append(
                                        $('<div class="object_container"/>').append(
                                            getControlButtons(host.status, hkey, 'host'),
                                            display_name,
                                            " ",
                                            host.status
                                        ).data('payload', host).data('type', 'host')
                                    );
                                    $host_td.data('display_name', display_name);
                                    $('#tableStatus > tbody').append(
                                        $("<tr/>").append(
                                            $vs_td.clone(true),
                                            $tbl_td.clone(true),
                                            $host_td
                                        )
                                    );
                                });
                            } else {
                                $('#tableStatus > tbody').append(
                                    $("<tr/>").append(
                                        $vs_td.clone(true),
                                        $tbl_td.clone(true),
                                        $("<td/>")
                                    )
                                );
                            }
                        });
                    } else {
                        $('#tableStatus > tbody').append(
                            $("<tr/>").append(
                                $vs_td.clone(true),
                                $("<td/>"),
                                $("<td/>")
                              )
                           );
                    }
                });
                $('[data-toggle="tooltip"]').tooltip();
                apply_filters();
                // bind node actions
                $(".node_action").click(function(){
                    let container = $(this).closest("div.object_container");
                    let item_payload = container.data('payload');
                    let action = $(this).data('nodeaction');
                    let nodeid = $(this).data('nodeid');
                    let nodetype = $(this).data('nodetype');
                    let host_uuids = [];
                    let host_enabled = false;
                    if (item_payload.properties != undefined) {
                        for (i=0; i < item_payload.properties.length; i++) {
                            host_uuids.push(item_payload.properties[i].uuid);
                            if (item_payload.properties[i].enabled === "1") {
                                host_enabled = true;
                            }
                        }
                    }
                    if (action === "remove" && nodetype == 'host') {
                        if (item_payload !== undefined) {
                            stdDialogConfirm(
                                '{{ lang._('Relayd') }}',
                                $("#remove_host_message").text().trim(),
                                '{{ lang._('Yes') }}',
                                '{{ lang._('No') }}',
                                function () {
                                    if (host_uuids.length > 0) {
                                        ajaxCall("/api/relayd/status/toggle/host/" + host_uuids.join(',') + "/remove", {}, function(result, status) {
                                            $("#btnRefresh").click();
                                        });
                                    }
                                }
                            );
                        }
                    } else if (action === "enable" && nodetype == 'host' && !host_enabled) {
                        stdDialogConfirm(
                            '{{ lang._('Relayd') }}',
                            $("#add_host_message").text().trim(),
                            '{{ lang._('Yes') }}',
                            '{{ lang._('No') }}',
                            function () {
                                if (item_payload.properties != undefined) {
                                    if (host_uuids.length > 0) {
                                      ajaxCall("/api/relayd/status/toggle/host/" + host_uuids.join(',') + "/add", {}, function(result, status) {
                                          $("#btnRefresh").click();
                                      });
                                    }
                                }
                            }
                        );
                    } else {
                        // default action, parameters for relayctl
                        ajaxCall("/api/relayd/status/toggle/" + nodetype + "/" + nodeid + "/" +  action, {}, function(result, status) {
                            $("#btnRefresh").click();
                        });
                    }
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

<div style="display:none" id="tooltips">
    <!--
      dynamic labels to ease translations
    -->
    <label id="stop_host_label">{{ lang._('Stop this host') }}</label>
    <label id="stop_table_label">{{ lang._('Stop this table') }}</label>
    <label id="stop_relay_label">{{ lang._('Stop this relay') }}</label>
    <label id="stop_redirect_label">{{ lang._('Stop this redirect') }}</label>
    <label id="start_host_label">{{ lang._('Start this host') }}</label>
    <label id="start_table_label">{{ lang._('Start this table') }}</label>
    <label id="start_relay_label">{{ lang._('Start this relay') }}</label>
    <label id="start_redirect_label">{{ lang._('Start this redirect') }}</label>
    <label id="remove_host_label">{{ lang._('Disable this host') }}</label>
    <label id="remove_host_message">{{ lang._('
      Are you sure you do want to disable this host?
      When being used in other virtual servers it will be disabled in there as well.') }}
    </label>
    <label id="add_host_message">{{ lang._('
      Are you sure you do want to enable this host?
      When being used in other virtual servers it will be enabled in there as well.') }}
    </label>
</div>

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
