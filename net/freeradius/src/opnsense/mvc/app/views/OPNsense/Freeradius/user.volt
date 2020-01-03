{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 - 2019 Michael Muenz <m.muenz@gmail.com>
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
        updateServiceControlUI('freeradius');

        $("#grid-users").UIBootgrid(
            {   'search':'/api/freeradius/user/searchUser',
                'get':'/api/freeradius/user/getUser/',
                'set':'/api/freeradius/user/setUser/',
                'add':'/api/freeradius/user/addUser/',
                'del':'/api/freeradius/user/delUser/',
                'toggle':'/api/freeradius/user/toggleUser/'
            }
        );

        $("#grid-avpairs").UIBootgrid(
            {   'search':'/api/freeradius/avpair/searchAvpair',
                'get':'/api/freeradius/avpair/getAvpair/',
                'set':'/api/freeradius/avpair/setAvpair/',
                'add':'/api/freeradius/avpair/addAvpair/',
                'del':'/api/freeradius/avpair/delAvpair/',
                'toggle':'/api/freeradius/avpair/toggleAvpair/'
            }
        );

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * Reconfigure
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/freeradius/service/reconfigure", sendData={}, callback=function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
                updateServiceControlUI('freeradius');
                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error reconfiguring FreeRADIUS') }}",
                        message: data['status'],
                        draggable: true
                    });
                } else {
                    ajaxCall(url="/api/freeradius/service/reconfigure", sendData={});
                }
            });
        });

      /*************************************************************************************************************
       * context driven input dialogs
       *************************************************************************************************************/
      ajaxGet(url='/api/freeradius/general/get', sendData={}, callback=function(data,status){
          // since our general data doesn't change during input of new users, we can control the dialog inputs
          // at once after load. No need for an "onShow" type of event here,
          // since our changes aren't driven by the dialog form itself.
          if (data.general != undefined) {
              $("#frm_dialogEditFreeRADIUSUser tr").each(function () {
                  var this_item_name = $(this).attr('id');
                  var this_item = $(this);
                  if (this_item_name != undefined) {
                      $.each(data.general, function(setting_key, setting_value){
                          var search_item = 'row_user.' + setting_key +'_';
                          if (this_item_name.startsWith(search_item) && setting_value == '0') {
                              // since our form tr rows are visible by default, we only have to hide what isn't needed
                              this_item.hide();
                          }
                      });
                  }
              });
          }
      });

    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#users">{{ lang._('Users') }}</a></li>
    <li><a data-toggle="tab" href="#avpairs">{{ lang._('AVPair') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="users" class="tab-pane fade in active">
        <table id="grid-users" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFreeRADIUSUser">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="username" data-type="string" data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="password" data-type="string" data-visible="false">{{ lang._('Password') }}</th>
                <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                <th data-column-id="subnet" data-type="string" data-visible="false">{{ lang._('Subnet') }}</th>
                <th data-column-id="vlan" data-type="string" data-visible="false">{{ lang._('VLAN ID') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>            </tr>
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
    <div id="avpairs" class="tab-pane fade in">
        <table id="grid-avpairs" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFreeRADIUSAvpair">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                <th data-column-id="operator" data-type="string" data-visible="false">{{ lang._('Operator') }}</th>
                <th data-column-id="value" data-type="string" data-visible="true">{{ lang._('Value') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
        <br/><br/>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSUser,'id':'dialogEditFreeRADIUSUser','label':lang._('Edit User')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSAvpair,'id':'dialogEditFreeRADIUSAvpair','label':lang._('Edit AVPair')])}}
