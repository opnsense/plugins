{#

OPNsense® is Copyright © 2014 – 2025 by Deciso B.V.
This file is Copyright © 2017 by Fabian Franz
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

<script>

    function quagga_update_status() {
      updateServiceControlUI('quagga');
    }

    $( document ).ready(function() {
        mapDataToFormUI({'frm_ospf_settings':"/api/quagga/ospfsettings/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('quagga');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/quagga/ospfsettings/set", 'frm_ospf_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('quagga');
            }
        });

        $("#grid-networks").UIBootgrid({
            'search':'/api/quagga/ospfsettings/searchNetwork',
            'get':'/api/quagga/ospfsettings/getNetwork/',
            'set':'/api/quagga/ospfsettings/setNetwork/',
            'add':'/api/quagga/ospfsettings/addNetwork/',
            'del':'/api/quagga/ospfsettings/delNetwork/',
            'toggle':'/api/quagga/ospfsettings/toggleNetwork/'
        });
        $("#grid-interfaces").UIBootgrid({
            'search':'/api/quagga/ospfsettings/searchInterface',
            'get':'/api/quagga/ospfsettings/getInterface/',
            'set':'/api/quagga/ospfsettings/setInterface/',
            'add':'/api/quagga/ospfsettings/addInterface/',
            'del':'/api/quagga/ospfsettings/delInterface/',
            'toggle':'/api/quagga/ospfsettings/toggleInterface/'
        });
        $("#grid-prefixlists").UIBootgrid({
            'search':'/api/quagga/ospfsettings/searchPrefixlist',
            'get':'/api/quagga/ospfsettings/getPrefixlist/',
            'set':'/api/quagga/ospfsettings/setPrefixlist/',
            'add':'/api/quagga/ospfsettings/addPrefixlist/',
            'del':'/api/quagga/ospfsettings/delPrefixlist/',
            'toggle':'/api/quagga/ospfsettings/togglePrefixlist/'
        });
        $("#grid-routemaps").UIBootgrid({
            'search':'/api/quagga/ospfsettings/searchRoutemap',
            'get':'/api/quagga/ospfsettings/getRoutemap/',
            'set':'/api/quagga/ospfsettings/setRoutemap/',
            'add':'/api/quagga/ospfsettings/addRoutemap/',
            'del':'/api/quagga/ospfsettings/delRoutemap/',
            'toggle':'/api/quagga/ospfsettings/toggleRoutemap/'
        });
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#networks">{{ lang._('Networks') }}</a></li>
    <li><a data-toggle="tab" href="#interfaces">{{ lang._('Interfaces') }}</a></li>
    <li><a data-toggle="tab" href="#prefixlists">{{ lang._('Prefix Lists') }}</a></li>
    <li><a data-toggle="tab" href="#routemaps">{{ lang._('Route Maps') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <!-- Tab: General -->
    <div id="general" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_ospf_settings'])}}
    </div>
    <!-- Tab: Networks -->
    <div id="networks" class="tab-pane fade in">
      <table id="grid-networks" class="table table-responsive" data-editDialog="DialogEditNetwork">
          <thead>
              <tr>
                  <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                  <th data-column-id="ipaddr" data-type="string" data-visible="true">{{ lang._('Network Address') }}</th>
                  <th data-column-id="netmask" data-type="string" data-visible="true">{{ lang._('Mask') }}</th>
                  <th data-column-id="area" data-type="string" data-visible="true">{{ lang._('Area') }}</th>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    </div>
    <!-- Tab: Interfaces -->
    <div id="interfaces" class="tab-pane fade in">
        <table id="grid-interfaces" class="table table-responsive" data-editDialog="DialogEditInterface">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="interfacename" data-type="string" data-visible="true">{{ lang._('Interface Name') }}</th>
                    <th data-column-id="networktype" data-type="string" data-visible="true">{{ lang._('Network Type') }}</th>
                    <th data-column-id="authtype" data-type="string" data-visible="true">{{ lang._('Authentication Type') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    </div>
    <!-- Tab: Prefixlists -->
    <div id="prefixlists" class="tab-pane fade in">
        <table id="grid-prefixlists" class="table table-responsive" data-editDialog="DialogEditPrefixLists">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-sortable="false">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Name') }}</th>
                    <th data-column-id="seqnumber" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Sequence Number') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Action') }}</th>
                    <th data-column-id="network" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Network') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    </div>
    <!-- Tab: Routemaps -->
    <div id="routemaps" class="tab-pane fade in">
        <table id="grid-routemaps" class="table table-responsive" data-editDialog="DialogEditRouteMaps">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true">{{ lang._('Action') }}</th>
                    <th data-column-id="id" data-type="string" data-visible="true">{{ lang._('ID') }}</th>
                    <th data-column-id="match2" data-type="string" data-visible="true">{{ lang._('Prefix List') }}</th>
                    <th data-column-id="set" data-type="string" data-visible="true">{{ lang._('Set') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <button class="btn btn-primary __mb __mt" id="reconfigureAct"
                data-endpoint='/api/quagga/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring OSPFv3') }}"
                data-service-widget="quagga"
                type="button"
            ></button>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditNetwork,'id':'DialogEditNetwork','label':lang._('Edit Network')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditInterface,'id':'DialogEditInterface','label':lang._('Edit Interface')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPrefixLists,'id':'DialogEditPrefixLists','label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRouteMaps,'id':'DialogEditRouteMaps','label':lang._('Edit Route Maps')])}}
