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

<script>
    $(document).ready(function () {
        [
            {"group": "users", "single": "User"},
            {"group": "macs", "single": "Mac"},
            {"group": "srcs", "single": "Src"},
            {"group": "dsts", "single": "Dst"},
            {"group": "domains", "single": "Domain"},
            {"group": "agents", "single": "Agent"},
            {"group": "mimes", "single": "Mime"},
            {"group": "times", "single": "Time"},
            {"group": "times", "single": "Time"}
        ].forEach(function (element) {
            $("#grid-" + element.group).UIBootgrid(
                {
                    'search': '/api/proxyuseracl/' + element.group + '/search' + element.single,
                    'get': '/api/proxyuseracl/' + element.group + '/get' + element.single + '/',
                    'set': '/api/proxyuseracl/' + element.group + '/set' + element.single + '/',
                    'add': '/api/proxyuseracl/' + element.group + '/add' + element.single + '/',
                    'del': '/api/proxyuseracl/' + element.group + '/del' + element.single + '/'
                }
            )
        });

        $("#grid-httpaccesses").UIBootgrid(
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
                        }
                    }
                }
            }
        );

        $("#grid-httpaccesses").on("loaded.rs.jquery.bootgrid", function () {
            $("#grid-httpaccesses").find(".command-updown").on("click", function () {
                ajaxCall(url = "/api/proxyuseracl/httpaccesses/updownACL/" + $(this).data("row-id"), sendData = {"command": $(this).data("command")}, callback = function () {
                    $("#grid-httpaccesses").bootgrid("reload");
                });
            }).end();

            $("#grid-httpaccesses").find("*[data-action=add]").click(function () {
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
    });
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':mainForm]) }}
</ul>

<div class="tab-content content-box tab-content">
    {% for tab in tabs %}
        <div id="subtab_{{ tab['name'] }}" class="tab-pane fade in {{ tab['active'] }}">
            <h1 class="text-center">{{ tab['title'] }}</h1>
            {% if tab['list'] == '1' %}
                <div class="alert alert-info">{{ lang._('Note:') }} {{ lang._('Use this lists in ACL rules.') }}</div>
            {% endif %}
            <table id="{{ tab['name'] }}-content">
                <tr>
                    <td colspan="2">
                        <table id="grid-{{ tab['name'] }}"
                               class="table table-condensed table-hover table-striped table-responsive"
                               data-editDialog="Dialog{{ tab['name'] }}">
                            <thead>
                            <tr>
                                {% if tab['list'] == '1' %}
                                    <th data-column-id="Description" data-type="string"
                                        data-sortable="false">{{ lang._('Description') }}</th>
                                {% else %}
                                    <th data-column-id="Priority" data-width="10em" data-type="string"
                                        data-sortable="false"
                                        data-visible="true">{{ lang._('Number') }}</th>
                                {% endif %}
                                {% for field in tab['fields'] %}
                                    <th data-column-id="{{ field['name'] }}" data-type="string"
                                        data-sortable="false"
                                        {% if field['width'] != '0' %}data-width="{{ field['width'] }}em"{% endif %}>{{ field['description'] }}</th>
                                {% endfor %}
                                {% if tab['list'] == '0' %}
                                    <th data-column-id="Visible" data-type="string" data-sortable="false"
                                        data-visible="true">{{ lang._('Name') }}</th>
                                    <th data-column-id="updown" data-width="7em" data-formatter="updown"
                                        data-sortable="false">{{ lang._('Priority') }}</th>
                                {% endif %}
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
            {{ partial("layout_partials/base_dialog",['fields':tab['formDialog'],'id':'Dialog%s'|format(tab['name']),'label':lang._('Edit %s for black and white lists')|format(tab['title'])]) }}
        </div>
    {% endfor %}
    <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i
                id="reconfigureAct_progress" class=""></i></button>
</div>
