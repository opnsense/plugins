{#

OPNsense® is Copyright © 2014 – 2022 by Deciso B.V.
Copyright (C) 2022 Marc Leuser
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

<script src="{{ cache_safe('/ui/js/quagga/diagnostics_utils.js') }}"></script>

<style>
    .searchbox {
      margin: 8px;
    }
  
    .node-selected {
        font-weight: bolder;
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

        {% for tab in tabs %}
            {% if tab['type'] in ['bgptable', 'ospftable'] %}
                /**
                 * initialize bootgrid table for {{ tab['tabhead'] }}
                 */
                $("#grid-{{ tab['name'] }}").bootgrid(gridopt);
            {% endif %}

            /**
             * register refresh event handler for {{ tab['tabhead'] }}
             */
            $("#refresh-{{ tab['name'] }}").click(function () { 
            {% switch tab['type'] %}
                {% case 'bgptable' %}
                    $("#grid-{{ tab['name'] }}").bootgrid('clear');

                    ajaxGet("{{ tab['endpoint'] }}", {}, function (data, status) {
                        if (status == "success") {
                            let routes = transformBGPRoutes(data['response']['routes']);
                            let details = transformBGPDetails(data['response']);

                            $("#grid-{{ tab['name'] }}").bootgrid('append', routes);
                            $("#details-{{ tab['name'] }}").html(details);
                        }
                    });
                    {% break %}
                {% case 'ospftable' %}
                    $("#grid-{{ tab['name'] }}").bootgrid('clear');

                    ajaxGet("{{ tab['endpoint'] }}", {}, function (data, status) {
                        if (status == "success") {
                            let routes = transformOSPFRoutes(data['response']);

                            $("#grid-{{ tab['name'] }}").bootgrid('append', routes);
                        }
                    });
                    {% break %}
                {% case 'ospfneighbors' %}
                    $("#grid-{{ tab['name'] }}").bootgrid('clear');

                    ajaxGet("{{ tab['endpoint'] }}", {}, function (data, status) {
                        if (status == "success") {
                            let neighbors = transformOSPFNeighbors(data['response']['neighbors']);

                            $("#grid-{{ tab['name'] }}").bootgrid('append', neighbors);
                        }
                    });
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
             * initial data fetch
             */
            $("#refresh-{{ tab['name'] }}").click();
        {% endfor %}

        /**
         * reset view to default tab
         */
        $('a[href="#{{ default_tab }}"]').click();
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
        <div id="{{ tab['name'] }}" class="tab-pane fade in{% if loop.first %} active{% endif %}">
            {% switch tab['type'] %}
                {% case 'bgptable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="valid" data-searchable="false" data-formatter="boolean" data-width="4%" data-sortable="false">{{ lang._('Valid') }}</th>
                                <th data-column-id="best" data-searchable="false" data-formatter="boolean" data-width="4%" data-sortable="false">{{ lang._('Best') }}</th>
                                <th data-column-id="internal" data-searchable="false" data-formatter="boolean" data-width="5%" data-sortable="false">{{ lang._('Internal') }}</th>
                                <th data-column-id="network" data-width="15%">{{ lang._('Network') }}</th>
                                <th data-column-id="nexthop" data-width="25%">{{ lang._('Next Hop') }}</th>
                                <th data-column-id="metric" data-searchable="false" data-width="5%">{{ lang._('Metric') }}</th>
                                <th data-column-id="locprf" data-searchable="false" data-width="5%">{{ lang._('LocPrf') }}</th>
                                <th data-column-id="weight" data-searchable="false" data-width="6%">{{ lang._('Weight') }}</th>
                                <th data-column-id="path" data-width="21%">{{ lang._('Path') }}</th>
                                <th data-column-id="origin" data-width="10%">{{ lang._('Origin') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-xs-12">
                        <div class="pull-left" data-toggle="popover">
                            <small id="details-{{ tab['name'] }}"></small>
                        </div>
                    </div>
                    {% break %}
                {% case 'ospftable' %}
                    <div class="col-sm-12">
                        <table id="grid-{{ tab['name'] }}" class="table table-condensed table-hover table-striped table-responsive">
                            <thead>
                            <tr>
                                <th data-column-id="type" data-formatter="ospf_route_type">{{ lang._('Type') }}</th>
                                <th data-column-id="network">{{ lang._('Network') }}</th>
                                <th data-column-id="cost">{{ lang._('Cost') }}</th>
                                <th data-column-id="area">{{ lang._('Area') }}</th>
                                <th data-column-id="via">{{ lang._('Via') }}</th>
                                <th data-column-id="viainterface">{{ lang._('Via interface') }}</th>
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
                                <th data-column-id="priority">{{ lang._('Priority') }}</th>
                                <th data-column-id="state">{{ lang._('State') }}</th>
                                <th data-column-id="deadTimeMsecs">{{ lang._('Dead Time') }} &lsqb;ms&rsqb;</th>
                                <th data-column-id="address">{{ lang._('Address') }}</th>
                                <th data-column-id="ifaceName">{{ lang._('Interface') }}</th>
                                <th data-column-id="retransmitCounter">{{ lang._('Retransmit Counter') }}</th>
                                <th data-column-id="requestCounter">{{ lang._('Request Counter') }}</th>
                                <th data-column-id="dbSummaryCounter">{{ lang._('DB Summary Counter') }}</th>
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
