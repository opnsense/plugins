{#
Copyright (C) 2017 Smart-Soft

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

    $(document).ready(function () {
        grid = $("#grid-acl").UIBootgrid(
            {
                'search': '/api/proxyuseracl/settings/searchACL',
                'get': '/api/proxyuseracl/settings/getACL/',
                'set': '/api/proxyuseracl/settings/setACL/',
                'add': '/api/proxyuseracl/settings/addACL/',
                'del': '/api/proxyuseracl/settings/delACL/',
                'toggle': '/api/proxyuseracl/settings/toggleACL/',
                options: {
                    formatters: {
                        "commands": function (column, row) {
                            return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-copy\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
                        },
                        "rowtoggle": function (column, row) {
                            if (parseInt(row[column.id], 2) == 1) {
                                return "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                            } else {
                                return "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                            }
                        },
                        "boolean": function (column, row) {
                            if (parseInt(row[column.id], 2) == 1) {
                                return "<span class=\"fa fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                            } else {
                                return "<span class=\"fa fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                            }
                        },
                        "updown": function (column, row) {
                            return "<button type=\"button\" class=\"btn btn-xs btn-default command-updown\" data-row-id=\"" + row.uuid + "\" data-command=\"up\"><span class=\"fa fa-arrow-up\"></span></button> " +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-updown\" data-row-id=\"" + row.uuid + "\" data-command=\"down\"><span class=\"fa fa-arrow-down\"></span></button>";
                        },
                    }
                }
            }
        );

        grid.on("loaded.rs.jquery.bootgrid", function () {
            grid.find(".command-updown").on("click", function () {
                ajaxCall(url = "/api/proxyuseracl/settings/updownACL/" + $(this).data("row-id"), sendData = {"command": $(this).data("command")}, callback = function () {
                    $("#grid-acl").bootgrid("reload");
                });
            }).end();

            grid.find("*[data-action=add]").click(function () {
                $("#btn_DialogACL_save_progress").removeClass("fa fa-spinner fa-pulse");
                $("#btn_DialogACL_save").click(function () {
                    $("#btn_DialogACL_save_progress").addClass("fa fa-spinner fa-pulse");
                    var old_handleFormValidation = window.handleFormValidation;
                    window.handleFormValidation = function (parent, validationErrors) {
                        $("#btn_DialogACL_save_progress").removeClass("fa fa-spinner fa-pulse");
                        window.handleFormValidation = old_handleFormValidation;
                        handleFormValidation(parent, validationErrors);
                    }
                });
            }).end();
        });

        $("#reconfigureAct").click(function () {
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/proxy/service/reconfigure", sendData = {}, callback = function (data, status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");

                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "Error reconfiguring proxy",
                        message: data['status'],
                        draggable: true
                    });
                }
            });
        });
    });
</script>

<div id="acl">
    <table id="acl-content">
        <tr>
            <td colspan="2">
                <table id="grid-acl" class="table table-condensed table-hover table-striped table-responsive"
                       data-editDialog="DialogACL">
                    <thead>
                    <tr>
                        <th data-column-id="Priority" data-width="10em" data-type="string" data-sortable="false"
                            data-visible="true">{{ lang._('Number') }}</th>
                        <th data-column-id="Group" data-width="10em" data-type="string"
                            data-sortable="false">{{ lang._('Group') }}</th>
                        <th data-column-id="Black" data-width="10em" data-type="string"
                            data-sortable="false">{{ lang._('Black') }}</th>
                        <th data-column-id="Name" data-type="string"
                            data-sortable="false">{{ lang._('Name') }}</th>
                        <th data-column-id="Domains" data-type="string" data-sortable="false"
                            data-visible="true">{{ lang._('Domains') }}</th>
                        <th data-column-id="updown" data-width="7em" data-formatter="updown"
                            data-sortable="false">{{ lang._('Priority') }}</th>
                        <th data-column-id="commands" data-width="7em" data-formatter="commands"
                            data-sortable="false">{{ lang._('Commands') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td></td>
                        <td>
                            <button data-action="add" type="button" class="btn btn-xs btn-default"><span
                                        class="fa fa-plus"></span></button>
                            <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span
                                        class="fa fa-trash-o"></span></button>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
    </table>
</div>
<button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b><i
            id="reconfigureAct_progress" class=""></i></button>

{{ partial("layout_partials/base_dialog",['fields':formDialogACL,'id':'DialogACL','label':lang._('Edit user/group white and black lists')]) }}
