{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-addresses").UIBootgrid(
            {   'search':'/api/postfix/address/searchAddress',
                'get':'/api/postfix/address/getAddress/',
                'set':'/api/postfix/address/setAddress/',
                'add':'/api/postfix/address/addAddress/',
                'del':'/api/postfix/address/delAddress/',
                'toggle':'/api/postfix/address/toggleAddress/'
            }
        );

        $("#grid-senderbccs").UIBootgrid(
            {   'search':'/api/postfix/senderbcc/searchSenderbcc',
                'get':'/api/postfix/senderbcc/getSenderbcc/',
                'set':'/api/postfix/senderbcc/setSenderbcc/',
                'add':'/api/postfix/senderbcc/addSenderbcc/',
                'del':'/api/postfix/senderbcc/delSenderbcc/',
                'toggle':'/api/postfix/senderbcc/toggleSenderbcc/'
            }
        );

        $("#grid-recipientbccs").UIBootgrid(
            {   'search':'/api/postfix/recipientbcc/searchRecipientbcc',
                'get':'/api/postfix/recipientbcc/getRecipientbcc/',
                'set':'/api/postfix/recipientbcc/setRecipientbcc/',
                'add':'/api/postfix/recipientbcc/addRecipientbcc/',
                'del':'/api/postfix/recipientbcc/delRecipientbcc/',
                'toggle':'/api/postfix/recipientbcc/toggleRecipientbcc/'
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
            ajaxCall("/api/postfix/service/reconfigure", {}, function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");

                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error reconfiguring Postfix') }}",
                        message: data['status'],
                        draggable: true
                    });
                } else {
                    ajaxCall("/api/postfix/service/reconfigure", {});
                }
            });
        });

    });


</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#addresses">{{ lang._('Aliases') }}</a></li>
    <li><a data-toggle="tab" href="#senderbccs">{{ lang._('Sender BCC') }}</a></li>
    <li><a data-toggle="tab" href="#recipientbccs">{{ lang._('Recipient BCC') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="addresses" class="tab-pane fade in active">
        <!-- tab page "addresses" -->
        <table id="grid-addresses" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditPostfixAddress">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="from" data-type="string" data-visible="true">{{ lang._('Rewrite From') }}</th>
                <th data-column-id="to" data-type="string" data-visible="true">{{ lang._('Rewrite To') }}</th>
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
    <div id="senderbccs" class="tab-pane fade in">
        <!-- tab page "senderbccs" -->
        <table id="grid-senderbccs" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditPostfixSenderbcc">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="from" data-type="string" data-visible="true">{{ lang._('Sender Address') }}</th>
                <th data-column-id="to" data-type="string" data-visible="true">{{ lang._('BCC To') }}</th>
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
    <div id="recipientbccs" class="tab-pane fade in">
        <!-- tab page "recipientbccs" -->
        <table id="grid-recipientbccs" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditPostfixRecipientbcc">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="from" data-type="string" data-visible="true">{{ lang._('Recipient Address') }}</th>
                <th data-column-id="to" data-type="string" data-visible="true">{{ lang._('BCC To') }}</th>
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

{{ partial("layout_partials/base_dialog",['fields':formDialogEditPostfixAddress,'id':'dialogEditPostfixAddress','label':lang._('Edit Address Rewriting')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPostfixSenderbcc,'id':'dialogEditPostfixSenderbcc','label':lang._('Edit Sender BCC')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPostfixRecipientbcc,'id':'dialogEditPostfixRecipientbcc','label':lang._('Edit Recipient BCC')])}}