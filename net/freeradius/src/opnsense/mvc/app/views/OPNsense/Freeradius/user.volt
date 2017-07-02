{#

OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
Copyright (C) 2017 Michael Muenz
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

<script type="text/javascript">

    $( document ).ready(function() {
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-users").UIBootgrid(
            {   search:'/api/freeradius/user/searchUser',
                get:'/api/freeradius/user/getUser/',
                set:'/api/freeradius/user/setUser/',
                add:'/api/freeradius/user/setUser/',
                del:'/api/freeradius/user/delUser/',
                toggle:'/api/freeradius/user/toggleUser/'
            }
        );

        $("#grid-clients").UIBootgrid(
                {   search:'/api/freeradius/user/searchClient',
                    get:'/api/freeradius/user/getClient/',
                    set:'/api/freeradius/user/setClient/',
                    add:'/api/freeradius/user/setClient/',
                    del:'/api/freeradius/user/delClient/',
                    toggle:'/api/freeradius/user/toggleClient/'
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
            ajaxCall(url="/api/tinc/service/reconfigure", sendData={}, callback=function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");

                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error reconfiguring Tinc') }}",
                        message: data['status'],
                        draggable: true
                    });
                } else {
                    ajaxCall(url="/api/freeradius/service/reconfigure", sendData={});
                }
            });
        });


    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#users">{{ lang._('Users') }}</a></li>
    <li><a data-toggle="tab" href="#clients">{{ lang._('Clients') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="users" class="tab-pane fade in active">
        <!-- tab page "users" -->
        <table id="grid-users" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFreeRADIUSUser">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="username" data-type="string" data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="password" data-type="string" data-visible="true">{{ lang._('Password') }}</th>
                <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                <th data-column-id="subnet" data-type="string" data-visible="true">{{ lang._('Subnet') }}</th>
                <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway Address') }}</th>
                <th data-column-id="vlan" data-type="string" data-visible="true">{{ lang._('VLAN ID') }}</th>
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
    <div id="hosts" class="tab-pane fade in">
        <div class="col-md-12">
            <!-- tab page "clients" -->
            <table id="grid-clients" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFreeRADIUSClient">
                <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                    <th data-column-id="secret" data-type="string" data-visible="true">{{ lang._('Secret') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>                </tr>
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
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
        <br/><br/>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSUser,'id':'dialogEditFreeRADIUSUser','label':lang._('Edit User')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSClient,'id':'dialogEditFreeRADIUSClient','label':lang._('Edit Client')])}}
