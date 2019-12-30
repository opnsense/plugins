<script>

function show_wake_result(data) {
  BootstrapDialog.show({
    type: data.status == 'OK' ? BootstrapDialog.TYPE_SUCCESS : BootstrapDialog.TYPE_DANGER,
    title: "{{ lang._('Result') }}",
    message: (data.status == 'OK' ?
              '{{ lang._('Magic packet was sent successfully.') }}' :
              '{{ lang._('The packet was not sent due to an error. Please consult the logs.') }}<br />' +
              $('<pre>').text(data.error_msg).html()
            ),
    buttons: [{
                label: '{{ lang._('Close') }}',
                action: function(dialog){
                  dialog.close();
                }
            }]
  });
}

$( document ).ready(function() {
  // delete host action
  $("#act_wake_all").click(function(event){
      event.preventDefault();
      $.post('/api/wol/wol/wakeall', {}, function(data) {
          BootstrapDialog.show({
              type: BootstrapDialog.TYPE_INFO,
              title: "{{ lang._('Result') }}",
              message: '<ul>' + (data['results'].map(function(element) {
                  return `<li>${element.mac}: ${element.status}</li>`
              }).join('')) + '</ul>',
              buttons: [{
                    label: '{{ lang._('Close') }}',
                    action: function(dialog){
                        dialog.close();
                    }
              }]
          });
      });
  });


  var grid = $("#grid-wol-settings").UIBootgrid(
      { 'search':'/api/wol/wol/searchHost',
        'get':'/api/wol/wol/getHost/',
        'set':'/api/wol/wol/setHost/',
        'add':'/api/wol/wol/addHost/',
        'del':'/api/wol/wol/delHost/',
        'options':{
            selection:false,
            multiSelect:false,
            formatters: {
              "commandswithwake": function (column, row) {
                return "<button type=\"button\" class=\"btn btn-xs btn-default command-wake\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clock-o\"></span></button> " +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-copy\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
              }
          }
        }
      }
  );
  grid.on("loaded.rs.jquery.bootgrid", function(){
    grid.find('.command-wake').click(function(event) {
      event.preventDefault();
      $.post('/api/wol/wol/set', {'uuid': this.dataset['rowId']}, function(data) {
        show_wake_result(data);
      });
    });
  });
});

function msg_not_successful(wolent) {
    return '{{ lang._('Please check the %ssystem log%s, the wol command for %s (%s) did not complete successfully.') | format( '<a href="/ui/diagnostics/log/core/system">', '</a>', '%s', '%s') }}'
    .replace('%s', wolent['descr']).replace('%s',$('<pre>').text(wolent['mac']).html());
}
function msg_successful(wolent) {
    return 'Sent magic packet to %s (%s).'.replace('%s', wolent['descr']).replace('%s',$('<pre>').text(wolent['mac']).html());
}


$( document ).ready(function() {
    var data_get_map = {'frm_wol_wake':"/api/wol/wol/getwake"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    // link save button to API set action
    $("#wakeAct").click(function(){
        ajaxCall(url="/api/wol/wol/set", data=getFormData('frm_wol_wake'),callback=function(data, status){
          show_wake_result(data);
        }, true);
    });
});

</script>
<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form",['fields':wakeForm,'id':'frm_wol_wake'])}}
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="wakeAct" type="button"><b>{{ lang._('Wake up') }}</b> <i id="wakeAct_progress"></i></button>
    </div>
</div>

<div class="content-box" style="padding-bottom: 1.5em;">

      <table id="grid-wol-settings" class="table table-responsive" data-editDialog="frm_wol_settings">
        <thead>
            <tr>
                <th data-column-id="interface" data-type="string" data-visible="true">{{ lang._('Interface') }}</th>
                <th data-column-id="mac" data-type="string" data-visible="true">{{ lang._('MAC') }}</th>
                <th data-column-id="descr" data-type="string" data-identifier="true">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-formatter="commandswithwake" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary" id="act_wake_all"><span class="fa wakeallAct_progress"></span> {{ lang._('Wake All') }}</button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>


{{ partial("layout_partials/base_dialog",['fields': hostForm,'id':'frm_wol_settings', 'label':lang._('Edit WOL Host')]) }}
