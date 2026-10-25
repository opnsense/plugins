{#

Copyright (C) 2021-2026 Frank Wall
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

        /**
         * show HAProxy config
         */
        function update_showconf() {
            ajaxCall("/api/haproxy/export/config/", {}, function(data, status) {
                if (data['response'] && data['response'].trim()) {
                    $("#showconf").text(data['response']);
                } else {
                    $("#showconfempty").append('<br><span class="conf-message"> {{ lang._('Config file not found. Run a syntax check to create it.') }}</span><br>');
                    $("#showconf").hide();
                }
            });
        }
        update_showconf();

        /**
         * show HAProxy config diff
         */
        function update_showdiff() {
            ajaxCall("/api/haproxy/export/diff/", {}, function(data, status) {
                const diffClasses = {'+': 'diff-add', '-': 'diff-remove', '@': 'diff-hunk'};
                let diff = '';
                if (data['response'] && data['response'].trim()) {
                    data['response'].split("\n").forEach(function(line) {
                        const cssClass = diffClasses[line.substring(0, 1)] || 'diff-context';
                        diff += `<span class="${cssClass}">${$('<span/>').text(line).html()}</span><br>`;
                    });
                } else {
                    diff = '<br><span class="diff-context"> {{ lang._('New and old config files are identical.') }}</span><br>';
                }
                $("#showdiff").append(diff);
            });
        }
        update_showdiff();

        /**
         * download HAProxy config
         */
        $('[id*="exportbtn"]').each(function(){
            $(this).click(function(){
                const type = $(this).data("type");
                ajaxGet("/api/haproxy/export/download/" + type + "/", {}, function(data, status){
                    if (data.filename !== undefined) {
                        const link = $('<a></a>')
                            .attr('href', 'data:' + data.filetype + ';base64,' + data.content)
                            .attr('download', data.filename)
                            .appendTo('body');

                        link.ready(function() {
                            link.get(0).click();
                            link.empty();
                        });
                    }
                });
            });
        });

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click();
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click();
        });
    });
</script>

<style>
    .diff-add     { color: #3bbb33; white-space: pre-wrap; font-family: monospace; }
    .diff-remove  { color: #c13928; white-space: pre-wrap; font-family: monospace; }
    .diff-hunk    { color: #3bb9c3; white-space: pre-wrap; font-family: monospace; }
    .diff-context { white-space: pre-wrap; font-family: monospace; }
    .conf-message { white-space: pre-wrap; font-family: monospace; }
</style>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#export"><b>{{ lang._('Config Export') }}</b></a></li>
    <li><a data-toggle="tab" href="#diff">{{ lang._('Config Diff') }}</a></li>
</ul>

<div class="content-box tab-content">

    <div id="export" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div id="showconfempty"></div>
            <pre id="showconf"></pre>
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="exportbtn" data-type="config" type="button"><b>{{ lang._('Download Config') }}</b></button>
                <button class="btn btn-primary" id="exportbtn" data-type="all" type="button"><b>{{ lang._('Download All') }}</b></button>
            </div>
        </div>
    </div>

    <div id="diff" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div id="showdiff"></div>
        </div>
    </div>

</div>

{{ partial("layout_partials/base_dialog_processing") }}
