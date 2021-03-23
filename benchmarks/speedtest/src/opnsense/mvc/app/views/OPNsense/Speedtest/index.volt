{# Copyright 2021 Miha Kralj 
*    Redistribution and use in source and binary forms, with or without
*    modification, are permitted provided that the following conditions are met:
*
*    1. Redistributions of source code must retain the above copyright notice,
*       this list of conditions and the following disclaimer.
*
*    2. Redistributions in binary form must reproduce the above copyright
*       notice, this list of conditions and the following disclaimer in the
*       documentation and/or other materials provided with the distribution.
*
*    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
*    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
*    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
*    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
*    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
*    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
*    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
*    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
*    POSSIBILITY OF SUCH DAMAGE.
#}
<div class="content-box">
    <table class="table table-condensed">
        <tbody>
            <tr>
                <td>
                    <h2 style="margin-top:1ex;">Statistics</h2>
                </td>
                <td style="width: 78%; text-align: right; background-color: inherit; border-top-width: 0px;">
                    <small>help </small>
                    <i class="fa fa-toggle-off text-success" style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
            </tr>
            <tr>
                <td style="width:30%">Speedtest probes:</td>
                <td>
                    <div id="stat_samples">0</div>
                    <div class="hide" data-for="help_for_speedprobes"><small>Number of recorded speedtest results</small></div>
                </td>
            </tr>
            <tr>
                <td>Average Latency:</td>
                <td>
                    <div id="stat_latency">0.00 ms (min: 0.00 ms, max: 0.00 ms)</div>
                    <div class="hide" data-for="help_for_latency"><small>Average time it takes for ping request to reach speedtest server and come back.</small></div>
                </td>
            </tr>
            <tr>
                <td>Average Download speed:</td>
                <td>
                    <div id="stat_download">0 Mbps (min: 0 Mbps, max: 0 Mbps)</div>
                    <div class="hide" data-for="help_for_download"><small>Average receiving bandwidth (lowest recorded probe, highest recorded probe)</small></div>
                </td>
            </tr>
            <tr>
                <td>Average Upload speed:</td>
                <td>
                    <div id="stat_upload">0 Mbps (min: 0 Mbps, max: 0 Mbps)</div>
                    <div class="hide" data-for="help_for_upload"><small>Average sending bandwidth (lowest recorded probe, highest recorded probe)</small></div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<h2>Run speedtest</h2>
<div class="content-box">
    <div class="content-box-main collapse in" id="system_information-container" style="display:inline">
        <table class="table table-condensed">
            <thead>
                <tr>
                    <td style="width:30%">
                        <select id="speedlist" name="serverid">
                        <option value="0">Fetching available Speedtest servers...</option>
                        /select>
                    </td>
                    <td style="width:30%"><button class="btn btn-primary" id="reportAct" type="button">
                <b>{{ lang._('socket test') }}</b> <i id="reportAct_progress"></i></button></td>
                    <td style="width:30%"><button class="btn btn-primary" id="reportPyAct" type="button">
                <b>{{ lang._('http test') }}</b> <i id="reportPyAct_progress"></i></button></td>
                </tr>
            </thead>
            <tbody id="test_results" style="display:none">
                <tr>
                    <td>Latency (ping)</td>
                    <td id="latency">0 ms</td>
                    <td id="pylatency">0 ms</td>
                </tr>
                <tr>
                    <td>Download speed</td>
                    <td id="dlspeed">0 Mbps</td>
                    <td id="pydlspeed">0 Mbps</td>
                </tr>
                <tr>
                    <td>Upload speed</td>
                    <td id="ulspeed">0 Mbps</td>
                    <td id="pyulspeed">0 Mbps</td>
                </tr>
                <tr>
                    <td>Speedtest server</td>
                    <td>
                        <div id="host1"></div>
                        <div id="ISP1"></div>
                        <div id="ISP2"></div>
                        <div id="ISP3"></div>
                    </td>
                    <td>
                        <div id="pyhost1"></div>
                        <div id="pyhost3"></div>
                        <div id="pyhost4"></div>
                        <div id="pyhost5"></div>
                    </td>
                </tr>
                <tr>
                    <td>Client</td>
                    <td>
                        <div id="client4"></div>
                        <div id="client5"></div>
                    </td>
                    <td>
                        <div id="pyclient"></div>
                    </td>
                </tr>
                <tr>
                    <td>Result id</td>
                    <td>
                        <div id="result"></div>
                    </td>
                    <td>
                        <div id="pyresult">
                        </div>
                    </td>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="hide" data-for="help_for_speedprobes"><a href="/ui/cron">Configure OPNsense Cron task</a> to run speedtest at a regular interval</div>
<br/>

<div class="content-box" id="logs">
    <table id="grid-log" class="table table-condensed">
        <thead>
            <tr>
                <th style="text-align:left">
                    <a href="#"><i class="fa fa-toggle-off text-danger" id="show_advanced_log"></i></a>
                    <small id="togglelog">show log</small>
                </th>
            </tr>
            <tr><th>
                <div id="log_buttons" data-advanced="true" style="display: none;">
                    <a href="/api/speedtest/download/csv">
                        <button class="btn btn-primary" id="downloadAct" type="button">
                            <b>Export log</b>
                        </button>
                    </a>
                    <button class="btn btn-primary" id="deletelogAct" type="button">
                        <b>{{ lang._('Clear log') }}</b> <i id="deletelogAct_progress"></i></button>
                </div>
            </th></tr>
            <tr id="log_head" data-advanced="true" style="display: none;">
                <th data-column-id="Timestamp" class="text-left" style="width:7em;">Timestamp (GMT)</th>
                <th data-column-id="ServerId" class="text-left" style="width:3em;">Server id</th>
                <th data-column-id="ServerName" class="text-left" style="width:12em;">Server name</th>
                <th data-column-id="Latency" class="text-left" style="width:2em;">Latency</th>
                <th data-column-id="DlSpeed" class="text-left" style="width:3em;">DlSpeed</th>
                <th data-column-id="UlSpeed" class="text-left" style="width:3em;">UlSpeed</th>
            </tr>
        </thead>
        <tbody id="log_block" data-advanced="true" style="display: none;">
        </tbody>
    </table>
</div>
<br>

<script>
    function stat_reload() {
        ajaxCall(url = "/api/speedtest/service/stat", sendData = {}, callback = function(data, status) {
            let l = JSON.parse(data['response'])
            $('#stat_samples').html("<b>" + l.samples + "<\/b>")
            $('#stat_latency').html("<b>" + l.latency.avg + " ms<\/b> (min: " + l.latency.min + " ms, max: " + l.latency.max + " ms)")
            $('#stat_download').html("<b>" + l.download.avg + " Mbps<\/b> (min: " + l.download.min + " Mbps, max: " + l.download.max + " Mbps)")
            $('#stat_upload').html("<b>" + l.upload.avg + " Mbps<\/b> (min: " + l.upload.min + " Mbps, max: " + l.upload.max + " Mbps)")
        });
    };
    function log_reload() {
        ajaxCall(url = "/api/speedtest/service/log", sendData = {}, callback = function(data, status) {
            let l = JSON.parse(data['response'])
            for (var i = 0; i < l.length; i++) {
                var obj = obj +
                    "<tr><td class=\"text-left\" style=\"\">" + l[i][0] + "</td>" +
                    "<td class=\"text-left\" style=\"\">" + l[i][2] + "</td>" +
                    "<td class=\"text-left\" style=\"\">" + l[i][3] + "</td>" +
                    "<td class=\"text-left\" style=\"\">" + parseFloat(l[i][4]).toFixed(2) + "</td>" +
                    "<td class=\"text-left\" style=\"\">" + parseFloat(l[i][6]).toFixed(2) + "</td>" +
                    "<td class=\"text-left\" style=\"\">" + parseFloat(l[i][7]).toFixed(2) + "</td></tr>"
            }
            $('#log_block').html(obj);
        });
    };
    $(document).ready(function() {
        // run on doc load
        stat_reload();
        log_reload();
        ajaxCall(url = "/api/speedtest/service/list", sendData = {}, callback = function(data, status) {
            let l = JSON.parse(data['response']).servers
            let list = ""
            $('#speedlist').text("")
            for (var i = 0; i < l.length; i++) {
                $('#speedlist').append("<option value=\"" + l[i].id + "\">" + "(" + l[i].id + ") " + l[i].name + ", " + l[i].location + "<\/option>");
            }

        });
    });
    $(function() {
        // python button
        $("#reportPyAct").click(function() {
            $("#reportPyAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/speedtest/service/test1/" + $('#speedlist').val(), sendData = {}, callback = function(data, status) {
                $("#reportPyAct_progress").removeClass("fa fa-spinner fa-pulse");
                $("#test_results").attr("style", "display:content");
                let py = JSON.parse(data['response'])
                $("#pylatency").text(py.ping + " ms");
                $("#pydlspeed").text((py.download / 1000000).toFixed(2) + " Mbps");
                $("#pyulspeed").text((py.upload / 1000000).toFixed(2) + " Mbps");
                $("#reportPyAct_progress").removeClass("fa fa-spinner fa-pulse");
                $("#pyhost1").text(py.server.host);
                $("#pyhost2").text("IPv4: ");
                $("#pyhost3").text("id: " + py.server.id);
                $("#pyhost4").text(py.server.name);
                $("#pyhost5").text(py.server.country);
                $("#pyclient").text("Public IP: " + py.client.ip);
                let pyresulturl = py.share.slice(0, py.share.length - 4)
                let pyresult = pyresulturl.slice(pyresulturl.lastIndexOf("/") + 1)
                $("#pyresult").html("<a href=\"" + pyresulturl + "\"  target=\"_blank\">" + pyresult + "</a>");
                stat_reload();
                log_reload();
            });
        });
        // Oookla binary button
        $("#reportAct").click(function() {
            $("#reportAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/speedtest/service/test/" + $('#speedlist').val(), sendData = {}, callback = function(data, status) {
                $("#reportAct_progress").removeClass("fa fa-spinner fa-pulse");
                $("#test_results").attr("style", "display:content");
                let r = JSON.parse(data['response'])
                $("#latency").text(r.ping.latency + " ms (" + r.ping.jitter + " ms jitter)");
                $("#dlspeed").text((r.download.bandwidth / 125000).toFixed(2) + " Mbps");
                $("#ulspeed").text((r.upload.bandwidth / 125000).toFixed(2) + " Mbps");
                $("#host1").text(r.server.host + ":" + r.server.port);
                $("#host2").text("IPv4: " + r.server.ip);
                $("#ISP1").text("id: " + r.server.id + " (" + r.server.name + ")");
                $("#ISP2").text(r.server.location);
                $("#ISP3").text(r.server.country);
                $("#client4").text("Public IP: " + r.interface.externalIp);
                $("#client5").text("VPN detected: " + r.interface.isVpn);
                $("#result").html("<a href=\"" + r.result.url + "\"  target=\"_blank\">" + r.result.id + "</a>");
                stat_reload();
                log_reload();
            });
        });
        // Delete Log
        $("#deletelogAct").click(function() {
            $("#deletelogAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/speedtest/service/deletelog/", sendData = {}, callback = function(data, status) {
                stat_reload();
                log_reload();
                $("#deletelogAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
            $('#log_block').html("");
            log_reload();
        });
    });
</script>