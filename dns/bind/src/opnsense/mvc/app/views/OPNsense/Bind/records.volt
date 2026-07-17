{#
 #
 # Copyright (c) 2014-2019 Deciso B.V.
 # Copyright (c) 2018-2019 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#primary-records">{{ lang._('Primary Zone Records') }}</a></li>
    <li><a data-toggle="tab" href="#dhcp-watcher">{{ lang._('DHCP Watcher') }}</a></li>
    <li><a data-toggle="tab" href="#dhcp-records">{{ lang._('Active DHCP Records') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="primary-records" class="tab-pane fade in active">
        <div id="primary-record-area" class="table-responsive">
            <table id="grid-primary-records" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindRecord">
                <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-width="4.5em">{{ lang._('Enabled') }}</th>
                    <th data-column-id="domain" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="type" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                    <th data-column-id="value" data-type="string" data-visible="true" data-css-class="long-str">{{ lang._('Value') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false" data-width="7em">{{ lang._('Commands') }}</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
        <div class="col-md-12">
            <div id="ChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <hr />
            <button class="btn btn-primary saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i class="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="dhcp-watcher" class="tab-pane fade in">
        <div id="dhcp-watcher-area" class="table-responsive">
            <table id="grid-dhcp-watcher" class="table table-condensed table-hover table-striped" data-editDialog="dialogEditBindWatcher">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="dhcp_source" data-type="string" data-visible="true">{{ lang._('DHCP Source') }}</th>
                        <th data-column-id="hostname_suffix" data-type="string" data-visible="true">{{ lang._('Hostname Suffix') }}</th>
                        <th data-column-id="reverse_zone" data-type="string" data-visible="true">{{ lang._('Reverse Zone') }}</th>
                        <th data-column-id="tsigkey" data-type="string" data-visible="true">{{ lang._('TSIG Key') }}</th>
                        <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                        <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5"></td>
                        <td>
                            <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                            <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_watcher" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_watcher_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="dhcp-records" class="tab-pane fade in">
        <div class="alert alert-info" role="alert">
            {{ lang._('These records are automatically created by the DHCP watcher based on active DHCP leases. They are read-only and cannot be edited manually.') }}
        </div>
        <div id="dhcp-records-area" class="table-responsive">
            <table id="grid-dhcp-records" class="table table-condensed table-hover table-striped">
                <thead>
                <tr>
                    <th data-column-id="hostname" data-type="string">{{ lang._('Hostname') }}</th>
                    <th data-column-id="domain" data-type="string">{{ lang._('Domain') }}</th>
                    <th data-column-id="address" data-type="string">{{ lang._('IP Address') }}</th>
                    <th data-column-id="mac" data-type="string">{{ lang._('MAC Address') }}</th>
                    <th data-column-id="ends" data-type="string">{{ lang._('Expires') }}</th>
                    <th data-column-id="source" data-type="string">{{ lang._('Source') }}</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindRecord,'id':'dialogEditBindRecord','label':lang._('Edit Record')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindWatcher,'id':'dialogEditBindWatcher','label':lang._('Edit DHCP Watcher Mapping')])}}

<style>
    .long-str {
        word-break: break-word;
    }
</style>
<script>
$(document).ready(function() {
    updateServiceControlUI('bind');

    $("#grid-primary-records").UIBootgrid({
        'search': '/api/bind/record/search_record',
        'get': '/api/bind/record/get_record/',
        'set': '/api/bind/record/set_record/',
        'add': '/api/bind/record/add_record/',
        'del': '/api/bind/record/del_record/',
        'toggle': '/api/bind/record/toggle_record/',
        options: {
            selection: true,
            multiSelect: true,
            rowSelect: true,
            rowCount: [7, 14, 20, 50, 100, -1]
        }
    });

    $("#grid-dhcp-watcher").UIBootgrid({
        'search': '/api/bind/watcher/search_mapping',
        'get': '/api/bind/watcher/get_mapping/',
        'set': '/api/bind/watcher/set_mapping/',
        'add': '/api/bind/watcher/add_mapping/',
        'del': '/api/bind/watcher/del_mapping/',
        'toggle': '/api/bind/watcher/toggle_mapping/'
    });

    $("#grid-dhcp-records").UIBootgrid({
        'search': '/api/bind/dhcprecord/search_record',
        options: {
            selection: false,
            multiSelect: false,
            rowSelect: false,
            rowCount: [7, 14, 20, 50, 100, -1]
        }
    });

    $(".saveAct_domain").click(function() {
        $(".saveAct_domain_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall("/api/bind/service/reconfigure", {}, function(data, status) {
            updateServiceControlUI('bind');
            $(".saveAct_domain_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    $("#saveAct_watcher").click(function() {
        $("#saveAct_watcher_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
            updateServiceControlUI('bind');
            $("#saveAct_watcher_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    // update history on tab state and implement navigation
    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        history.pushState(null, null, e.target.hash);
    });
});
</script>
