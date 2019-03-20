{#

OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-clients").UIBootgrid(
            {   'search':'/api/freeradius/client/searchClient',
                'get':'/api/freeradius/client/getClient/',
                'set':'/api/freeradius/client/setClient/',
                'add':'/api/freeradius/client/addClient/',
                'del':'/api/freeradius/client/delClient/',
                'toggle':'/api/freeradius/client/toggleClient/'
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


    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#clients">{{ lang._('Clients') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="clients" class="tab-pane fade in active">
        <!-- tab page "clients" -->
        <table id="grid-clients" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFreeRADIUSClient">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                <th data-column-id="secret" data-type="string" data-visible="true">{{ lang._('Shared secret') }}</th>
                <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address or Network') }}</th>
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
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
        <br/><br/>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSClient,'id':'dialogEditFreeRADIUSClient','label':lang._('Edit Client')])}}
