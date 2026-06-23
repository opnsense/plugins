{#

Copyright (C) 2017-2021 Frank Wall
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

        var gridParams = {
            search:'/api/acmeclient/accounts/search',
            get:'/api/acmeclient/accounts/get/',
            set:'/api/acmeclient/accounts/update/',
            add:'/api/acmeclient/accounts/add/',
            del:'/api/acmeclient/accounts/del/',
            toggle:'/api/acmeclient/accounts/toggle/',
            register:'/api/acmeclient/accounts/register/',
        };

        var gridopt = {
            ajax: true,
            selection: true,
            multiSelect: true,
            url: '/api/acmeclient/accounts/search',
            formatters: {
                "commands": function (column, row) {
                    return "<button type=\"button\" title=\"{{ lang._('Edit account') }}\" class=\"btn btn-xs btn-default command-edit bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                        "<button type=\"button\" title=\"{{ lang._('Copy account') }}\" class=\"btn btn-xs btn-default command-copy bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Register account') }}\" class=\"btn btn-xs btn-default command-register bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-address-book-o\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Remove account') }}\" class=\"btn btn-xs btn-default command-delete bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
                },
                "rowtoggle": function (column, row) {
                    if (parseInt(row[column.id], 2) == 1) {
                        return "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                    } else {
                        return "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                    }
                },
                "accountstatus": function (column, row) {
                    if (row.statusCode == "" || row.statusCode == undefined) {
                        // fallback to lastUpdate value (unset if account was not registered)
                        if (row.statusLastUpdate == "" || row.statusLastUpdate == undefined) {
                            return "{{ lang._('not registered') }}";
                        } else {
                            return "{{ lang._('OK') }}";
                        }
                    } else if (row.statusCode == "100") {
                        return "{{ lang._('not registered') }}";
                    } else if (row.statusCode == "200") {
                        return "{{ lang._('OK (registered)') }}";
                    } else if (row.statusCode == "250") {
                        return "{{ lang._('deactivated') }}";
                    } else if (row.statusCode == "300") {
                        return "{{ lang._('configuration error') }}";
                    } else if (row.statusCode == "400") {
                        return "{{ lang._('registration failed') }}";
                    } else if (row.statusCode == "500") {
                        return "{{ lang._('internal error') }}";
                    } else {
                        return "{{ lang._('unknown') }}";
                    }
                },
                "acmestatusdate": function (column, row) {
                    if (row.statusLastUpdate == "" || row.statusCode == undefined) {
                        return "{{ lang._('unknown') }}";
                    } else {
                        var statusdate = new Date(row.statusLastUpdate*1000);
                        return statusdate.toLocaleString();
                    }
                }
            },
        };

        const grid_accounts = $("#grid-accounts").UIBootgrid($.extend(gridParams, {
            options: gridopt,
            commands: {
                register: {
                    method: function(event, cell) {
                        var uuid = $(this).data("row-id");
                        stdDialogConfirm(
                            '{{ lang._('Confirmation Required') }}',
                            '{{ lang._('Register the selected account with the configured ACME CA?') }}',
                            '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}',
                            function() {
                                ajaxCall(gridParams['register'] + uuid, {}, function() {
                                    grid_accounts.bootgrid("reload");
                                });
                            }
                        );
                    },
                    classname: 'fa fa-address-book-o',
                    title: '{{ lang._('Register account') }}',
                    sequence: 510
                }
            },
            tabulatorOptions: {
                rowFormatter: function(row) {
                    if (parseInt(row.getData()['enabled'], 2) !== 1) {
                        $(row.getElement()).addClass('text-muted');
                    } else {
                        $(row.getElement()).removeClass('text-muted');
                    }
                }
            }
        }));

        // hook into on-show event for dialog to extend layout.
        $('#DialogAccount').on('shown.bs.modal', function (e) {
            // hide options that are irrelevant for the selected CA
            $("#account\\.ca").change(function(){
                $(".ca_options").hide();
                $(".ca_options_"+$(this).val()).show();
            });
            $("#account\\.ca").change();
        })

    });

</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li {% if showIntro|default('0')=='1' %}class="active"{% endif %}><a data-toggle="tab" id="accounts-introduction" href="#subtab_accounts-introduction"><b>{{ lang._('Introduction') }}</b></a></li>
    <li {% if showIntro|default('0')=='0' %}class="active"{% endif %}><a data-toggle="tab" id="accounts-tab" href="#accounts"><b>{{ lang._('Accounts') }}</b></a></li>
</ul>

<div class="content-box tab-content">

    <div id="subtab_accounts-introduction" class="tab-pane fade {% if showIntro|default('0')=='1' %}in active{% endif %}">
        <div class="col-md-12">
            <h1>{{ lang._('Accounts') }}</h1>
            <p>{{ lang._('In order to create certificates, an account is required. Also the following information should be considered:') }}</p>
            <ul>
              <li>{{ lang._('The account will be %sregistered automatically%s at the chosen CA. The CA will then associate new certificates to the selected account.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('Usually CAs will let you know if something went wrong and a certificate is about to expire, therefore a %svalid e-mail address%s should be provided.') | format('<b>', '</b>') }}</li>
              <li>{{ lang._('For certain use-cases it can be useful to register %smultiple accounts%s, but the policy of the CA should be respected with this regard.') | format('<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._('When requesting support from a CA the account ID may be required, %sthis documentation%s contains information how to get the internal account ID from the log files.') | format('<a href="https://letsencrypt.org/docs/account-id/">', '</a>') }}</p>
        </div>
    </div>

    <div id="accounts" class="tab-pane fade {% if showIntro|default('0')=='0' %}in active{% endif %}">

        <table id="grid-accounts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAccount">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="email" data-type="string">{{ lang._('E-Mail') }}</th>
                <th data-column-id="ca" data-type="string">{{ lang._('CA') }}</th>
                <th data-column-id="statusCode" data-type="string" data-formatter="accountstatus">{{ lang._('Status') }}</th>
                <th data-column-id="statusLastUpdate" data-type="string" data-formatter="acmestatusdate">{{ lang._('Registration Date') }}</th>
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
{{ partial("layout_partials/base_dialog",['fields':formDialogAccount,'id':'DialogAccount','label':lang._('Edit Account')])}}
