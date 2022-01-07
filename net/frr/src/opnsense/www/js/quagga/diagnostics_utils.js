/*
 * Copyright (C) 2015-2022 Deciso B.V.
 * Copyright (C) 2022 Marc Leuser
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
        boolean: function (column, row) {
            if (row[column.id]) {
                return "<span class=\"fa fa-fw fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
            } else {
                return "";
            }
        },
        ospf_route_type: function(column, row) {
            let result = '';

            row[column.id].split(' ').forEach(function(routeType) {
                let translatedRouteType = translateOSPFTerm(routeType)

                if (translatedRouteType == routeType) {
                    result += routeType;
                } else {
                    result += '<abbr title="' + translatedRouteType + '">' + routeType + '</abbr>';
                }

                result += ' ';
            });

            return result;
        }
    }
};

/**
 * take the BGP routes as delivered by the diagnostics API and transform them into a bootgrid-compatible format
 */
 function transformBGPRoutes(data) {
    let routes = [];

    for(let network in data) {
        data[network].forEach(function (route) {
            route.nexthops.forEach(function (nexthop) {
                routes.push({
                    valid: route['valid'],
                    best: route['bestpath'],
                    internal: (route['pathFrom'] === 'internal'),
                    network: network,
                    nexthop: nexthop['ip'],
                    metric: route['metric'],
                    locprf: route['locPrf'],
                    weight: route['weight'],
                    path: (route['path'] === '' ? 'Internal' : route['path']),
                    origin: route['origin']
                });
            });
        });
    }

    return routes;
}

/**
 * take the diagnostics API's output and transform it into a printable HTML format (skip routes)
 */
function transformBGPDetails(data) {
    let html = '';

    for(let key in data) {
        if (key === 'routes') {
            continue;
        }

        html += key + ": " + data[key] + "<br>";
    }

    return html;
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
* take the OSPF routes as delivered by the API and transform them into a bootgrid-compatible format
*/
function transformOSPFRoutes(data) {
    let routes = [];

    for(let network in data) {
        data[network].nexthops.forEach(function(nexthop) {
            routes.push({
                type: data[network]['routeType'],
                network: network,
                cost: data[network]['cost'],
                area: data[network]['area'],
                via: (typeof nexthop['via'] !== 'undefined' ? nexthop['ip'] : 'Directly Attached'),
                viainterface: (typeof nexthop['via'] !== 'undefined' ? nexthop['via'] : nexthop['directly attached to'])
            });
        });
    }

    return routes;
}
