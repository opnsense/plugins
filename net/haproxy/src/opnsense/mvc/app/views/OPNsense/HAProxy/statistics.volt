{#

Copyright (C) 2016-2026 Frank Wall
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
        'use strict';

        const gridopt = {
            ajax: false,
            selection: false,
            multiSelect: false
        };
        $("#grid-status").bootgrid('destroy');
        $("#grid-status").bootgrid(gridopt);

        // build table rows safely from key/value data
        function buildInfoRows(data) {
            return Object.entries(data).map(function([key, value]) {
                return $("<tr/>").append(
                    $("<td/>").text(key),
                    $("<td/>").text(value)
                );
            });
        }

        // build table rows from an array of objects using a field list
        function buildGridRows(data, fields) {
            return Object.values(data).map(function(value) {
                const $tr = $("<tr/>");
                fields.forEach(function(field) {
                    $("<td/>").text(value[field] != null ? value[field] : '').appendTo($tr);
                });
                return $tr;
            });
        }

        // update info
        $("#update-info").click(function() {
            $('#processing-dialog').modal('show');
            ajaxGet("/api/haproxy/statistics/info/", {},
                function (data, status) {
                    $("#infolist > tbody").empty();
                    $("#infolist > thead").hide();
                    if (status == "success") {
                        $("#infolist > thead").show();
                        $("#infolist > tbody").append(buildInfoRows(data));
                    } else {
                        $("<tr/>").append(
                            $("<td/>").attr("colspan", 2).css("text-align", "center")
                                .html("<br/>{{ lang._('The statistics could not be fetched. Is HAProxy running?') }}<br/><br/>")
                        ).appendTo("#infolist > tbody");
                    }
                    $('#processing-dialog').modal('hide');
                }
            );
        });

        // update status
        $("#update-status").click(function() {
            $('#processing-dialog').modal('show');
            ajaxGet("/api/haproxy/statistics/counters/", {},
                function (data, status) {
                    if (status == "success") {
                        const fields = ["id", "pxname", "svname", "status", "lastchg", "weight", "act", "downtime"];
                        $("#status_nav").show();
                        $("#grid-status").bootgrid('destroy');
                        $("#grid-status > tbody").empty().append(buildGridRows(data, fields));
                        $("#grid-status").bootgrid(gridopt);
                    }
                    $('#processing-dialog').modal('hide');
                }
            );
        });

        // update counters
        $("#update-counters").click(function() {
            $('#processing-dialog').modal('show');
            ajaxGet("/api/haproxy/statistics/counters/", {},
                function (data, status) {
                    if (status == "success") {
                        const fields = ["id", "pxname", "svname", "qcur", "qmax", "qlimit", "rate", "rate_max", "rate_lim", "scur", "smax", "slim", "stot", "bin", "bout", "dreq", "dresp", "ereq", "econ", "eresp", "wretr", "wredis"];
                        $("#counters_nav").show();
                        $("#grid-counters").bootgrid('destroy');
                        $("#grid-counters > tbody").empty().append(buildGridRows(data, fields));
                        $("#grid-counters").bootgrid(gridopt);
                    }
                    $('#processing-dialog').modal('hide');
                }
            );
        });

        // update tables
        $("#update-tables").click(function() {
            $('#processing-dialog').modal('show');
            ajaxGet("/api/haproxy/statistics/tables/", {},
                function (data, status) {
                    if (status == "success") {
                        const fields = ["table", "type", "size", "used"];
                        $("#tables_nav").show();
                        $("#grid-tables").bootgrid('destroy');
                        $("#grid-tables > tbody").empty().append(buildGridRows(data, fields));
                        $("#grid-tables").bootgrid(gridopt);
                    }
                    $('#processing-dialog').modal('hide');
                }
            );
        });

        // initial load
        $("#update-info").click();
        $("#update-status").click();
        $("#update-counters").click();
        $("#update-tables").click();

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });
</script>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    <li class="active"><a data-toggle="tab" href="#info"><b>{{ lang._('Overview') }}</b></a></li>
    <li><a data-toggle="tab" href="#status" id="status_nav" style="display:none"><b>{{ lang._('Status') }}</b></a></li>
    <li><a data-toggle="tab" href="#counters" id="counters_nav" style="display:none"><b>{{ lang._('Counters') }}</b></a></li>
    <li><a data-toggle="tab" href="#tables" id="tables_nav" style="display:none"><b>{{ lang._('Stick Tables') }}</b></a></li>
</ul>

<div class="content-box tab-content">

    <div id="info" class="tab-pane fade in active">
        <!-- tab page "info" -->
        <table id="infolist" class="table table-striped table-condensed table-responsive">
            <thead>
                <tr>
                    <th>{{ lang._('Name') }}</th>
                    <th>{{ lang._('Value') }}</th>
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
                                <button id="update-info" type="button" class="btn btn-default">
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

    <div id="status" class="tab-pane fade in">
        <!-- tab page "status" -->
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
                                <button id="update-status" type="button" class="btn btn-default">
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

    <div id="counters" class="tab-pane fade in">
        <!-- tab page "counters" -->
        <table id="grid-counters" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
            <tr>
                <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('id') }}</th>
                <th data-column-id="pxname" data-type="string" data-identifier="true">{{ lang._('Backend/Frontend') }}</th>
                <th data-column-id="svname" data-type="string">{{ lang._('Server') }}</th>
                <th data-column-id="qcur" data-type="string">{{ lang._('Queue') }}</th>
                <th data-column-id="qmax" data-type="string">{{ lang._('Max') }}</th>
                <th data-column-id="qlimit" data-type="string">{{ lang._('Limit') }}</th>
                <th data-column-id="rate" data-type="string">{{ lang._('Session Rate') }}</th>
                <th data-column-id="rate_max" data-type="string">{{ lang._('Max') }}</th>
                <th data-column-id="rate_lim" data-type="string">{{ lang._('Limit') }}</th>
                <th data-column-id="scur" data-type="string">{{ lang._('Sessions') }}</th>
                <th data-column-id="smax" data-type="string">{{ lang._('Max') }}</th>
                <th data-column-id="slim" data-type="string">{{ lang._('Limit') }}</th>
                <th data-column-id="stot" data-type="string">{{ lang._('Total') }}</th>
                <th data-column-id="bin" data-type="string">{{ lang._('Bytes In') }}</th>
                <th data-column-id="bout" data-type="string">{{ lang._('Out') }}</th>
                <th data-column-id="dreq" data-type="string">{{ lang._('Denied Req') }}</th>
                <th data-column-id="dresp" data-type="string">{{ lang._('Resp') }}</th>
                <th data-column-id="ereq" data-type="string">{{ lang._('Errors Req') }}</th>
                <th data-column-id="econ" data-type="string">{{ lang._('Conn') }}</th>
                <th data-column-id="eresp" data-type="string">{{ lang._('Resp') }}</th>
                <th data-column-id="wretr" data-type="string">{{ lang._('Warnings Retr') }}</th>
                <th data-column-id="wredis" data-type="string">{{ lang._('Redis') }}</th>
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
                                <button id="update-counters" type="button" class="btn btn-default">
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

    <div id="tables" class="tab-pane fade in">
        <!-- tab page "tables" -->
        <table id="grid-tables" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
            <tr>
                <th data-column-id="table" data-type="string" data-identifier="true">{{ lang._('Table') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="size" data-type="string">{{ lang._('Size') }}</th>
                <th data-column-id="used" data-type="string">{{ lang._('Used') }}</th>
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
                                <button id="update-tables" type="button" class="btn btn-default">
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
