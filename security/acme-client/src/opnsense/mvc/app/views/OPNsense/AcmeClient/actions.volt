{#

Copyright (C) 2017-2019 Frank Wall
OPNsense® is Copyright © 2014-2015 by Deciso B.V.
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

        /***********************************************************************
         * link grid actions
         **********************************************************************/

        $("#grid-actions").UIBootgrid(
            {   search:'/api/acmeclient/actions/search',
                get:'/api/acmeclient/actions/get/',
                set:'/api/acmeclient/actions/update/',
                add:'/api/acmeclient/actions/add/',
                del:'/api/acmeclient/actions/del/',
                toggle:'/api/acmeclient/actions/toggle/',
                options: {
                    rowCount:[10,25,50,100,500,1000]
                }
            }
        );

        // hook into on-show event for dialog to extend layout.
        $('#DialogAction').on('shown.bs.modal', function (e) {
            $("#action\\.type").change(function(){
                var service_id = 'table_optional_' + $(this).val();
                $(".table_optional").hide();
                $("."+service_id).show();
            });
            $("#action\\.type").change(function(){
                $(".method_table").hide();
                $(".method_table_"+$(this).val()).show();
            });
            $("#action\\.type").change();
        });

        // Helpers for extra buttons and status divs
        function makeButton(label, buttonGroup, buttonClass) {
            var button = $('<button class="btn" type="button">'
                           + '<span class="btn-text"></span>'
                           + '<i class="fa fa-spinner fa-pulse" style="margin-left: 0.5em;"></i></button>');
            button.addClass(buttonClass || "btn-primary");
            $('.fa-spinner', button).hide();
            $('.btn-text', button).html(label);

            var targetContainer = $("#DialogAction .modal-footer"),
                targetId = "method_table_" + buttonGroup,
                target = $("." + targetId, targetContainer);

            if (!target.is('span')) {
                target = $('<span class="method_table" style="float: left"></span>')
                    .addClass(targetId)
                    .prependTo(targetContainer)
                    .hide();
            }

            return button.appendTo(target);
        }

        function makeStatusDiv(anchor, statusClass) {
            return $('<div class="alert method_table" role="alert" style="word-break: break-all"></div>')
                .appendTo($(anchor).closest("table").find("thead th[colspan=3]").first())
                .addClass(statusClass || 'alert-info')
                .hide();
        }

        // SFTP - Identity show button
        (function ($identityType) {
            var identityDiv = makeStatusDiv($identityType);

            makeButton("{{ lang._('Show Identity') }}", "upload_sftp", "btn-info")
                .click(function () {
                    identityDiv.hide();
                    var button = $(this);
                    button.prop('disabled', true).find(".fa-spinner").show();

                    ajaxCall("/api/acmeclient/actions/sftpGetIdentity", getFormData("DialogAction").action, function (data, status) {
                        button.prop('disabled', false).find(".fa-spinner").hide();

                        if (status === "success" && data.status === "ok") {
                            identityDiv.text(data.identity).show();
                        } else {
                            identityDiv.text("{{ lang._('Failed loading identity') }}").show();
                        }
                    });
                });

            // Hide when input changes that influences the identity.
            $identityType.change(function() {
                identityDiv.hide();
            });
        })($('#action\\.sftp_identity_type'));

        // SFTP - Connection test button
        (function ($user) {
            var statusDiv = makeStatusDiv($user, 'alert-success').html(
                '<div class="message"></div>'
                + '<div class="detail-enabler" style="cursor: pointer"><i class="fa fa-plus-square"></i></div>'
                + '<div class="detail" style="font-family: monospace"></div>');

            statusDiv.find(".detail-enabler").click(function() {
                $(".detail", statusDiv).show();
                $(this).hide();
            });

            var errors = [
                {cond: ["connect_failed", "invalid_parameters"], msg: "{{ lang._('Host or username not specified.') }}"},
                {cond: ["connect_failed", "host_not_resolved"], msg: "{{ lang._('Failed to resolve hostname.') }}"},
                {cond: ["connect_failed", "connection_refused"], msg: "{{ lang._('Connection to host refused.') }}"},
                {cond: ["connect_failed", "network_timeout"], msg: "{{ lang._('Connection timed out.') }}"},
                {cond: ["connect_failed", "network_unreachable"], msg: "{{ lang._('Host not reachable.') }}"},
                {cond: ["connect_failed", "host_not_trusted"], msg: "{{ lang._('Host cannot be trusted.') }}"},
                {cond: ["connect_failed", "permission_denied"], msg: "{{ lang._('Host does not permit a connection for the specified user & identity.') }}"},
                {cond: ["connect_failed"], msg: "{{ lang._('Failed to connect to host.') }}"},
                {cond: ["change_home_dir_failed"], msg: "{{ lang._('Failed to change the remote path.') }}"},
                {cond: ["permission_denied"], msg: "{{ lang._('Uploads are not allowed to the specified remote path.') }}"},
                {msg: "{{ lang._('Test failed, see details.') }}"},
            ];

            makeButton("{{ lang._('Test Connection') }}", "upload_sftp")
                .click(function () {
                    statusDiv.hide();
                    var button = $(this);
                    button.prop('disabled', true).find(".fa-spinner").show();

                    ajaxCall("/api/acmeclient/actions/sftpTestConnection", getFormData("DialogAction").action, function (data, status) {
                        button.prop('disabled', false).find(".fa-spinner").hide();

                        var message = "",
                            detail = "",
                            statusClass = "alert-warning";

                        if (status === "success") {
                            if (data.success === true) {
                                statusClass = "alert-success";
                                message = "{{ lang._('Connection and upload test succeeded.') }}"
                            } else {
                                detail = JSON.stringify(data, null, '  ').replace(/\\"/g, "'");

                                for (var i = 0; i < errors.length; i++) {
                                    var error = errors[i],
                                        matching = (error.cond || []).filter(function (condition) {
                                            return data[condition] === true;
                                        });

                                    if (matching.length === error.cond.length) {
                                        message = error.msg;
                                        break;
                                    }
                                }
                            }
                        } else {
                            message = "{{ lang._('Test not possible. Failed to talk to firewall backend.') }}";
                        }

                        $(".message", statusDiv).html(message);
                        $(".detail", statusDiv).text(detail).hide();
                        $(".detail-enabler", statusDiv).toggle(detail !== "");

                        statusDiv.removeClass("alert-success alert-warning").addClass(statusClass).show();
                    });
                });
        })($('#action\\.sftp_user'));

        // Eagerly hiding method tables to avoid contents popping up when opening the dialog for the first time.
        $(".method_table").hide();
    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#actions">{{ lang._('Automation') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="actions" class="tab-pane fade in active">
        <table id="grid-actions" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAction">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogAction,'id':'DialogAction','label':lang._('Edit Automation')])}}
