{#
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#}
<script src="/ui/js/moment-with-locales.min.js"></script>

<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">

            <div  class="col-sm-12">
                <div class="table-responsive">
                    <table id="grid-connectionstatus" class="table table-condensed table-hover table-striped table-responsive">
                        <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('Id') }}</th>
                            <th data-column-id="connection" data-type="string">{{ lang._('Connection') }}</th>
                            <th data-column-id="bind_interface" data-width="6em" data-type="string">{{ lang._('Interface') }}</th>
                            <th data-column-id="ssh_key" data-type="string">{{ lang._('SSH Key') }}</th>
                            <th data-column-id="forwards" data-formatter="forwards_list" data-type="string">{{ lang._('Forwards') }}</th>
                            <th data-column-id="status" data-formatter="status_list" data-type="string">{{ lang._('Status') }}</th>
                            <th data-column-id="actions" data-sortable="false" data-width="8em" data-type="string" data-formatter="actions">{{ lang._('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <br>
                </div>
            </div>

        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog_processing") }}

<style>
    #grid-connectionstatus ul.autossh_forwards, #grid-connectionstatus ul.autossh_status {
        padding-left: 1em;
    }
</style>

<script>
    
    $(document).ready(function() {
        
        $('#grid-connectionstatus').bootgrid('destroy');
        
        var gridopt = {
            ajax: true,
            url: '/api/autossh/service/connectionStatus',
            navigation: 1,
            selection: false,
            multiSelect: false,
            rowCount:[-1],
            ajaxSettings: {
                method: "GET",
                cache: false
            },
            formatters:{
                actions: function(column, row) {
                    var html = '<div>';
                    if (row.status.enabled !== true) {
                        html += '<span class="btn btn-xs btn-default disabled"><i class="fa fa-play fa-fw"></i></span>&nbsp;' +
                                '<span class="btn btn-xs btn-default disabled"><i class="fa fa-refresh fa-fw"></i></span>&nbsp;' +
                                '<span class="btn btn-xs btn-default disabled"><i class="fa fa-stop fa-fw"></i></span>';
                    }
                    else {
                        if (row.status.uptime !== null) {
                        html += '<span class="label label-opnsense label-opnsense-xs label-success"><i class="fa fa-play fa-fw"></i></span>&nbsp;' +
                                '<span data-service="autossh" data-action="restart" data-id="'+ row.uuid +'" class="btn btn-xs btn-default" onclick="service_action(this)"><i class="fa fa-refresh fa-fw"></i></span>&nbsp;' +
                                '<span data-service="autossh" data-action="stop" data-id="'+ row.uuid +'" class="btn btn-xs btn-default" onclick="service_action(this)"><i class="fa fa-stop fa-fw"></i></span>' ;
                        }
                        else {
                        html += '<span class="label label-opnsense label-opnsense-xs label-danger"><i class="fa fa-stop fa-fw"></i></span>&nbsp;' +
                                '<span data-service="autossh" data-action="start" data-id="'+ row.uuid +'" class="btn btn-xs btn-default" onclick="service_action(this)"><i class="fa fa-play fa-fw"></i></span>';
                        }
                    }
                    html += '</div>';
                    return html;
                },
                forwards_list: function(column, row) {
                    var html = '<ul class="autossh_forwards">';
                    for (var key in row.forwards) {
                        if (row.forwards[key].length > 0) {
                            html += '<li><b>' + key.charAt(0).toUpperCase() + ':</b> <code>' + row.forwards[key] + '</code></li>';
                        }
                    }
                    html += '</ul>';
                    return html;
                },
                status_list: function(column, row) {
                    var html = '<ul class="autossh_status">';
                    for (var key in row.status) {
                        if(row.status[key] !== null) {
                            var value = row.status[key];
                            var name = key.charAt(0).toUpperCase() + key.slice(1);
                            var span_class = '';
                            if(key==='uptime') {
                                value = moment.duration(parseInt(value), 'seconds').humanize();
                            }
                            else if(key==='starts') {
                                value = (parseInt(value) - 1);
                                name = '{{ lang._("Fails") }}';
                            }
                            else if(key==='last_healthy') {
                                if (parseInt(value) < 0) {
                                    value = '{{ lang._("pending") }}';
                                    span_class = 'text-warning';
                                } else {
                                    value = moment.duration(parseInt(value), 'seconds').asSeconds() + ' ' + '{{ lang._("sec ago") }}';
                                    if (parseInt(value) > 65) {
                                        span_class = 'text-danger';
                                    }
                                }
                                name = '{{ lang._("Healthy") }}';
                            }
                            html += '<li><span class="' + span_class + '"><b>' + name + ':</b> ' + value + '</span></li>';
                        }
                    }
                    html += '</ul>';
                    return html;
                }
            }
        };
        
        function reload_per_timecycle(milliseconds){
            $('#grid-connectionstatus').bootgrid('reload');
            setTimeout(function(){
                reload_per_timecycle(milliseconds);
            },milliseconds);
        };
        
        $("#grid-connectionstatus").bootgrid(gridopt);
        reload_per_timecycle(10000);
        
    });
    
    function service_action(element) {
        $("#OPNsenseStdWaitDialog").modal('show');
        $.post(
            '/api/autossh/service/' + $(element).data('action'), 
            {'id': $(element).data('id')}, 
            function(data) {
                location.reload(true);
            }
        );
    }
        
</script>
