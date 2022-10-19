{#

OPNsense® is Copyright © 2021 by Deciso B.V.
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
        $("#grid-accounts").UIBootgrid(
            {   search:'/api/dyndns/accounts/search_item',
                get:'/api/dyndns/accounts/get_item/',
                set:'/api/dyndns/accounts/set_item/',
                add:'/api/dyndns/accounts/add_item/',
                del:'/api/dyndns/accounts/del_item/',
                toggle:'/api/dyndns/accounts/toggle_item/'
            }
        );
        let data_get_map = {'frm_settings':"/api/dyndns/settings/get"};
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('dyndns');
        });

        $("#reconfigureAct").SimpleActionButton({
          onPreAction: function() {
              const dfObj = new $.Deferred();
              saveFormToEndpoint("/api/dyndns/settings/set", 'frm_settings', function(){
                  dfObj.resolve();
              });
              return dfObj;
          }
        });
        $("#account\\.service").change(function(){
            let service = $(this).val();
            $("#frm_DialogAccount .optional_setting").each(function(){
                let this_item = $(this);
                if (this_item.hasClass("service_"+service)) {
                    this_item.prop( "disabled", false );
                    this_item.closest("tr").show();
                } else {
                    this_item.closest("tr").hide();
                    this_item.prop( "disabled", true );
                }
            });
        });
        $('#DialogAccount').on('shown.bs.modal', function (e) {
            $("#account\\.service").change();
        });

        $("#ddclient\\.general\\.checkip").change(function(){
            let checkip = $(this).val();
            $("#frm_settings .optional_setting").each(function(){
                let this_item = $(this);
                if (this_item.hasClass("checkip_"+checkip)) {
                    this_item.prop( "disabled", false );
                    this_item.closest("tr").show();
                } else {
                    this_item.closest("tr").hide();
                    this_item.prop( "disabled", true );
                }
            });
        });
        $("#ddclient\\.general\\.checkip").change();
    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="destinations" href="#tab_accounts">{{ lang._('Accounts') }}</a></li>
    <li><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('General settings') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="tab_accounts" class="tab-pane fade in active">
        <!-- tab page "accounts" -->
        <table id="grid-accounts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAccount" data-editAlert="ddclientChangeMessage">
            <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="service" data-type="string">{{ lang._('Service') }}</th>
                <th data-column-id="hostnames" data-type="string">{{ lang._('Hostnames') }}</th>
                <th data-column-id="username" data-type="string">{{ lang._('Username') }}</th>
                <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                <th data-column-id="current_ip" data-type="string">{{ lang._('Current IP') }}</th>
                <th data-column-id="current_mtime" data-type="string">{{ lang._('Updated') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="settings" class="tab-pane fade in">
      {{ partial("layout_partials/base_form",['fields':formSettings,'id':'frm_settings'])}}
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="ddclientChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/dyndns/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-service-widget="dyndns"
                    data-error-title="{{ lang._('Error reconfiguring DynDNS') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogAccount,'id':'DialogAccount','label':lang._('Edit Account')])}}
