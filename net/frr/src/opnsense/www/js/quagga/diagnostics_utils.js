/*
 * Copyright (C) 2015-2022 Deciso B.V.
 * Copyright (C) 2023 Marc Bartelt
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

'use strict';

/**
 * shared options for bootgrid tables
 */
let gridopt = {
    formatters: {
        boolean: function(column, row) {
            if (row[column.id]) {
                return '<span class="fa fa-fw fa-check" data-value="1" data-row-id="' + row.uuid + '"></span>';
            } else {
                return '';
            }
        },
        ospf_route_type: function(column, row) {
            let result = '';

            row[column.id].split(' ').forEach(function(routeType) {
                let translatedRouteType = translateOSPFTerm(routeType)

                if (translatedRouteType === routeType) {
                    result += routeType;
                } else {
                    result += '<abbr title="' + translatedRouteType + '">' + routeType + '</abbr>';
                }

                result += ' ';
            });

            return result;
        },
        general_route_code: function(column, row) {
            let result = row.code;
            let protocol = translateZebraCode(row.code);

            if(typeof(protocol) !== 'string') result = '<abbr title="' + protocol['long'] + '">' + protocol['short'] + '</abbr>';
            if(row.selected) result += ' <abbr title="Selected">&gt;</abbr>';
            if(row.installed) result += ' <abbr title="FIB">&ast;</abbr>';

            return result;
        }
    }
};

/**
 * zebra route codes - translation table
 */
function translateZebraCode(data) {
    let tr = [];

    // routing table tab
    tr['kernel'] = {short: 'K', long: 'Kernel'};
    tr['connected'] = {short: 'C', long: 'Connected'};
    tr['bgp'] = {short: 'B', long: 'BGP'};
    tr['ospf'] = {short: 'O', long: 'OSPF'};
    tr['ospf6'] = {short: 'O', long: 'OSPFv3'};

    return ((data in tr) ? tr[data] : data);
}



/**
 * OSPF terms and abbreviations - translation table
 **/
function translateOSPFTerm(data) {
    let tr = [];

    // routing table tab
    tr['N'] = 'Network';
    tr['R'] = 'Router';
    tr['IA'] = 'OSPF inter area';
    tr['N1'] = 'OSPF NSSA external type 1';
    tr['N2'] = 'OSPF NSSA external type 2';
    tr['E1'] = 'OSPF external type 1';
    tr['E2'] = 'OSPF external type 2';

    return ((data in tr) ? tr[data] : data);
}


/**
 * tree view: resize widget on window resize
 */
function resizeTreeWidget() {
    let new_height = $(".page-foot").offset().top -
                     ($(".page-content-head").offset().top + $(".page-content-head").height()) - 160;
    $(".treewidget").height(new_height);
    $(".treewidget").css('max-height', new_height + 'px');
}

/**
 * tree view: delayed live-search
 */
let apply_tree_search_timer = null;
function treeSearchKeyUp() {
    let sender = $(this);

    clearTimeout(apply_tree_search_timer);
    apply_tree_search_timer = setTimeout(function() {
        let searchTerm = sender.val().toLowerCase();
        let target = $("#"+sender.attr('for'));
        let tree = target.tree("getTree");
        let selected = [];
        if (tree !== null) {
            tree.iterate((node) => {
                let matched = false;
                if (searchTerm !== "") {
                    matched = node.name.toLowerCase().includes(searchTerm);
                    if (!matched && typeof node.value === 'string') {
                        matched = node.value.toLowerCase().includes(searchTerm);
                    }
                }
                node["selected"] = matched;

                if (matched) {
                    selected.push(node);
                    if (node.isFolder()) {
                        node.is_open = true;
                    }
                    let parent = node.parent;
                    while (parent) {
                        parent.is_open = true;
                        parent = parent.parent;
                    }
                } else if (node.isFolder()) {
                    node.is_open = false;
                }

                return true;
            });
            target.tree("refresh");
            if (selected.length > 0) {
                target.tree('scrollToNode', selected[0]);
            }
        }
    }, 500);
}

/**
 * jqtree expects a list + dict type structure, transform key value store into expected output
 * https://mbraak.github.io/jqTree/#general
 */
 function dict_to_tree(node, path) {
    // some entries are lists, try use a name for the nodes in that case
    let node_name_keys = ['name', 'interface-name'];
    let result = [];
    if ( path === undefined) {
        path = "";
    } else {
        path = path + ".";
    }
    for (let key in node) {
        if (typeof node[key] === "function") {
            continue;
        }
        let item_path = path + key;
        if (node[key] instanceof Object) {
            let node_name = key;
            for (let idx=0; idx < node_name_keys.length; ++idx) {
                if (/^(0|[1-9]\d*)$/.test(node_name) && node[key][node_name_keys[idx]] !== undefined) {
                    node_name = node[key][node_name_keys[idx]];
                    break;
                }
            }
            result.push({
                name: node_name,
                id: item_path,
                children: dict_to_tree(node[key], item_path)
            });
        } else {
            result.push({
                name: key,
                value: node[key],
                id: item_path
            });
        }
    }
    return result;
}
