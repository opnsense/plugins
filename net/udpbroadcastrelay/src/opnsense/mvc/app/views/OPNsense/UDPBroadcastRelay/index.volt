{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2020 Martin Wasley

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
        /**
         * inline open dialog, go back to previous page on exit
         */
        function openDialog(uuid) {
            var editDlg = "DialogEdit";
            var setUrl = "/api/udpbroadcastrelay/settings/setRelay/";
            var getUrl = "/api/udpbroadcastrelay/settings/getRelay/";
            var urlMap = {};
            urlMap['frm_' + editDlg] = getUrl + uuid;
            mapDataToFormUI(urlMap).done(function () {
                // update selectors
                $('.selectpicker').selectpicker('refresh');
                // clear validation errors (if any)
                clearFormValidation('frm_' + editDlg);
                // show
                $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                $('#'+editDlg).on('hidden.bs.modal', function () {
                    // go back to previous page on exit
                    parent.history.back();
                });
            });
        }
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-proxies").UIBootgrid(
                {   'search':'/api/udpbroadcastrelay/settings/searchRelay',
                    'get':'/api/udpbroadcastrelay/settings/getRelay/',
                    'set':'/api/udpbroadcastrelay/settings/setRelay/',
                    'add':'/api/udpbroadcastrelay/settings/addRelay/',
                    'del':'/api/udpbroadcastrelay/settings/delRelay/',
                    'toggle':'/api/udpbroadcastrelay/settings/toggleRelay/',
                    'options':{selection:false, multiSelect:false}
                }
        );

        {% if (selected_uuid|default("") != "") %}
            openDialog(uuid='{{selected_uuid}}');
        {% endif %}

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

    });

</script>


<!-- <ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-proxies">{{ lang._('UDPBroadcastRelays') }}</a></li>
</ul> -->
<div class="tab-content content-box tab-content">
    <div id="udpbroadcastrelays" class="tab-pane fade in active">
        <!-- tab page "udpbroadcastrelay items" -->
        <table id="grid-proxies" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="1em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="interfaces" data-width="2em" data-type="string" data-visible="true">{{ lang._('Interfaces') }}</th>
                <th data-column-id="multicastaddress" data-width="2em" data-type="string" data-visible="true">{{ lang._('Multicast Addresses') }}</th>
                <th data-column-id="sourceaddress"  data-width="2em" data-type="string" data-visible="true">{{ lang._('Source Address') }}</th>
                <th data-column-id="listenport" data-width="2em" data-type="string" data-visible="true">{{ lang._('Listen Port') }}</th>
                <th data-column-id="InstanceID" data-width="1em" data-type="string" data-visible="true">{{ lang._('ID') }}</th>
                <th data-column-id="description" data-width="2em" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="RevertTTL" data-width="2em" data-type="string"  data-visible="true" data-formatter="boolean">{{ lang._('Use ID as TTL') }}</th>
                <th data-column-id="commands" data-width="2em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit Relay')])}}
