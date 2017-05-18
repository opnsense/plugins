{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 Michael Muenz
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
<!-- Navigation bar -->
    
<!-- START FRAENKI -->
<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    <li><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#neighbors">{{ lang._('Neighbors') }}</a></li>
    <li><a data-toggle="tab" href="#aspaths">{{ lang._('AS-Path Lists') }}</a></li>
    <li><a data-toggle="tab" href="#prefixlists">{{ lang._('Prefix Lists') }}</a></li>
{% for tab in formDialogEditBGPRouteMaps['tabs']|default([]) %}
    {% if tab['subtabs']|default(false) %}
        {# Tab with dropdown #}

        {# Find active subtab #}
            {% set active_subtab="" %}
            {% for subtab in tab['subtabs']|default({}) %}
                {% if subtab[0]==formDialogEditBGPRouteMaps['activetab']|default("") %}
                    {% set active_subtab=subtab[0] %}
                {% endif %}
            {% endfor %}

        <li role="presentation" class="dropdown {% if formDialogEditBGPRouteMaps['activetab']|default("") == active_subtab %}active{% endif %}">
            <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button" style="border-left: 1px dashed lightgray;">
                <b><span class="caret"></span></b>
            </a>
            <a data-toggle="tab" href="#subtab_{{tab['subtabs'][0][0]}}" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{tab[1]}}</b></a>
            <ul class="dropdown-menu" role="menu">
                {% for subtab in tab['subtabs']|default({})%}
                <li class="{% if formDialogEditBGPRouteMaps['activetab']|default("") == subtab[0] %}active{% endif %}"><a data-toggle="tab" href="#subtab_{{subtab[0]}}"><i class="fa fa-check-square"></i> {{subtab[1]}}</a></li>
                {% endfor %}
            </ul>
        </li>
    {% else %}
        {# Standard Tab #}
        <li {% if formDialogEditBGPRouteMaps['activetab']|default("") == tab[0] %} class="active" {% endif %}>
                <a data-toggle="tab" href="#tab_{{tab[0]}}">
                    <b>{{tab[1]}}</b>
                </a>
        </li>
    {% endif %}
{% endfor %}
   
</ul>
</ul>

<div class="content-box tab-content">
    {% for tab in formDialogEditBGPRouteMaps['tabs']|default([]) %}
        {% if tab['subtabs']|default(false) %}
            {# Tab with dropdown #}
            {% for subtab in tab['subtabs']|default({})%}
                <div id="subtab_{{subtab[0]}}" class="tab-pane fade{% if formDialogEditBGPRouteMaps['activetab']|default("") == subtab[0] %} in active {% endif %}">
                    {{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPRouteMaps[2],'id':'DialogEditBGPRouteMaps'~subtab[0],'data_title':subtab[1],'apply_btn_id':'save_'~subtab[0]])}}
                </div>
            {% endfor %}
        {% endif %}
        {% if tab['subtabs']|default(false)==false %}
            <div id="tab_{{tab[0]}}" class="tab-pane fade{% if formDialogEditBGPRouteMaps['activetab']|default("") == tab[0] %} in active {% endif %}">
                {{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPRouteMaps[2],'id':'DialogEditBGPRouteMaps'~tab[0],'apply_btn_id':'save_'~tab[0]])}}
            </div>
        {% endif %}
    {% endfor %}

<!-- END FRAENKI -->
    


    <div id="neighbors" class="tab-pane fade in">
        <table id="grid-neighbors" class="table table-responsive" data-editDialog="DialogEditBGPNeighbor">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="address" data-type="string" data-visible="true">{{ lang._('Neighbor Address') }}</th>
                    <th data-column-id="remoteas" data-type="string" data-visible="true">{{ lang._('Remote AS') }}</th>
                    <th data-column-id="updatesource" data-type="string" data-visible="true">{{ lang._('Update Source Address') }}</th>
                    <th data-column-id="nexthopself" data-type="string" data-formatter="rowtoggle">{{ lang._('Next Hop Self') }}</th>
                    <th data-column-id="defaultoriginate" data-type="string" data-formatter="rowtoggle">{{ lang._('Default Originate') }}</th>
                    <th data-column-id="linkedPrefixlistIn" data-type="string" data-visible="true">{{ lang._('Prefix-List Inbound') }}</th>
                    <th data-column-id="linkedPrefixlistOut" data-type="string" data-visible="true">{{ lang._('Prefix-List Outbound') }}</th>
                    <th data-column-id="linkedRoutemapIn" data-type="string" data-visible="true">{{ lang._('Route-Map Inbound') }}</th>
                    <th data-column-id="linkedRoutemapOut" data-type="string" data-visible="true">{{ lang._('Route-Map Outbound') }}</th>                
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
                
    <div id="aspaths" class="tab-pane fade in">
        <table id="grid-aspaths" class="table table-responsive" data-editDialog="DialogEditBGPASPaths">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-sortable="false">{{ lang._('Enabled') }}</th>
                    <th data-column-id="number" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Number') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Action') }}</th>
                    <th data-column-id="as" data-type="string" data-visible="true" data-sortable="false">{{ lang._('AS Number') }}</th>
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

    <div id="prefixlists" class="tab-pane fade in">
        <table id="grid-prefixlists" class="table table-responsive" data-editDialog="DialogEditBGPPrefixLists">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-sortable="false">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Name') }}</th>
                    <th data-column-id="seqnumber" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Secquence Number') }}</th>
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
                    <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>                
                
    <div id="routemaps-general-settings" class="tab-pane fade in">
        <table id="grid-routemaps-general" class="table table-responsive" data-editDialog="DialogEditBGPRouteMaps">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
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

    
<script type="text/javascript">
$(document).ready(function() {
  var data_get_map = {'frm_bgp_settings':"/api/quagga/bgp/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status']);
  });

  // link save button to API set action
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/quagga/bgp/set",formid='frm_bgp_settings',callback_ok=function(){
        ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
          ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
          });
        });
      });
  });
  $("#grid-neighbors").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchNeighbor',
      'get':'/api/quagga/bgp/getNeighbor/',
      'set':'/api/quagga/bgp/setNeighbor/',
      'add':'/api/quagga/bgp/addNeighbor/',
      'del':'/api/quagga/bgp/delNeighbor/',
      'toggle':'/api/quagga/bgp/toggleNeighbor/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-aspaths").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchAspath',
      'get':'/api/quagga/bgp/getAspath/',
      'set':'/api/quagga/bgp/setAspath/',
      'add':'/api/quagga/bgp/addAspath/',
      'del':'/api/quagga/bgp/delAspath/',
      'toggle':'/api/quagga/bgp/toggleAspath/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-prefixlists").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchPrefixlist',
      'get':'/api/quagga/bgp/getPrefixlist/',
      'set':'/api/quagga/bgp/setPrefixlist/',
      'add':'/api/quagga/bgp/addPrefixlist/',
      'del':'/api/quagga/bgp/delPrefixlist/',
      'toggle':'/api/quagga/bgp/togglePrefixlist/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-routemaps-general-settings").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchRoutemap',
      'get':'/api/quagga/bgp/getRoutemap/',
      'set':'/api/quagga/bgp/setRoutemap/',
      'add':'/api/quagga/bgp/addRoutemap/',
      'del':'/api/quagga/bgp/delRoutemap/',
      'toggle':'/api/quagga/bgp/toggleRoutemap/',
      'options':{selection:false, multiSelect:false}
    }
  ); 
  $("#grid-ids").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchRoutemap2',
      'get':'/api/quagga/bgp/getRoutemap2/',
      'set':'/api/quagga/bgp/setRoutemap2/',
      'add':'/api/quagga/bgp/addRoutemap2/',
      'del':'/api/quagga/bgp/delRoutemap2/',
      'toggle':'/api/quagga/bgp/toggleRoutemap2/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-sets").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchRoutemap3',
      'get':'/api/quagga/bgp/getRoutemap3/',
      'set':'/api/quagga/bgp/setRoutemap3/',
      'add':'/api/quagga/bgp/addRoutemap3/',
      'del':'/api/quagga/bgp/delRoutemap3/',
      'toggle':'/api/quagga/bgp/toggleRoutemap3/',
      'options':{selection:false, multiSelect:false}
    }
  );
    });
</script>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPNeighbor,'id':'DialogEditBGPNeighbor','label':lang._('Edit Neighbor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPASPaths,'id':'DialogEditBGPASPaths','label':lang._('Edit AS-Paths')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPPrefixLists,'id':'DialogEditBGPPrefixLists','label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPRouteMaps,'id':'DialogEditBGPRouteMaps','label':lang._('Edit Route-Maps')])}}
