{#

OPNsense® is Copyright © 2014 – 2023 by Deciso B.V.
Copyright (C) 2023 Marc Bartelt
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

<script src="{{ cache_safe('/ui/js/frr/diagnostics_utils.js') }}"></script>

<style>
    .searchbox {
      margin: 8px;
    }

    .node-selected {
        font-weight: bolder;
    }

    #page_frr_subtitle {
        margin-left: -2px;
        > span {
            font-style: italic;
        }
    }
  </style>
  <link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/jqtree.css', ui_theme|default('opnsense'))) }}">
  <script src="{{ cache_safe('/ui/js/tree.jquery.min.js') }}"></script>

<script>
    'use strict';

    $( document ).ready(function() {
        updateServiceControlUI('quagga');

        /**
         * only display the refresh button on the currently active tab
         */
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          $(".tab-icon").removeClass("fa-refresh");
          $("#"+e.target.id).find(".tab-icon").addClass("fa-refresh");
        });

        /**
         * resize tree widgets on window resize
         */
        $(window).on('resize', resizeTreeWidget);

        /**
         * delayed search for tree widgets
         */
        $(".tree_search").keyup(treeSearchKeyUp);

        let all_grids = [];
        {% for tab in tabs %}
            /**
             * register refresh event handler for {{ tab['tabhead'] }}
             */
            $("#refresh-{{ tab['name'] }}").click(function () {
            {% switch tab['type'] %}
                {% case 'generalroutetable' %}
                {% case 'bgproutetable' %}
                {% case 'ospfroutetable' %}
                {% case 'ospfv3routetable' %}
                {% case 'ospfv3databasetable' %}
                {% case 'ospfneighbors' %}
                {% case 'bfdsummary' %}
                    if (all_grids["{{ tab['name'] }}"] === undefined) {
                        /**
                         * initialize bootgrid table for {{ tab['tabhead'] }}
                         */
                        let gridopt = {
                            search: "{{ tab['endpoint'] }}",
                            options:{
                                formatters : {
                                    general_route_code: function(column, row){
                                        let protocols = {
                                            'kernel' : {short: 'K', long: '{{ lang._('Kernel') }}'},
                                            'connected': {short: 'C', long: '{{ lang._('Connected') }}'},
                                            'bgp': {short: 'B', long: '{{ lang._('BGP') }}'},
                                            'ospf': {short: 'O', long: '{{ lang._('OSPF') }}'},
                                            'ospf6': {short: 'O', long: '{{ lang._('OSPFv3') }}'}
                                        };
                                        let field = $("<div/>");
                                        if (protocols[row.protocol] !== undefined) {
                                            let tmp = protocols[row.protocol];
                                            field.append($("<abbr>").text(tmp.short).attr('title', tmp.long));
                                        }
                                        if(row.selected) {
                                            field.append($("<abbr>").html("&gt;").attr('title', "{{ lang._('Selected') }}"));
                                        }
                                        if(row.installed) {
                                            field.append($("<abbr>").html("&ast;").attr('title', "{{ lang._('FIB') }}"));
                                        }
                                        return field.html();
                                    },
                                    ospf_route_type: function(column, row) {
                                        let ospf_route_types = {
                                            'N': "{{ lang._('Network') }}",
                                            'IA': "{{ lang._('OSPF inter area') }}",
                                            'N1': "{{ lang._('OSPF NSSA external type 1') }}",
                                            'N2': "{{ lang._('OSPF NSSA external type 2') }}",
                                            'E1': "{{ lang._('OSPF external type 1') }}",
                                            'E2': "{{ lang._('OSPF external type 2') }}",
                                        };
                                        let field = $("<div/>");
                                        row[column.id].split(' ').forEach(function(routeType) {
                                            if (ospf_route_types[routeType] !== undefined) {
                                                field.append($("<abbr>").text(routeType).attr('title', ospf_route_types[routeType]));
                                            } else {
                                                field.append($("<abbr>").text(routeType));
                                            }
                                            field.append('&nbsp;');
                                        });

                                        return field.html();
                                    },
                                    ospf6_dest_type: function(column, row){
                                        let ospf6_dest_types = {
                                            '?': "{{ lang._('Unknown') }}",
                                            'R': "{{ lang._('Router') }}",
                                            'N': "{{ lang._('Network') }}",
                                            'D': "{{ lang._('Discard') }}",
                                            'L': "{{ lang._('Linkstate') }}",
                                            'A': "{{ lang._('AddressRange') }}",
                                        };
                                        let field = $("<div/>");
                                        if (ospf6_dest_types[row[column.id]] !== undefined) {
                                            field.append($("<abbr>").text(row[column.id]).attr('title', ospf6_dest_types[row[column.id]]));
                                        } else {
                                            field.append($("<abbr>").text(row[column.id]));
                                        }

                                        return field.html();
                                    },
                                    ospf6_path_type: function(column, row){
                                        let ospf6_path_types = {
                                            '??': "{{ lang._('UnknownRoute') }}",
                                            'IA': "{{ lang._('IntraArea') }}",
                                            'IE': "{{ lang._('InterArea') }}",
                                            'E1': "{{ lang._('External1') }}",
                                            'E2': "{{ lang._('External2') }}",
                                        };
                                        let field = $("<div/>");
                                        if (ospf6_path_types[row[column.id]] !== undefined) {
                                            field.append($("<abbr>").text(row[column.id]).attr('title', ospf6_path_types[row[column.id]]));
                                        } else {
                                            field.append($("<abbr>").text(row[column.id]));
                                        }
                                        return field.html();
                                    },
                                    boolean: function (column, row) {
                                        if (row[column.id]) {
                                            return "<span class=\"fa fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                                        } else {
                                            return "<span class=\"fa fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                                        }
                                    },
                                    origin: function(column, row) {
                                        return (row[column.id] === 'incomplete' ? '<abbr title="{{ lang._('Incomplete') }}">&quest;</abbr>' : row[column.id]);
                                    }
                                },
                                responseHandler: function (data) {
                                    if (data.subtitle !== undefined) {
                                        $("#page_frr_subtitle").empty();
                                        $("#page_frr_subtitle").append('<i class="fa fa-fw fa-chevron-right" aria-hidden="true"></i>');
                                        $("#page_frr_subtitle").append($("<span/>").text(data.subtitle));
                                    }
                                    return data;
                                }
                            }
                        };
                        all_grids["{{ tab['name'] }}"] = $("#grid-{{ tab['name'] }}").UIBootgrid(gridopt);
                        all_grids["{{ tab['name'] }}"].on("loaded.rs.jquery.bootgrid", function (e) {
                            $("abbr").tooltip();
                        });
                    } else {
                        all_grids["{{ tab['name'] }}"].bootgrid('reload');
                    }
                    {% break %}
                {% case 'tree' %}
                    ajaxGet("{{ tab['endpoint'] }}", {}, function (data, status) {
                        if (status == "success") {
                            let $tree = $("#tree-{{ tab['name'] }}");
                            if ($("#tree-{{ tab['name'] }} > ul").length == 0) {
                                $tree.tree({
                                    data: dict_to_tree(data['response']),
                                    autoOpen: false,
                                    dragAndDrop: false,
                                    selectable: false,
                                    closedIcon: $('<i class="fa fa-plus-square-o"></i>'),
                                    openedIcon: $('<i class="fa fa-minus-square-o"></i>'),
                                    onCreateLi: function(node, $li) {
                                        let n_title = $li.find('.jqtree-title');
                                        n_title.text(n_title.text().replace('&gt;','\>').replace('&lt;','\<'));
                                        if (node.value !== undefined) {
                                            $li.find('.jqtree-element').append(
                                                '&nbsp; <strong>:</strong> &nbsp;' + node.value
                                            );
                                        }
                                        if (node.selected) {
                                            $li.addClass("node-selected");
                                        } else {
                                            $li.removeClass("node-selected");
                                        }
                                    }
                                });
                                // initial view, collapse first level if there's only one node
                                if (Object.keys(data['response']).length == 1) {
                                    for (let key in data['response']) {
                                        $tree.tree('openNode', $tree.tree('getNodeById', key));
                                    }
                                }
                                //open node on label click
                                $tree.bind('tree.click', function(e) {
                                    $tree.tree('toggle', e.node);
                                });
                            } else {
                                let curent_state = $tree.tree('getState');
                                $tree.tree('loadData', dict_to_tree(data['response']));
                                $tree.tree('setState', curent_state);
                            }
                        }
                    });
                    $(window).trigger('resize');
                    {% break %}
                {% case 'text' %}
                    ajaxGet("{{ tab['endpoint'] }}", {}, function(data, status) {
                        if (status == "success") {
                            $('#text-{{ tab['name'] }}').html(data['response']);
                        }
                    });
                    {% break %}
            {% endswitch %}
            });

            /**
             * perform data fetch via event handler
             */
            $("a[id='{{ tab['name'] }}_tab']").on("shown.bs.tab", function (event) {
                $("#refresh-{{ tab['name'] }}").click();
            });
        {% endfor %}

        /**
         * add "sub title" heading
         */
        $("header.page-content-head > div:eq(0) > ul:eq(0) > li:eq(0)").append($("<span id='page_frr_subtitle'/>"));

        /**
         * activate the default tab
         */
        $("a[id='{{ default_tab }}_tab']").click();
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    {% for tab in tabs %}
        <li>
            <a data-toggle="tab" href="#{{ tab['name'] }}" id="{{tab['name']}}_tab">
                {{ tab['tabhead'] }} <span id="refresh-{{ tab['name'] }}" class="fa tab-icon fa-refresh" style="cursor: pointer"></span>
            </a>
        </li>
    {% endfor %}
</ul>

<div class="content-box tab-content" style="padding-bottom: 1.5em;">
    {% for tab in tabs %}
        <div id="{{ tab['name'] }}" class="tab-pane fade">
            {% switch tab['type'] %}
                {% case 'generalroutetable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="protocol" data-searchable="false" data-formatter="general_route_code" data-sortable="false">{{ lang._('Code') }}</th>
                                <th data-column-id="selected" data-searchable="false" data-visible="false" data-sortable="false">{{ lang._('Selected') }}</th>
                                <th data-column-id="installed" data-searchable="false" data-visible="false" data-sortable="false">{{ lang._('Installed') }}</th>
                                <th data-column-id="prefix">{{ lang._('Network') }}</th>
                                <th data-column-id="distance" data-searchable="false">{{ lang._('Administrative Distance') }}</th>
                                <th data-column-id="metric" data-searchable="false">{{ lang._('Metric') }}</th>
                                <th data-column-id="interfaceName">{{ lang._('Interface') }}</th>
                                <th data-column-id="interfaceDescr">{{ lang._('Interface name') }}</th>
                                <th data-column-id="via">{{ lang._('Via') }}</th>
                                <th data-column-id="uptime" data-searchable="false">{{ lang._('Time') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'bgproutetable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="valid" data-searchable="false" data-formatter="boolean" data-sortable="false">{{ lang._('Valid') }}</th>
                                <th data-column-id="bestpath" data-searchable="false" data-formatter="boolean" data-sortable="false">{{ lang._('Best') }}</th>
                                <th data-column-id="internal" data-searchable="false" data-formatter="boolean" data-sortable="false">{{ lang._('Internal') }}</th>
                                <th data-column-id="network" data-width="15%">{{ lang._('Network') }}</th>
                                <th data-column-id="ip" data-width="25%">{{ lang._('Next Hop') }}</th>
                                <th data-column-id="metric" data-searchable="false">{{ lang._('Metric') }}</th>
                                <th data-column-id="locPrf" data-searchable="false">{{ lang._('LocPrf') }}</th>
                                <th data-column-id="weight" data-searchable="false">{{ lang._('Weight') }}</th>
                                <th data-column-id="path" data-width="21%">{{ lang._('Path') }}</th>
                                <th data-column-id="origin" data-width="10%" data-formatter="origin">{{ lang._('Origin') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'ospfroutetable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="type" data-searchable="false" data-formatter="ospf_route_type" data-sortable="false">{{ lang._('Type') }}</th>
                                <th data-column-id="network">{{ lang._('Network') }}</th>
                                <th data-column-id="cost" data-searchable="false">{{ lang._('Cost') }}</th>
                                <th data-column-id="area">{{ lang._('Area') }}</th>
                                <th data-column-id="via">{{ lang._('Via') }}</th>
                                <th data-column-id="viainterface">{{ lang._('Via interface') }}</th>
                                <th data-column-id="viainterfaceDescr">{{ lang._('Via interface name') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'ospfv3routetable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="destinationType" data-width="5em" data-formatter="ospf6_dest_type" data-sortable="false">{{ lang._('Type') }}</th>
                                <th data-column-id="isBestRoute" data-width="5em" data-formatter="boolean" data-searchable="false" data-sortable="false">{{ lang._('Best') }}</th>
                                <th data-column-id="pathType" data-formatter="ospf6_path_type">{{ lang._('Path type') }}</th>
                                <th data-column-id="network">{{ lang._('Network') }}</th>
                                <th data-column-id="nextHop" data-type="string">{{ lang._('nextHop') }}</th>
                                <th data-column-id="interfaceName" data-type="string">{{ lang._('Interface') }}</th>
                                <th data-column-id="interfaceDescr">{{ lang._('interface name') }}</th>
                                <th data-column-id="duration" data-type="string">{{ lang._('Time') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'ospfneighbors' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="neighborid">{{ lang._('Neighbor ID') }}</th>
                                <th data-column-id="priority" data-searchable="false">{{ lang._('Priority') }}</th>
                                <th data-column-id="state">{{ lang._('State') }}</th>
                                <th data-column-id="deadTimeMsecs" data-searchable="false">{{ lang._('Dead Time') }} &lsqb;ms&rsqb;</th>
                                <th data-column-id="address">{{ lang._('Address') }}</th>
                                <th data-column-id="ifaceName">{{ lang._('Interface') }}</th>
                                <th data-column-id="retransmitCounter" data-searchable="false">{{ lang._('Retransmit Counter') }}</th>
                                <th data-column-id="requestCounter" data-searchable="false">{{ lang._('Request Counter') }}</th>
                                <th data-column-id="dbSummaryCounter" data-searchable="false">{{ lang._('DB Summary Counter') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'ospfv3databasetable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="dbname">{{ lang._('Database') }}</th>
                                <th data-column-id="areaId">{{ lang._('Area') }}</th>
                                <th data-column-id="interface">{{ lang._('Interface') }}</th>
                                <th data-column-id="interfaceDescr">{{ lang._('interface name') }}</th>
                                <th data-column-id="type">{{ lang._('Type') }}</th>
                                <th data-column-id="lsId">{{ lang._('LS ID') }}</th>
                                <th data-column-id="advRouter">{{ lang._('Advertising Router') }}</th>
                                <th data-column-id="age">{{ lang._('Age') }}</th>
                                <th data-column-id="seqNum">{{ lang._('Sequence Number') }}</th>
                                <th data-column-id="payload">{{ lang._('Payload') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'bfdsummary' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="id">{{ lang._('SessionId') }}</th>
                                <th data-column-id="local" data-searchable="false">{{ lang._('LocalAddress') }}</th>
                                <th data-column-id="peer">{{ lang._('PeerAddress') }}</th>
                                <th data-column-id="status" data-searchable="false">{{ lang._('Status') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    {% break %}
                {% case 'tree' %}
                    <div class="searchbox">
                        <input
                            id="search-{{ tab['name'] }}"
                            type="text"
                            for="tree-{{tab['name']}}"
                            class="tree_search"
                            placeholder="{{ lang._('search') }}"
                        ></input>
                    </div>
                    <div class="treewidget" style="padding: 8px; overflow-y: scroll; height:400px;" id="tree-{{ tab['name'] }}"></div>
                    {% break %}
                {% case 'text' %}
                    <pre id="text-{{ tab['name'] }}"></pre>
                    {% break %}
            {% endswitch %}
        </div>
    {% endfor %}
</div>
