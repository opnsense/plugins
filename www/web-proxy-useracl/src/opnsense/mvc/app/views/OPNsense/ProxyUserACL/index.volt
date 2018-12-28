{#
Copyright (C) 2017-2019 Smart-Soft

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

<link rel="stylesheet" href="/ui/css/OPNsense/ProxyUserACL/bootstrap-datetimepicker.css"/>
<script type="text/javascript" src="/ui/js/OPNsense/ProxyUserACL/moment-with-locales.min.js"></script>
<script type="text/javascript" src="/ui/js/OPNsense/ProxyUserACL/bootstrap-datetimepicker.min.js"></script>
<script>
    $(document).ready(function () {
        $("#grid-users").UIBootgrid(
            {
                'search': '/api/proxyuseracl/users/searchUser',
                'get': '/api/proxyuseracl/users/getUser/',
                'set': '/api/proxyuseracl/users/setUser/',
                'add': '/api/proxyuseracl/users/addUser/',
                'del': '/api/proxyuseracl/users/delUser/',
            }
        );

        $("#grid-arps").UIBootgrid(
            {
                'search': '/api/proxyuseracl/arps/searchArp',
                'get': '/api/proxyuseracl/arps/getArp/',
                'set': '/api/proxyuseracl/arps/setArp/',
                'add': '/api/proxyuseracl/arps/addArp/',
                'del': '/api/proxyuseracl/arps/delArp/',
            }
        );

        $("#grid-srcs").UIBootgrid(
            {
                'search': '/api/proxyuseracl/srcs/searchSrc',
                'get': '/api/proxyuseracl/srcs/getSrc/',
                'set': '/api/proxyuseracl/srcs/setSrc/',
                'add': '/api/proxyuseracl/srcs/addSrc/',
                'del': '/api/proxyuseracl/srcs/delSrc/',
            }
        );

        $("#grid-dsts").UIBootgrid(
            {
                'search': '/api/proxyuseracl/dsts/searchDst',
                'get': '/api/proxyuseracl/dsts/getDst/',
                'set': '/api/proxyuseracl/dsts/setDst/',
                'add': '/api/proxyuseracl/dsts/addDst/',
                'del': '/api/proxyuseracl/dsts/delDst/',
            }
        );

        $("#grid-domains").UIBootgrid(
            {
                'search': '/api/proxyuseracl/domains/searchDomain',
                'get': '/api/proxyuseracl/domains/getDomain/',
                'set': '/api/proxyuseracl/domains/setDomain/',
                'add': '/api/proxyuseracl/domains/addDomain/',
                'del': '/api/proxyuseracl/domains/delDomain/',
            }
        );

        $("#grid-agents").UIBootgrid(
            {
                'search': '/api/proxyuseracl/agents/searchAgent',
                'get': '/api/proxyuseracl/agents/getAgent/',
                'set': '/api/proxyuseracl/agents/setAgent/',
                'add': '/api/proxyuseracl/agents/addAgent/',
                'del': '/api/proxyuseracl/agents/delAgent/',
            }
        );

        $("#grid-mimes").UIBootgrid(
            {
                'search': '/api/proxyuseracl/mimes/searchMime',
                'get': '/api/proxyuseracl/mimes/getMime/',
                'set': '/api/proxyuseracl/mimes/setMime/',
                'add': '/api/proxyuseracl/mimes/addMime/',
                'del': '/api/proxyuseracl/mimes/delMime/',
            }
        );

        $("#grid-times").UIBootgrid(
            {
                'search': '/api/proxyuseracl/times/searchTime',
                'get': '/api/proxyuseracl/times/getTime/',
                'set': '/api/proxyuseracl/times/setTime/',
                'add': '/api/proxyuseracl/times/addTime/',
                'del': '/api/proxyuseracl/times/delTime/',
            }
        );

        $("#grid-acl").UIBootgrid(
            {
                'search': '/api/proxyuseracl/httpaccesses/searchACL',
                'get': '/api/proxyuseracl/httpaccesses/getACL/',
                'set': '/api/proxyuseracl/httpaccesses/setACL/',
                'add': '/api/proxyuseracl/httpaccesses/addACL/',
                'del': '/api/proxyuseracl/httpaccesses/delACL/',
                'toggle': '/api/proxyuseracl/httpaccesses/toggleACL/',
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

        $("#grid-acl").on("loaded.rs.jquery.bootgrid", function () {
            $("#grid-acl").find(".command-updown").on("click", function () {
                ajaxCall(url = "/api/proxyuseracl/httpaccesses/updownACL/" + $(this).data("row-id"), sendData = {"command": $(this).data("command")}, callback = function () {
                    $("#grid-acl").bootgrid("reload");
                });
            }).end();

            $("#grid-acl").find("*[data-action=add]").click(function () {
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

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        $("#Time\\.Start").datetimepicker({locale: '{{ locale }}', format: 'LT'});
        $("#Time\\.End").datetimepicker({locale: '{{ locale }}', format: 'LT'});
    });
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':mainForm]) }}
</ul>

<div class="tab-content content-box tab-content">
    <div id="subtab_users" class="tab-pane fade in active">
        <h1 class="text-center">{{ lang._('Users and groups') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="users-content">
            <tr>
                <td colspan="2">
                    <table id="grid-users" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogUsers">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string"
                                data-sortable="false">{{ lang._('Description') }}</th>
                            <th data-column-id="Server" data-type="string"
                                data-sortable="false">{{ lang._('Server') }}</th>
                            <th data-column-id="Group" data-width="10em" data-type="string"
                                data-sortable="false">{{ lang._('Group') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogUsers,'id':'DialogUsers','label':lang._('Edit users and groups for black and white lists')]) }}
    </div>
    <div id="subtab_mac" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('MAC-addresses') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="arps-content">
            <tr>
                <td colspan="2">
                    <table id="grid-arps" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogArps">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Description') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogArps,'id':'DialogArps','label':lang._('Edit MAC addresses for black and white lists')]) }}
    </div>
    <div id="subtab_src" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('Sources nets') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="srcs-content">
            <tr>
                <td colspan="2">
                    <table id="grid-srcs" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogSrcs">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Description') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogSrcs,'id':'DialogSrcs','label':lang._('Edit source IPs for black and white lists')]) }}
    </div>
    <div id="subtab_dst" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('Destination nets') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="dsts-content">
            <tr>
                <td colspan="2">
                    <table id="grid-dsts" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogDsts">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Description') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogDsts,'id':'DialogDsts','label':lang._('Edit destination IPs for black and white lists')]) }}
    </div>
    <div id="subtab_domains" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('Destination domains') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="domains-content">
            <tr>
                <td colspan="2">
                    <table id="grid-domains" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogDomains">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Description') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogDomains,'id':'DialogDomains','label':lang._('Edit domains for black and white lists')]) }}
    </div>
    <div id="subtab_agents" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('Browser user agents') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="agents-content">
            <tr>
                <td colspan="2">
                    <table id="grid-agents" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogAgents">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Description') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogAgents,'id':'DialogAgents','label':lang._('Edit Browser/user-agents for black and white lists')]) }}
    </div>
    <div id="subtab_mime" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('Mime types') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <table id="mimes-content">
            <tr>
                <td colspan="2">
                    <table id="grid-mimes" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogMimes">
                        <thead>
                        <tr>
                            <th data-column-id="Description" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Description') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogMimes,'id':'DialogMimes','label':lang._('Edit mime types for black and white lists')]) }}
    </div>
    <div id="subtab_time" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('Schedules') }}</h1>
        <div class="alert alert-warning">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
        <div id="times">
            <table id="times-content">
                <tr>
                    <td colspan="2">
                        <table id="grid-times" class="table table-condensed table-hover table-striped table-responsive"
                               data-editDialog="DialogTimes">
                            <thead>
                            <tr>
                                <th data-column-id="Description" data-type="string" data-sortable="false"
                                    data-visible="true">{{ lang._('Description') }}</th>
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
                                    <button data-action="deleteSelected" type="button"
                                            class="btn btn-xs btn-default"><span
                                                class="fa fa-trash-o"></span></button>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        {{ partial("layout_partials/base_dialog",['fields':formDialogTimes,'id':'DialogTimes','label':lang._('Edit Schedules')]) }}
    </div>
    <div id="subtab_http-access" class="tab-pane fade in">
        <h1 class="text-center">{{ lang._('HTTP access') }}</h1>
        <table id="acl-content">
            <tr>
                <td colspan="2">
                    <table id="grid-acl" class="table table-condensed table-hover table-striped table-responsive"
                           data-editDialog="DialogHttpaccesses">
                        <thead>
                        <tr>
                            <th data-column-id="Priority" data-width="10em" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Number') }}</th>
                            <th data-column-id="Black" data-width="10em" data-type="string"
                                data-sortable="false">{{ lang._('Black') }}</th>
                            <th data-column-id="Visible" data-type="string" data-sortable="false"
                                data-visible="true">{{ lang._('Name') }}</th>
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
        {{ partial("layout_partials/base_dialog",['fields':formDialogHttpaccesses,'id':'DialogHttpaccesses','label':lang._('Edit user/group white and black lists')]) }}
    </div>
    <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i
                id="reconfigureAct_progress" class=""></i></button>
</div>
