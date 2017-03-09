{{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_ospf_settings'])}}

<script type="text/javascript">
    $( document ).ready(function() {
        var data_get_map = {'frm_ospf_settings':"/api/quagga/ospfsettings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/quagga/ospfsettings/set",formid='frm_ospf_settings',callback_ok=function(){
                // action to run after successful save, for example reconfigure service.
            });
        });
	$("#grid-networks").UIBootgrid(
                {   'search':'/api/quagga/ospfsettings/searchNetwork',
                    'get':'/api/quagga/ospfsettings/getNetwork/',
                    'set':'/api/quagga/ospfsettings/setNetwork/',
                    'add':'/api/quagga/ospfsettings/addNetwork/',
                    'del':'/api/quagga/ospfsettings/delNetwork/',
                    'toggle':'/api/quagga/ospfsettings/toggleNetwork/',
                    'options':{selection:false, multiSelect:false}
                }
	);


    });
</script>

<div class="col-md-12">
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
</div>

<h2>Networks</h2>
<div class="tab-content content-box tab-content">
<div id="networks" class="tab-pane fade in active">

<table id="grid-networks" class="table table-responsive" data-editDialog="DialogEditNetwork">
<thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="ipaddr" data-type="string" data-visible="true">{{ lang._('Network Address') }}</th>
                <th data-column-id="netmask" data-type="string" data-visible="true">{{ lang._('Mask') }}</th>
                <th data-column-id="area" data-type="string" data-visible="false">{{ lang._('Area') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="5"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                </td>
            </tr>
</tfoot>
</table>

</div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditNetwork,'id':'DialogEditNetwork','label':'Edit OSPF Network'])}}
