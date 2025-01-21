<script type="text/javascript">
    $( document ).ready(function() {
        mapDataToFormUI({'frmSettings':"/api/tailscale/settings/get"}).done(function(data) {
            updateServiceControlUI('tailscale');
        });

        $("#grid-subnets").UIBootgrid(
            {   search:'/api/tailscale/settings/search_subnet',
                get:'/api/tailscale/settings/get_subnet/',
                set:'/api/tailscale/settings/set_subnet/',
                add:'/api/tailscale/settings/add_subnet/',
                del:'/api/tailscale/settings/del_subnet/'
            }
        );

        // link save button to API set action
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/tailscale/settings/set", 'frmSettings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('tailscale');
            }
        });
    });
</script>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings" id="tab_settings">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#subnets" id="tab_subnets"> {{ lang._('Advertised Routes') }} </a></li>
</ul>
<div class="tab-content content-box">
    <div id="settings"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':settingsForm,'id':'frmSettings'])}}
    </div>
    <div id="subnets" class="tab-pane fade in">
        <table id="grid-subnets" class="table table-condensed table-hover table-striped" data-editDialog="DialogSubnet" data-editAlert="tailscaleChangeMessage">
            <thead>
                <tr>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="subnet" data-type="string">{{ lang._('Subnet') }}</th>
                  <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                  <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/tailscale/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring Tailscale') }}"
                type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':'DialogSubnet','label':lang._('Edit Subnet')])}}
