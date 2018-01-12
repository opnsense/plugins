{#

    Copyright (C) 2017 Fabian Franz
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

function tor_update_status() {
    ajaxCall(url='/api/tor/service/status', sendData={}, callback=function(data, status) {
        updateServiceStatusUI(data['status']);
    });
}

$( document ).ready(function() {

    tor_update_status();
    ajaxCall(url='/api/tor/service/circuits', sendData={}, callback=function(data, status) {
        data = data['response'];
        var tmp = '';
        for (var name in data) {
            if (data.hasOwnProperty(name)) {
                tmp += '<tr><td>' + name +
                       '</td><td>' + data[name]['status'] +
                       '</td><td><ul>';
                hosts = data[name]['hosts'];
                for (var host_id in hosts) {
                    if (hosts.hasOwnProperty(host_id)) {
                        tmp += '<li>' + hosts[host_id]['host'] + ' - ' + hosts[host_id]['nickname'] + '</li>';
                    }
                }

                tmp += '</ul></td><td><ul>'

                flags = data[name]['flags'];
                for (var flag_id in flags) {
                    if (flags.hasOwnProperty(flag_id)) {
                        tmp += '<li>' + flag_id + ': ' + flags[flag_id].join(', ') + '</li>';
                    }
                }
                tmp += '</ul></td></tr>';
            }
        }
        $("#circuitstbdy").html(tmp);
    });
    ajaxCall(url="/api/tor/service/streams", sendData={}, callback=function(data, status) {
        data = data['response'];
        var tmp = '';
        for (var name in data) {
            if (data.hasOwnProperty(name)) {
                tmp += '<tr><td>' + data[name]['stream_id'] +
                       '</td><td>' + data[name]['stream_status'] +
                       '</td><td>' + data[name]['circuit_id'] +
                       '</td><td>' + data[name]['destination_host'] +
                       '</td><td>' + data[name]['destination_port'] +
                       '</td></tr>';
            }
        }
        $("#streamstbdy").html(tmp);
    });

});

</script>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#streams">{{ lang._('Streams') }}</a></li>
    <li><a data-toggle="tab" href="#circuits">{{ lang._('Circuits') }}</a></li>
</ul>

<div class="tab-content content-box tab-content" style="padding-bottom: 1.5em;">
    <div id="streams" class="tab-pane fade in active">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ lang._('Stream ID') }}</th>
                    <th>{{ lang._('Stream Status') }}</th>
                    <th>{{ lang._('Circuit ID') }}</th>
                    <th>{{ lang._('Destination Host') }}</th>
                    <th>{{ lang._('Destination Port') }}</th>
                </tr>
            </thead>
            <tbody id="streamstbdy"></tbody>
        </table>
    </div>
    <div id="circuits" class="tab-pane fade in">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ lang._('Circuit ID') }}</th>
                    <th>{{ lang._('Status') }}</th>
                    <th>{{ lang._('Hosts') }}</th>
                    <th>{{ lang._('Flags') }}</th>
                </tr>
            </thead>
            <tbody id="circuitstbdy"></tbody>
        </table>
    </div>
</div>
