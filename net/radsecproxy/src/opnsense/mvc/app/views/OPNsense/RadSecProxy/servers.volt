<script>
    $( document ).ready(function() {
        $("#grid-addresses").UIBootgrid(
            {   search:'/api/radsecproxy/servers/searchItem/',
                get:'/api/radsecproxy/servers/getItem/',
                set:'/api/radsecproxy/servers/setItem/',
                add:'/api/radsecproxy/servers/addItem/',
                del:'/api/radsecproxy/servers/delItem/',
                toggle:'/api/radsecproxy/servers/toggleItem/'
            }
        );
        updateServiceControlUI('radsecproxy');

        // link apply button to API set action
        $("#saveAct").click(function(){
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            // action to run after successful save, for example reconfigure service.
            ajaxCall(url="/api/radsecproxy/service/reconfigure", sendData={},callback=function(data,status) {
                // action to run after reload
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                updateServiceControlUI('radsecproxy');
            });
        });
    });
</script>

<table id="grid-addresses" class="table table-condensed table-hover table-striped" data-editDialog="DialogServer">
    <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="host" data-type="string">{{ lang._('Host') }}</th>
            <th data-column-id="identifier" data-type="string">{{ lang._('Identifier') }}</th>
            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
            <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
            <th data-column-id="tlsConfig" data-type="string">{{ lang._('TLS-Config') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
            </td>
        </tr>
    </tfoot>
</table>

<div class="col-md-12">
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAct_progress"></i></button>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogServer,'id':'DialogServer','label':lang._('Edit server')])}}
