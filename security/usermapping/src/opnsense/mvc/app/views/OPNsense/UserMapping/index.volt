{#
 # Copyright (C) 2017-2019 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 #  1. Redistributions of source code must retain the above copyright notice,
 #   this list of conditions and the following disclaimer.
 #
 #  2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    $(function () {
        let data_get_map = {'frm_ipmap_general_general': '/api/usermapping/settings/get'};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('select[data-allownew="false"]').selectpicker('refresh');
            updateServiceControlUI('usermapping');
        });

        // update history on tab state and implement navigation
        if(window.location.hash !== "") {
            $('a[href="' + window.location.hash + '"]').click();
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        $('.reload_btn').on('click', function() {
            $(".reloadAct_progress").addClass("fa-spin");
            ajaxCall("/api/usermapping/service/reconfigure", {}, function() {
                $(".reloadAct_progress").removeClass("fa-spin");
            });
        });
        $("#grid-user_mapping").UIBootgrid(
            {
                'search': '/api/usermapping/settings/search_user_mapping',
                'get': '/api/usermapping/settings/get_user_mapping/',
                'set': '/api/usermapping/settings/set_user_mapping/',
                'add': '/api/usermapping/settings/add_user_mapping/',
                'del': '/api/usermapping/settings/del_user_mapping/',
                'options': {selection: false, multiSelect: false}
            }
        );
    })
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settings]) }}
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_user_mapping').trigger('click');"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0;"><b>{{ lang._('Objects')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_user_mapping" href="#subtab_user_mapping">{{ lang._('User Mapping')}}</a>
            </li>
        </ul>
    </li>
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData': settings]) }}
    <div id="subtab_user_mapping" class="tab-pane fade">
        <table id="grid-user_mapping" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="usermappingdlg">
            <thead>
            <tr>
                <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="external_alias" data-type="string" data-sortable="true" data-visible="true">{{ lang._('External Alias') }}</th>
                <th data-column-id="type" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Type') }}</th>
                <th data-column-id="object_name" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Object Name') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>


{{ partial("layout_partials/base_dialog",['fields': user_mapping,'id':'usermappingdlg', 'label':lang._('Edit User Mapping')]) }}