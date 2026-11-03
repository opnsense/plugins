<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#status">{{ lang._('Status') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="settings" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':formGeneral,'id':'frm_general_settings'])}}

        <hr />
        <h3>{{ lang._('Hosts to enable') }}</h3>
        <table id="grid-hosts" class="table table-condensed table-hover table-striped" data-editDialog="DialogHost"
            data-editAlert="RTSP Helper Host Change">
            <thead>
                <tr>
                    <th data-column-id="ip" data-type="string" data-identifier="true">{{ lang._('IP Address') }}</th>
                    <th data-column-id="port" data-type="string">{{ lang._('Port') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands')
                        }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span
                                class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>

        <hr />
        <h3>{{ lang._('User specified permissions') }}</h3>
        <table id="grid-permissions" class="table table-condensed table-hover table-striped"
            data-editDialog="DialogPermission" data-editAlert="RTSP Helper Permission Change">
            <thead>
                <tr>
                    <th data-column-id="network" data-type="string" data-identifier="true">{{ lang._('Network') }}</th>
                    <th data-column-id="port" data-type="string">{{ lang._('Port / Range') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands')
                        }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span
                                class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i
                    id="saveAct_progress"></i></button>
            <br /><br />
        </div>
    </div>

    <div id="status" class="tab-pane fade">
        <table id="grid-status" class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                    <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="proto" data-type="string">{{ lang._('Protocol') }}</th>
                    <th data-column-id="source" data-type="string">{{ lang._('Source') }}</th>
                    <th data-column-id="destination" data-type="string">{{ lang._('Destination') }}</th>
                    <th data-column-id="port" data-type="string">{{ lang._('Port') }}</th>
                    <th data-column-id="redirect_to" data-type="string">{{ lang._('Redirect To') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div class="col-md-12">
            <br />
            <button class="btn btn-primary" id="refreshAct" type="button"><b>{{ lang._('Refresh') }}</b></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogHost,'id':'DialogHost','label':lang._('Edit Host')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPermission,'id':'DialogPermission','label':lang._('Edit
Permission')])}}

<script>
    $(document).ready(function () {
        var data_get_map = { 'frm_general_settings': "/api/rtsphelper/settings/get" };
        mapDataToFormUI(data_get_map).done(function (data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#grid-hosts").bootgrid({
            ajax: true,
            selection: true,
            multiSelect: true,
            rowCount: [10, 25, 50, -1],
            url: '/api/rtsphelper/settings/searchHost',
            formatters: {
                "commands": function (column, row) {
                    return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-delete bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
                }
            }
        }).on("loaded.rs.jquery.bootgrid", function (e) {
            $("#grid-hosts").find(".command-edit").on("click", function (e) {
                var uuid = $(this).data("row-id");
                mapDataToFormUI({ 'DialogHost': "/api/rtsphelper/settings/getHost/" + uuid }).done(function () {
                    $("#DialogHost").attr('data-uuid', uuid);
                    $("#DialogHost").modal({ backdrop: 'static', keyboard: false });
                });
            });
            $("#grid-hosts").find(".command-delete").on("click", function (e) {
                var uuid = $(this).data("row-id");
                stdDialogConfirm('{{ lang._('Confirm') }}', '{{ lang._('Do you want to delete this host ? ') }}', function () {
                    ajaxCall(url = "/api/rtsphelper/settings/delHost/" + uuid, sendData = {}, callback = function (data, status) {
                        $("#grid-hosts").bootgrid("reload");
                    });
                });
            });
        });

        $("#grid-hosts").find("tfoot button[data-action='add']").on("click", function (e) {
            $("#DialogHost").attr('data-uuid', '');
            $("#DialogHost").modal({ backdrop: 'static', keyboard: false });
            $("#DialogHost").find("input").val("");
        });

        $("#btn_DialogHost_save").unbind('click').click(function () {
            var uuid = $("#DialogHost").attr('data-uuid');
            var url = "/api/rtsphelper/settings/addHost";
            if (uuid) {
                url = "/api/rtsphelper/settings/setHost/" + uuid;
            }
            saveFormToEndpoint(url = url, formid = 'DialogHost', callback_ok = function () {
                $("#DialogHost").modal('hide');
                $("#grid-hosts").bootgrid("reload");
            });
        });

        $("#grid-permissions").bootgrid({
            ajax: true,
            selection: true,
            multiSelect: true,
            rowCount: [10, 25, 50, -1],
            url: '/api/rtsphelper/settings/searchPermission',
            formatters: {
                "commands": function (column, row) {
                    return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-delete bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
                }
            }
        }).on("loaded.rs.jquery.bootgrid", function (e) {
            $("#grid-permissions").find(".command-edit").on("click", function (e) {
                var uuid = $(this).data("row-id");
                mapDataToFormUI({ 'DialogPermission': "/api/rtsphelper/settings/getPermission/" + uuid }).done(function () {
                    $("#DialogPermission").attr('data-uuid', uuid);
                    $("#DialogPermission").modal({ backdrop: 'static', keyboard: false });
                });
            });
            $("#grid-permissions").find(".command-delete").on("click", function (e) {
                var uuid = $(this).data("row-id");
                stdDialogConfirm('{{ lang._('Confirm') }}', '{{ lang._('Do you want to delete this permission ? ') }}', function () {
                    ajaxCall(url = "/api/rtsphelper/settings/delPermission/" + uuid, sendData = {}, callback = function (data, status) {
                        $("#grid-permissions").bootgrid("reload");
                    });
                });
            });
        });

        $("#grid-permissions").find("tfoot button[data-action='add']").on("click", function (e) {
            $("#DialogPermission").attr('data-uuid', '');
            $("#DialogPermission").modal({ backdrop: 'static', keyboard: false });
            $("#DialogPermission").find("input").val("");
        });

        $("#btn_DialogPermission_save").unbind('click').click(function () {
            var uuid = $("#DialogPermission").attr('data-uuid');
            var url = "/api/rtsphelper/settings/addPermission";
            if (uuid) {
                url = "/api/rtsphelper/settings/setPermission/" + uuid;
            }
            saveFormToEndpoint(url = url, formid = 'DialogPermission', callback_ok = function () {
                $("#DialogPermission").modal('hide');
                $("#grid-permissions").bootgrid("reload");
            });
        });

        $("#grid-status").bootgrid({
            ajax: true,
            selection: false,
            multiSelect: false,
            rowCount: [10, 25, 50, -1],
            url: '/api/rtsphelper/status/connections',
        });

        $("#saveAct").click(function () {
            saveFormToEndpoint(url = "/api/rtsphelper/settings/set", formid = 'frm_general_settings', callback_ok = function () {
                $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url = "/api/rtsphelper/service/reconfigure", sendData = {}, callback = function (data, status) {
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

        $("#refreshAct").click(function () {
            $("#grid-status").bootgrid("reload");
        });
    });
</script>