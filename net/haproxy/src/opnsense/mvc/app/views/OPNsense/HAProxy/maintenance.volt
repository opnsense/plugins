{#

Copyright (C) 2016 Frank Wall
OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
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
    $( document ).ready(function() {
        var gridopt = {
            ajax: false,
            selection: false,
            multiSelect: false
        };
        $("#grid-status").bootgrid('destroy');
        $("#grid-status").bootgrid(gridopt);

        // update server
        $("#update-server").click(function() {
            $('#processing-dialog').modal('show');
            ajaxGet(url = "/api/haproxy/statistics/counters/", sendData={},
                    callback = function (data, status) {
                        if (status == "success") {
                            // status
                            $("#status_nav").show();
                            $("#grid-status").bootgrid('destroy');
                            var html = [];
                            $.each(data, function (key, value) {
                                var fields = ["id", "pxname", "svname", "status", "lastchg", "weight", "act", "downtime"];
                                tr_str = '<tr>';
                                for (var i = 0; i < fields.length; i++) {
                                    if (value[fields[i]] != null) {
                                        tr_str += '<td>' + value[fields[i]] + '</td>';
                                    } else {
                                        tr_str += '<td></td>';
                                    }
                                }
                                tr_str += '</tr>';
                                html.push(tr_str);
                            });
                            $("#grid-status > tbody").html(html.join(''));
                            $("#grid-status").bootgrid(gridopt);
                        }
                        $('#processing-dialog').modal('hide');
                    }
            );
        });

        // initial load
        $("#update-server").click();
    });
</script>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    <li class="active"><a data-toggle="tab" href="#server"><b>{{ lang._('Server') }}</b></a></li>
</ul>

<div class="content-box tab-content">
    <div id="server" class="tab-pane fade in active">
        <!-- tab page "server" -->
        <table id="grid-status" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
            <tr>
                <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('id') }}</th>
                <th data-column-id="pxname" data-type="string">{{ lang._('Proxy') }}</th>
                <th data-column-id="svname" data-type="string">{{ lang._('Server') }}</th>
                <th data-column-id="status" data-type="string">{{ lang._('Status') }}</th>
                <th data-column-id="lastchg" data-type="string">{{ lang._('Last Change') }}</th>
                <th data-column-id="weight" data-type="string">{{ lang._('Weight') }}</th>
                <th data-column-id="act" data-type="string">{{ lang._('Active') }}</th>
                <th data-column-id="downtime" data-type="string">{{ lang._('Downtime') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div  class="col-sm-12">
            <div class="row">
                <table class="table">
                    <tr>
                        <td>
                            <div class="pull-right">
                                <button id="update-server" type="button" class="btn btn-default">
                                    <span>{{ lang._('Refresh') }}</span>
                                    <span class="fa fa-refresh"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <hr/>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog_processing") }}
