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
    <li class="active"><a data-toggle="tab" href="#primary-domains">{{ lang._('Primary Zones') }}</a></li>
    <li><a data-toggle="tab" href="#secondary-domains">{{ lang._('Secondary Zones') }}</a></li>
    <li><a data-toggle="tab" href="#forward-domains">{{ lang._('Forward Zones') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="primary-domains" class="tab-pane fade in active">
        <div id="primary-domains-area" class="table-responsive">
            <table id="grid-primary-domains" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindPrimaryDomain">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-width="4.5em">{{ lang._('Enabled') }}</th>
                        <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                        <th data-column-id="ttl" data-type="string" data-visible="true">{{ lang._('TTL') }}</th>
                        <th data-column-id="refresh" data-type="string" data-visible="true">{{ lang._('Refresh') }}</th>
                        <th data-column-id="retry" data-type="string" data-visible="true">{{ lang._('Retry') }}</th>
                        <th data-column-id="expire" data-type="string" data-visible="true">{{ lang._('Expire') }}</th>
                        <th data-column-id="negative" data-type="string" data-visible="true">{{ lang._('Negative TTL') }}</th>
                        <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                        <th data-column-id="commands" data-width="9em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5"></td>
                        <td>
                            <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div id="secondary-domains" class="tab-pane fade in">
        <div class="col-md-12">
            <h2>{{ lang._('Zones') }}</h2>
        </div>
        <div id="secondary-domains-area" class="table-responsive">
            <table id="grid-secondary-domains" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindSecondaryDomain">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                        <th data-column-id="primaryip" data-type="string" data-visible="true">{{ lang._('Primary IPs') }}</th>
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
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <hr/>
        <div class="col-md-12">
            <div id="ChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <button class="btn btn-primary saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i class="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="forward-domains" class="tab-pane fade in">
        <div class="col-md-12">
            <h2>{{ lang._('Zones') }}</h2>
        </div>
        <div id="forward-domains-area" class="table-responsive">
            <table id="grid-forward-domains" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindForwardDomain">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                        <th data-column-id="forwardserver" data-type="string" data-visible="true">{{ lang._('Forwarder IPs') }}</th>
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
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <hr/>
        <div class="col-md-12">
            <div id="ChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <button class="btn btn-primary saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i class="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindPrimaryDomain,'id':'dialogEditBindPrimaryDomain','label':lang._('Edit Primary Zone')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindSecondaryDomain,'id':'dialogEditBindSecondaryDomain','label':lang._('Edit Secondary Zone')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindForwardDomain,'id':'dialogEditBindForwardDomain','label':lang._('Edit Forward Zone')])}}

{{ partial("OPNsense/Bind/zone_check") }}

<script>
$(document).ready(function() {
    updateServiceControlUI('bind');

    $("#grid-primary-domains").UIBootgrid({
        'search': '/api/bind/domain/search_primary_domain',
        'get': '/api/bind/domain/get_domain/',
        'set': '/api/bind/domain/set_domain/',
        'add': '/api/bind/domain/add_primary_domain/',
        'del': '/api/bind/domain/del_domain/',
        'toggle': '/api/bind/domain/toggle_domain/',
        commands: {
            'bind-checkzone': {
                'title': "Check & preview",
                'classname': "fa fa-fw fa-stethoscope  ",
                'sequence': 300,
            },
        },
        options: {
            selection: true,
            multiSelect: false,
            rowSelect: true,
            rowCount: [3, 7, 14, 20, 50, 100, -1]
        }
    }).on("loaded.rs.jquery.bootgrid", function(e) {
        // Checkzone button
        $("#grid-primary-domains").find(".command-bind-checkzone").off("click").on("click", function(ev) {
            if (!$(this).closest(".tabulator-row").hasClass("text-muted")) {
                let zonename = $(this).closest(".tabulator-row").find("[tabulator-field='domainname']").text();
                zone_test(zonename);
            } else {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('For zone Check and Show to work, the zone must be enabled and the configuration applied.') }}",
                    buttons: [{
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        },
                    }]
                });
            }
        });

        let ids = $("#grid-primary-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-primary-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $("#grid-secondary-domains").UIBootgrid({
        'search': '/api/bind/domain/search_secondary_domain',
        'get': '/api/bind/domain/get_domain/',
        'set': '/api/bind/domain/set_domain/',
        'add': '/api/bind/domain/add_secondary_domain/',
        'del': '/api/bind/domain/del_domain/',
        'toggle': '/api/bind/domain/toggle_domain/',
        options: {
            selection: false,
            multiSelect: false,
            rowSelect: false,
            rowCount: [7, 14, 20, 50, 100, -1]
        }
    }).on("loaded.rs.jquery.bootgrid", function(e) {
        let ids = $("#grid-secondary-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-secondary-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $("#grid-forward-domains").UIBootgrid({
        'search': '/api/bind/domain/search_forward_domain',
        'get': '/api/bind/domain/get_domain/',
        'set': '/api/bind/domain/set_domain/',
        'add': '/api/bind/domain/add_forward_domain/',
        'del': '/api/bind/domain/del_domain/',
        'toggle': '/api/bind/domain/toggle_domain/',
        options: {
            selection: false,
            multiSelect: false,
            rowSelect: false,
            rowCount: [7, 14, 20, 50, 100, -1]
        }
    }).on("loaded.rs.jquery.bootgrid", function(e) {
        let ids = $("#grid-forward-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-forward-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $(".saveAct_domain").click(function() {
        $(".saveAct_domain_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall("/api/bind/service/reconfigure", {}, function(data, status) {
            updateServiceControlUI('bind');
            $(".saveAct_domain_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    $('#domain\\.transferkeyalgo').on('change', function(e) {
        if (e.target.selectedIndex === 0) {
            $('#domain\\.transferkey,#domain\\.transferkeyname').val('').attr('readonly', true);
        } else {
            $('#domain\\.transferkey,#domain\\.transferkeyname').attr('readonly', false);
        }
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
