{#
 #
 # Copyright (C) 2020 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
<div class="tab-content content-box tab-content">
    <div id="mailbox" class="tab-pane fade in active">
        <table id="grid-mailbox" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditFetchmailMailbox">
            <thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="host" data-type="string" data-visible="true">{{ lang._('Host') }}</th>
                <th data-column-id="protocol" data-type="string" data-visible="true">{{ lang._('Protocol') }}</th>
                <th data-column-id="user" data-type="string" data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="password" data-type="string" data-visible="true">{{ lang._('Password') }}</th>
                <th data-column-id="destination" data-type="string" data-visible="true">{{ lang._('Destination') }}</th>
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
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAct_progress" class=""></i></button>
            <br/><br/>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditFetchmailMailbox,'id':'dialogEditFetchmailMailbox','label':lang._('Edit Mailbox')])}}

<script>

$(function() {

    $("#grid-mailbox").UIBootgrid(
        {   'search':'/api/fetchmail/mailbox/searchMailbox',
            'get':'/api/fetchmail/mailbox/getMailbox/',
            'set':'/api/fetchmail/mailbox/setMailbox/',
            'add':'/api/fetchmail/mailbox/addMailbox/',
            'del':'/api/fetchmail/mailbox/delMailbox/',
            'toggle':'/api/fetchmail/mailbox/toggleMailbox/'
        }
    );

    updateServiceControlUI('fetchmail');

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/fetchmail/mailbox/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/fetchmail/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('fetchmail');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

});
</script>
