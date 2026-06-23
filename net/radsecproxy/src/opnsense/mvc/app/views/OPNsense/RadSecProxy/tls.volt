<script>
    $( document ).ready(function() {
        $("#grid-addresses").UIBootgrid(
            {   search:'/api/radsecproxy/tls/search_item/',
                get:'/api/radsecproxy/tls/get_item/',
                set:'/api/radsecproxy/tls/set_item/',
                add:'/api/radsecproxy/tls/add_item/',
                del:'/api/radsecproxy/tls/del_item/',
                toggle:'/api/radsecproxy/tls/toggle_item/'
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

<div class="tab-content content-box">
    <table id="grid-addresses" class="table table-condensed table-hover table-striped" data-editDialog="DialogTls">
        <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="caCertificateRefId" data-type="string">{{ lang._('CA-certificate') }}</th>
                <th data-column-id="proxyCertificateRefId" data-type="string">{{ lang._('Proxy-certificate') }}</th>
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
</div>
{{ partial("layout_partials/base_dialog",['fields':formDialogTls,'id':'DialogTls','label':lang._('Edit TLS-config')])}}
