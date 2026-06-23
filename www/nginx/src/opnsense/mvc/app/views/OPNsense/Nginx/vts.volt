{#
# Copyright (C) 2017-2018 Fabian Franz
# Copyright (C) 2015 YoungJoo.Kim <vozltx@gmail.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
#  1. Redistributions of source code must retain the above copyright notice,
#   this list of conditions and the following disclaimer.
#
#  2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script src="{{ cache_safe('/ui/js/nginx/lib/lodash.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/nginx/lib/backbone-min.js') }}"></script>
<link rel="stylesheet" href="{{ cache_safe('/ui/css/nginx/vts.css') }}" type="text/css" />

<div id="update" class="update">
    <strong>{{ lang._('Update Interval:') }}</strong>
    <select id="refresh">
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
        <option value="6">6</option>
        <option value="7">7</option>
        <option value="8">8</option>
    </select>
    <strong>{{ lang._('Seconds') }}</strong>
</div>

<div id="monitor"></div>

<script>
    function DataPoint() {
        this.data = [];
        this.msec = {
            last: undefined,
            period: undefined
        };
    }
    DataPoint.prototype.getValue =  function(key, value) {
        if (typeof this.data[key] === 'undefined') {
            this.data[key] = value;
            return 'n/a';
        } else {
            const increase = value - this.data[key];
            this.data[key] = value;
            return Math.floor(increase * 1000 / this.msec.period);
        }
    };
    DataPoint.prototype.refresh = function(time) {
        this.msec.period = time - this.msec.last;
        this.msec.last = time;
    };
    const UpstreamModel = Backbone.Model.extend({
        idAttribute: 'uuid'
    });
    const UpstreamCollection = Backbone.Collection.extend({
        url: '/api/nginx/settings/searchupstream',
        model: UpstreamModel,
        parse: function(response) {
            return response.rows;
        },
        getByInternalName(name) {
            name = name.replace('upstream', ''); // remove prefix
            const len = [8, 4, 4, 4, 12];
            let idx = 0;
            const parts = [];
            len.forEach(function (l) {
                parts.push(name.substring(idx, idx + l));
                idx += l;
            });
            const uuid = parts.join("-");
            return this.get(uuid);
        }
    });
    const uc = new UpstreamCollection();
    uc.fetch();
    const vtsStatusURI = "/api/nginx/service/vts";
    let vtsUpdateInterval = 1000, vtsUpdate;
    const vtsStatusVars = {
        titles: {
            main:"{{ lang._('Server main') }}",
            server:"{{ lang._('Server zones') }}",
            filter:"{{ lang._('Filters') }}",
            upstream:"{{ lang._('Upstreams') }}",
            cache:"{{ lang._('Caches') }}"
        },
        ids: {
            main:"mainZones",
            server:"serverZones",
            filter:"filterZones",
            upstream:"upstreamZones",
            cache:"cacheZones"
        },
        classes: {
            table: "table table-condensed table-hover table-striped",
            div: "panel"
        }
    };

    const aPs = new DataPoint();
    function formatTime(msec) {
        const ms = 1000, m = 60, h = m * m, d = h * 24;
        let s = '';
        if (msec < ms) {
            return msec + 'ms';
        }
        if (msec < (ms * m)) {
            return Math.floor(msec / ms) + '.' + Math.floor((msec % ms) / 10) + 's';
        }
        const days = Math.floor(msec / (d * ms));
        if (days) {
            s += days + "d ";
        }
        const hours = Math.floor((msec % (d * ms)) / (h * ms));
        if (days || hours) {
            s += hours + "h ";
        }
        const minutes = Math.floor(((msec % (d * ms)) % (h * ms)) / (m * ms));
        if (days || hours || minutes) {
            s += minutes + "m ";
        }
        const seconds = Math.floor((((msec % (d * ms)) % (h * ms)) % (m * ms)) / ms);
        return s + seconds + "s";
    }
    function formatAvailability(b,d) {
        if (!b && !d) {
            return "up";
        } else if (d) {
            return "down";
        } else {
            return "backup";
        }
    }
    function htmlTag(t, v) {
        const ts = t.split(' ');
        return `<${t}>${v}</${ts[0]}>`;
    }
    function aHe(t, v) {
        let o = '';
        if (Array.isArray(v)) {
            for (let i = 0; i < v.length; i++)  {
                o += htmlTag(t, v[i]);
            }
        } else {
            o = htmlTag(t, v);
        }
        return o;
    }
    function templateServerHeader(cache) {
        const heads = [];
        heads[0] = aHe('th rowspan="2"', "{{ lang._('Zone') }}") +
            aHe('th colspan="3"', "{{ lang._('Requests') }}") +
            aHe('th colspan="6"', "{{ lang._('Responses') }}") +
            aHe('th colspan="4"', "{{ lang._('Traffic') }}");
        if (cache) {
            heads[0] += aHe('th colspan="9"', "{{ lang._('Cache') }}");
        }
        heads[1] = aHe('th', [ "{{ lang._('Total') }}",
            "{{ lang._('Req/s') }}",
            "{{ lang._('Time') }}",
            "{{ lang._('1xx') }}",
            "{{ lang._('2xx') }}",
            "{{ lang._('3xx') }}",
            "{{ lang._('4xx') }}",
            "{{ lang._('5xx') }}",
            "{{ lang._('Total') }}",
            "{{ lang._('Sent') }}",
            "{{ lang._('Rcvd') }}",
            "{{ lang._('Sent/s') }}",
            "{{ lang._('Rcvd/s') }}" ]);
        if (cache) {
            heads[1] += aHe('th', [ "{{ lang._('Miss') }}",
                "{{ lang._('Bypass') }}",
                "{{ lang._('Expired') }}",
                "{{ lang._('Stale') }}",
                "{{ lang._('Updating') }}",
                "{{ lang._('Revalidated') }}",
                "{{ lang._('Hit') }}",
                "{{ lang._('Scarce') }}",
                "{{ lang._('Total') }}" ]);
        }
        return aHe('thead', aHe('tr', heads[0]) + aHe('tr', heads[1]));
    }
    function templateUpstreamHeader() {
        const heads = [];
        heads[0] = aHe('th rowspan="2"', "{{ lang._('Server') }}") +
            aHe('th rowspan="2"', "{{ lang._('State') }}") +
            aHe('th rowspan="2"', "{{ lang._('Response Time') }}") +
            aHe('th rowspan="2"', "{{ lang._('Weight') }}") +
            aHe('th rowspan="2"', "{{ lang._('MaxFails') }}") +
            aHe('th rowspan="2"', "{{ lang._('FailTimeout') }}") +
            aHe('th colspan="3"', "{{ lang._('Requests') }}") +
            aHe('th colspan="6"', "{{ lang._('Responses') }}") +
            aHe('th colspan="4"', "{{ lang._('Traffic') }}");
        heads[1] = aHe('th', [ "{{ lang._('Total') }}",
            "{{ lang._('Req/s') }}",
            "{{ lang._('Time') }}",
            "{{ lang._('1xx') }}",
            "{{ lang._('2xx') }}",
            "{{ lang._('3xx') }}",
            "{{ lang._('4xx') }}",
            "{{ lang._('5xx') }}",
            "{{ lang._('Total') }}",
            "{{ lang._('Sent') }}",
            "{{ lang._('Rcvd') }}",
            "{{ lang._('Sent/s') }}",
            "{{ lang._('Rcvd/s') }}" ]);
        return aHe('thead', aHe('tr', heads[0]) + aHe('tr', heads[1]));
    }
    function templateCacheHeader() {
        var heads = [];
        heads[0] = aHe('th rowspan="2"', 'Zone') +
            aHe('th colspan="2"', "{{ lang._('Size') }}") +
            aHe('th colspan="4"', "{{ lang._('Traffic') }}") +
            aHe('th colspan="9"', "{{ lang._('Cache') }}");
        heads[1] = aHe('th', [ "{{ lang._('Capacity') }}",
            "{{ lang._('Used') }}",
            "{{ lang._('Sent') }}",
            "{{ lang._('Rcvd') }}",
            "{{ lang._('Sent/s') }}",
            "{{ lang._('Rcvd/s') }}",
            "{{ lang._('Miss') }}",
            "{{ lang._('Bypass') }}",
            "{{ lang._('Expired') }}",
            "{{ lang._('Stale') }}",
            "{{ lang._('Updating') }}",
            "{{ lang._('Revalidated') }}",
            "{{ lang._('Hit') }}",
            "{{ lang._('Scarce') }}",
            "{{ lang._('Total') }}" ]);
        return aHe('thead', aHe('tr', heads[0]) + aHe('tr', heads[1]));
    }
    function templateMainZone(it) {
        let o, head, body;
        const heads = [];
        const bodys = [];
        heads[0] = aHe('th rowspan="2"', "{{ lang._('Host') }}") +
            aHe('th rowspan="2"', "{{ lang._('Version') }}") +
            aHe('th rowspan="2"', "{{ lang._('Uptime') }}") +
            aHe('th colspan="4"', "{{ lang._('Connections') }}") +
            aHe('th colspan="4"', "{{ lang._('Requests') }}") +
            aHe('th colspan="4"', "{{ lang._('Shared memory') }}");
        heads[1] = aHe('th', [ "{{ lang._('active') }}",
            "{{ lang._('reading') }}",
            "{{ lang._('writing') }}",
            "{{ lang._('waiting') }}",
            "{{ lang._('accepted') }}",
            "{{ lang._('handled') }}",
            "{{ lang._('Total') }}",
            "{{ lang._('Req/s') }}",
            "{{ lang._('name') }}",
            "{{ lang._('max Size') }}",
            "{{ lang._('used Size') }}",
            "{{ lang._('used Node') }}" ]);
        head = aHe('thead', aHe('tr', heads[0]) + aHe('tr', heads[1]));
        bodys[0] = aHe('td', [ aHe('strong', it.hostName), it.nginxVersion, formatTime(it.nowMsec - it.loadMsec),
            it.connections.active, it.connections.reading, it.connections.writing,
            it.connections.waiting, it.connections.accepted, it.connections.handled,
            it.connections.requests, aPs.getValue('main.connections.requests', it.connections.requests),
            aHe('strong', it.sharedZones.name), byteFormat(it.sharedZones.maxSize), byteFormat(it.sharedZones.usedSize),
            it.sharedZones.usedNode]);
        body = aHe('tbody', aHe('tr', bodys[0]));
        o = aHe('h2', vtsStatusVars.titles.main) + aHe(`table class="${vtsStatusVars.classes.table}"`, `${head}${body}`);
        o = aHe(`div id="${vtsStatusVars.ids.main}" class="${vtsStatusVars.classes.div}"`, o);
        return o;
    }
    function templateServerZone(filter, group, id, cache) {
        let s, o = '';
        for(const name in filter) {
            if (filter.hasOwnProperty(name)) {
                const zone = filter[name];
                const uniq = `${id}.${group}.${name}`;
                let flag = '';
                let responseCount = 0;
                let responseTotal = 0;
                let cacheCount = 0;
                let cacheTotal = 0;
                flag = (group.indexOf("country") !== -1 && name.length === 2)
                    ? `<img class="flag flag-${name.toLowerCase()}" alt="flag ${name.toLowerCase()}" />`
                    : '';
                s = aHe('th', flag + name) +
                    aHe('td', [(zone.requestCounter + zone.overCounts['maxIntegerSize'] * zone.overCounts['requestCounter']),
                        aPs.getValue(`${uniq}.requestCounter`, zone.requestCounter), formatTime(zone.requestMsec)
                    ]);
                for(const code in zone.responses) {
                    if (!zone.responses.hasOwnProperty(code)) continue;
                    responseCount = zone.responses[code] + zone.overCounts['maxIntegerSize'] * zone.overCounts[code];
                    responseTotal += responseCount;
                    s += aHe('td', responseCount);
                    if(code === '5xx') break;
                }
                s += aHe('td', [responseTotal,
                    byteFormat(zone.outBytes + zone.overCounts['maxIntegerSize'] * zone.overCounts['outBytes']),
                    byteFormat(zone.inBytes + zone.overCounts['maxIntegerSize'] * zone.overCounts['inBytes']),
                    byteFormat(aPs.getValue(`${uniq}.outBytes`, zone.outBytes)),
                    byteFormat(aPs.getValue(`${uniq}.inBytes`, zone.inBytes))
                ]);
                if (cache) {
                    let i = 0;
                    for(const code in zone.responses) {
                        if(i++ < 5) continue;
                        if (!zone.responses.hasOwnProperty(code)) continue;
                        cacheCount = (zone.responses[code] + zone.overCounts['maxIntegerSize'] * zone.overCounts[code]);
                        cacheTotal += cacheCount;
                        s += aHe('td', cacheCount);
                    }
                    s += aHe('td', cacheTotal);
                }
                o += aHe('tr', s);
            }
        }
        return o;
    }
    function templateUpstreamZone(filter, group, id) {
        let n = 0;
        let s = '';
        let o = '';
        while (n < filter.length) {
            const peer = filter[n];
            const uniq = `${id}.${group}.${peer.server}`;
            let responseCount = 0;
            let responseTotal = 0;
            n++;
            s = aHe('th', peer.server) +
                aHe('td', [formatAvailability(peer.backup, peer.down), formatTime(peer.responseMsec),
                    peer.weight, peer.maxFails, peer.failTimeout,
                    (peer.requestCounter + peer.overCounts['maxIntegerSize'] * peer.overCounts['requestCounter']),
                    aPs.getValue(`${uniq}.requestCounter`, peer.requestCounter),
                    formatTime(peer.requestMsec)
                ]);
            for(const code in peer.responses) {
                if (peer.responses.hasOwnProperty(code)) {
                    responseCount = peer.responses[code] + peer.overCounts['maxIntegerSize'] * peer.overCounts[code];
                    responseTotal += responseCount;
                    s += aHe('td', responseCount);
                }
            }
            s += aHe('td', [responseTotal,
                byteFormat(peer.outBytes + peer.overCounts['maxIntegerSize'] * peer.overCounts['outBytes']),
                byteFormat(peer.inBytes + peer.overCounts['maxIntegerSize'] * peer.overCounts['inBytes']),
                byteFormat(aPs.getValue(`${uniq}.outBytes`, peer.outBytes)),
                byteFormat(aPs.getValue(`${uniq}.inBytes`, peer.inBytes))
            ]);
            o += aHe('tr', s);
        }
        return o;
    }
    function templateCacheZone(filter, group, id) {
        let s;
        let o = '';
        for(const name in filter) {
            if (filter.hasOwnProperty(name)) {
                const zone = filter[name];
                const uniq = `${id}.${group}.${name}`;
                let cacheCount = 0;
                let cacheTotal = 0;
                s = aHe('th', name) +
                    aHe('td', [byteFormat(zone.maxSize),
                        byteFormat(zone.usedSize),
                        byteFormat(zone.outBytes + zone.overCounts['maxIntegerSize'] * zone.overCounts['outBytes']),
                        byteFormat(zone.inBytes + zone.overCounts['maxIntegerSize'] * zone.overCounts['inBytes']),
                        byteFormat(aPs.getValue(`${uniq}.outBytes`, zone.outBytes)),
                        byteFormat(aPs.getValue(`${uniq}.inBytes`, zone.inBytes))
                    ]);
                for (const code in zone.responses) {
                    if (zone.responses.hasOwnProperty(code)) {
                        cacheCount = zone.responses[code] + zone.overCounts['maxIntegerSize'] * zone.overCounts[code];
                        cacheTotal += cacheCount;
                        s += aHe('td', cacheCount);
                    }
                }
                s += aHe('td', cacheTotal);
                o += aHe('tr', s);
            }
        }
        return o;
    }
    function haveCache(it) {
        const key = "*";
        if (typeof it.serverZones[key] == "undefined") {
            return true;
        }
        return Object.keys(it.serverZones[key].responses).length > 5;
    }
    function template(it) {
        aPs.refresh(it.nowMsec);
        const bodys = [];
        let tmp = '';
        let out, head, body, cache;
        /* main */
        out = templateMainZone(it);
        /* serverZones */
        cache = haveCache(it);
        head = templateServerHeader(cache);
        bodys[0] = templateServerZone(it.serverZones, 'server', vtsStatusVars.ids.server, cache);
        body = aHe('tbody', bodys[0]);
        out += aHe(`div id="${vtsStatusVars.ids.server}" class="${vtsStatusVars.classes.div}"`, aHe('h2', vtsStatusVars.titles.server) + aHe(`table class="${vtsStatusVars.classes.table}"`, head + body));
        /* filterZones */
        if (vtsStatusVars.ids.filter in it) {
            tmp = '';
            for (const group in it.filterZones) {
                if (it.filterZones.hasOwnProperty(group)) {
                    const filter = it.filterZones[group];
                    head = templateServerHeader(cache);
                    bodys[0] = templateServerZone(filter, group, vtsStatusVars.ids.filter, cache);
                    body = aHe('tbody', bodys[0]);
                    tmp += aHe('h3', group) + aHe(`table class="${vtsStatusVars.classes.table}"`, head + body);
                }
            }
            out += aHe(`div id="${vtsStatusVars.ids.filter}"`, aHe('h2', vtsStatusVars.titles.filter) + tmp);
        }
        /* upstreamZones */
        if (vtsStatusVars.ids.upstream in it) {
            tmp = '';
            for (let group in it.upstreamZones) {
                if (it.upstreamZones.hasOwnProperty(group)) {
                    const filter = it.upstreamZones[group];
                    head = templateUpstreamHeader();
                    bodys[0] = templateUpstreamZone(filter, group, vtsStatusVars.ids.upstream);
                    body = aHe('tbody', bodys[0]);
                    let g2 = uc.getByInternalName(group);
                    if (g2) {
                        if (g2.get('description')) {
                            group = g2.get('description');
                        }
                    }
                    tmp += aHe('h3', group) + aHe(`table class="${vtsStatusVars.classes.table}"`, head + body);
                }
            }
            out += aHe(`div id="${vtsStatusVars.ids.upstream}" class="${vtsStatusVars.classes.div}"`, aHe('h2', vtsStatusVars.titles.upstream) + tmp);
        }
        /* cacheZones */
        if (vtsStatusVars.ids.cache in it) {
            head = templateCacheHeader();
            bodys[0] = templateCacheZone(it.cacheZones, 'cache', vtsStatusVars.ids.cache);
            body = aHe('tbody', bodys[0]);
            out += aHe(`div id="${vtsStatusVars.ids.cache}"`,
                aHe('h2', vtsStatusVars.titles.cache) + aHe(`table class="${vtsStatusVars.classes.table}"`, head + body));
        }
        return out;
    }
    const monitor = $('#monitor');
    function vtsGetData() {
        jQuery.get(vtsStatusURI).done(function (d) {
            monitor.html(template(d));
        });
    }
    function vtsSetInterval(msec) {
        clearInterval(vtsUpdate);
        vtsUpdate = setInterval(vtsGetData, msec);
    }
    $('#refresh').on('change', function () {
       vtsSetInterval($(this).val() * 1000);
    });
    vtsGetData();
    vtsSetInterval(vtsUpdateInterval);
</script>
