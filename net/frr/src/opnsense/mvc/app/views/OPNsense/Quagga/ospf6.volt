{#
 # Copyright (c) 2014-2024 Deciso B.V.
 # Copyright (c) 2017 Fabian Franz
 # Copyright (c) 2017 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    'use strict';
    $( document ).ready(function () {
        mapDataToFormUI({'frm_ospf6_settings':"/api/quagga/ospf6settings/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        updateServiceControlUI('quagga');

        // link save button to API set action
        $("#saveAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/quagga/ospf6settings/set", 'frm_ospf6_settings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        $("#grid-networks").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchNetwork',
            'get':'/api/quagga/ospf6settings/getNetwork/',
            'set':'/api/quagga/ospf6settings/setNetwork/',
            'add':'/api/quagga/ospf6settings/addNetwork/',
            'del':'/api/quagga/ospf6settings/delNetwork/',
            'toggle':'/api/quagga/ospf6settings/toggleNetwork/',
            'options':{
                selection:false,
                multiSelect:false
            }
        });
        $("#grid-interfaces").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchInterface',
            'get':'/api/quagga/ospf6settings/getInterface/',
            'set':'/api/quagga/ospf6settings/setInterface/',
            'add':'/api/quagga/ospf6settings/addInterface/',
            'del':'/api/quagga/ospf6settings/delInterface/',
            'toggle':'/api/quagga/ospf6settings/toggleInterface/',
            'options':{
                selection:false,
                multiSelect:false
            }
        });
        $("#grid-prefixlists").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchPrefixlist',
            'get':'/api/quagga/ospf6settings/getPrefixlist/',
            'set':'/api/quagga/ospf6settings/setPrefixlist/',
            'add':'/api/quagga/ospf6settings/addPrefixlist/',
            'del':'/api/quagga/ospf6settings/delPrefixlist/',
            'toggle':'/api/quagga/ospf6settings/togglePrefixlist/',
            'options':{
                selection:false,
                multiSelect:false
            }
        });
        $("#grid-routemaps").UIBootgrid({
            'search':'/api/quagga/ospf6settings/searchRoutemap',
            'get':'/api/quagga/ospf6settings/getRoutemap/',
            'set':'/api/quagga/ospf6settings/setRoutemap/',
            'add':'/api/quagga/ospf6settings/addRoutemap/',
            'del':'/api/quagga/ospf6settings/delRoutemap/',
            'toggle':'/api/quagga/ospf6settings/toggleRoutemap/',
            'options':{
                selection:false,
                multiSelect:false
            }
        });

        // hook checkbox item with conditional options
        $("#ospf6\\.originate").change(function(){
            if ($(this).is(':checked')) {
                $(".ospf6_originate").closest('tr').show();
            } else {
                $(".ospf6_originate").closest('tr').hide();
            }
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
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':ospf6Form,'id':'frm_ospf6_settings'])}}
        </div>
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
                    <th data-column-id="area" data-type="string" data-visible="true">{{ lang._('Area') }}</th>
                    <th data-column-id="networktype" data-type="string" data-visible="true">{{ lang._('Network Type') }}</th>
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
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Tab: Prefix Lists -->
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
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Tab: Route Maps -->
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
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <button class="btn btn-primary __mb __mt" id="saveAct"
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
