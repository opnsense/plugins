{#

Copyright © 2017 Fabian Franz
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

function table_tr_kv(key, value) {
    return "<tr><td>" + key + "</td><td>" + value + "</td></tr>";
}
function table_tr_transpose(key, key_name, list, arr) {
    data =  "<tr><td>" + key + "</td>";
    for (var i = 0; i < list.length; i++) {
      data += "<td>" + arr[list[i]][key_name] + "</td>";
    }
    data += "</tr>";
    return data;
}

function result_to_html(elements) {
    var output = '';
    for (var element_cnt = 0; element_cnt < elements.length; element_cnt++) {
        var element = elements[element_cnt];
        output += "{{ lang._('%sResult %s%s') }}".replace('%s','<h2>').replace('%s',element_cnt + 1).replace('%s','</h2>');
        output += '<table class="table table-striped"><tr><td>{{ lang._('Interface') }}</td><td>' + element.interface + '</td></tr>' +
        '<tr><td>{{ lang._('Start Time') }}</td><td>' + element.start_time + '</td></tr>' +
        '<tr><td>{{ lang._('Port') }}</td><td>' + element.port + '</td></tr></table>';

        // only if test did already run
        if ('result' in element) {
            var result = element.result,
                start = result.start,
                connection = start.connected[0],
                intervals = result.intervals,
                test_end = result.end,
                cpu = test_end.cpu_utilization_percent;
            // General
            output += "<h3>{{ lang._('General') }}</h3>";
            output += '<table class="table table-striped">';
            output += table_tr_kv("{{ lang._('Time') }}", start.timestamp.time);
            output += table_tr_kv("{{ lang._('Duration') }}", start.test_start.duration);
            output += table_tr_kv("{{ lang._('Block Size') }}", start.test_start.blksize);
            output += "</table>";
            // connection
            output += "<h3>{{ lang._('Connection') }}</h3>";
            output += '<table class="table table-striped">';
            output += table_tr_kv("{{ lang._('Local Host') }}", connection.local_host);
            output += table_tr_kv("{{ lang._('Local Port') }}", connection.local_port);
            output += table_tr_kv("{{ lang._('Remote Host') }}", connection.remote_host);
            output += table_tr_kv("{{ lang._('Remote Port') }}", connection.remote_port);
            output += "</table>";
            // CPU Usage
            output += "<h3>{{ lang._('CPU Usage') }}</h3>";
            output += '<table class="table table-striped">';
            output += table_tr_kv("{{ lang._('Host Total') }}", cpu.host_total.toFixed(2));
            output += table_tr_kv("{{ lang._('Host User') }}", cpu.host_user.toFixed(2));
            output += table_tr_kv("{{ lang._('Host System') }}", cpu.host_system.toFixed(2));
            output += table_tr_kv("{{ lang._('Remote Total') }}", cpu.remote_total.toFixed(2));
            output += table_tr_kv("{{ lang._('Remote User') }}", cpu.remote_user.toFixed(2));
            output += table_tr_kv("{{ lang._('Remote System') }}", cpu.remote_system.toFixed(2));
            output += "</table>";
            // performance data
            output += "<h3>{{ lang._('Performance Data') }}</h3>";
            output += '<table class="table table-striped">';
            var fields = ['sum_sent', 'sum_received'];
            output += table_tr_transpose("{{ lang._('Start') }}","start",fields, test_end);
            output += table_tr_transpose("{{ lang._('End') }}","end",fields, test_end);
            output += table_tr_transpose("{{ lang._('Seconds') }}","seconds",fields, test_end);
            output += table_tr_transpose("{{ lang._('Bytes') }}","bytes",fields, test_end);
            output += table_tr_transpose("{{ lang._('Bits Per Second') }}","bits_per_second",fields, test_end);
            output += "</table>";
        }
    }
    $('#resultcontainer').html(output);
}

function update_results() {
    ajaxCall(url="/api/iperf/instance/query", sendData={}, callback=function(data,status) {
        result_to_html(data);
    });
}

$( document ).ready(function() {
    var data_get_map = {'instance': '/api/iperf/instance/get'};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('select').selectpicker('refresh');
    });

    ajaxCall(url="/api/iperf/service/status", sendData={}, callback=function(data,status) {
        updateServiceStatusUI(data['result']);
    });
    update_results();
    setInterval(update_results, 10000);

    // link save button to API set action
    $("#create_instance_action").click(function(){
        $("#create_instance_action_progress").addClass("fa fa-spinner fa-pulse");
        saveFormToEndpoint(url="/api/iperf/instance/set", formid='instance',callback_ok=function(){
            $("#create_instance_action_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });
});

</script>

<div class="content-box" style="padding-bottom: 1.5em;">
    {{ partial("layout_partials/base_form",['fields': instance_settings,'id':'instance'])}}
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="create_instance_action" type="button"><b>{{ lang._('Create Instance') }}</b> <i id="create_instance_action_progress"></i></button>
    </div>
</div>

<div id="resultcontainer" class="content-box"></div>
