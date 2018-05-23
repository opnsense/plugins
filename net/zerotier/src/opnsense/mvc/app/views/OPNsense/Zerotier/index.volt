{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 David Harrigan

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

    $(document).ready(function() {

        var zerotierSettings = {'settings': '/api/zerotier/settings/get'};

        mapDataToFormUI(zerotierSettings).done(function(data) {
            formatTokenizersUI();
            $('select').selectpicker('refresh');
        });

        $("#grid-networks").UIBootgrid(
            {
                search: '/api/zerotier/network/search',
                get:'/api/zerotier/network/get/',
                set:'/api/zerotier/network/set/',
                add:'/api/zerotier/network/add/',
                del:'/api/zerotier/network/del/',
                info:'/api/zerotier/network/info/',
                toggle:'/api/zerotier/network/toggle/'
            }
        );

        ajaxGet(url="/api/zerotier/settings/status", sendData={}, callback=function(data, status) {
            updateServiceStatusUI(data['result']);
            toggleNetworksTab(data['result']);
        });

        $("#btn_save_settings").click(function() {
            $("#settings_progress").addClass("fa fa-spinner fa-pulse");
            saveFormToEndpoint(url="/api/zerotier/settings/set", formid="settings", callback_ok=function(data, status) {
                ajaxGet(url="/api/zerotier/settings/status", sendData={}, callback=function(data, status) {
                    updateServiceStatusUI(data['result']);
                    toggleNetworksTab(data['result']);
                });
                $("#settings_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        function toggleNetworksTab(status) {
            switch(status) {
                case "disabled":
                case "service_not_enabled":
                    $('#ztNetworks').addClass("disabled");
                    $('#ztNetworksLink').removeAttr("data-toggle");
                    break;
                default:
                    $('#ztNetworks').removeClass("disabled");
                    $('#ztNetworksLink').attr("data-toggle", "tab");
            }
        };

    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li id="ztSettings" class="active"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
    <li id="ztNetworks"><a id="ztNetworksLink" data-toggle="tab" href="#networks">{{ lang._('Networks') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in active">
        <div class="content-box">
            {{ partial("layout_partials/base_form", ['fields': settingsForm, 'id': 'settings', 'apply_btn_id': 'btn_save_settings']) }}
        </div>
    </div>
    <div id="networks" class="tab-pane fade in">
        <table id="grid-networks" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogNetwork">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="11%" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="networkId" data-width="32%" data-type="string" data-visible="true">{{ lang._('Network Id') }}</th>
                    <th data-column-id="description" data-width="46%" data-type="string" data-visible="true">{{ lang._('Local Description') }}</th>
                    <th data-column-id="commands" data-width="11%" data-formatter="commandsWithInfo" data-visible="true" data-sortable="false">{{ lang._('Commands') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
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

{{ partial("layout_partials/base_dialog", ['fields': dialogNetworkForm, 'id': 'dialogNetwork', 'label': lang._('Edit Zerotier Network')]) }}
