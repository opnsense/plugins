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
    <li class="active"><a data-toggle="tab" href="#forwarders">{{ lang._('DNS Forwarders') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="forwarders" class="tab-pane fade in active">
        <div class="col-md-12">
            <div class="alert alert-info" role="alert">
                {{ lang._('If you enter forwarders in both the DNS and DNS over TLS tables, BIND treats them as one combined pool.') }}
            </div>
        </div>
        <div class="col-md-12">
            <h2>{{ lang._('DNS') }}</h2>
        </div>
        <div id="dns-forwarders-area" class="table-responsive">
            <table id="grid-dns-forwarders" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessageForwarders" data-editDialog="dialogEditBindDnsForwarder">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                        <th data-column-id="port" data-type="string" data-visible="true">{{ lang._('Port') }}</th>
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
        <hr/>
        <div class="col-md-12">
            <h2>{{ lang._('DNS over TLS') }}</h2>
        </div>
        <div id="dot-forwarders-area" class="table-responsive">
            <table id="grid-dot-forwarders" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessageForwarders" data-editDialog="dialogEditBindDotForwarder">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                        <th data-column-id="port" data-type="string" data-visible="true">{{ lang._('Port') }}</th>
                        <th data-column-id="tlshostname" data-type="string" data-visible="true">{{ lang._('TLS Hostname') }}</th>
                        <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                        <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6"></td>
                        <td>
                            <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                            <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <hr/>
        <div class="col-md-12">
            <div id="ChangeMessageForwarders" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <button class="btn btn-primary" id="saveAct_forwarders" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_forwarders_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindDnsForwarder,'id':'dialogEditBindDnsForwarder','label':lang._('Edit DNS Forwarder')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindDotForwarder,'id':'dialogEditBindDotForwarder','label':lang._('Edit DNS over TLS Forwarder')])}}

<style>
    .long-str {
        word-break: break-word;
    }
</style>
<script>
$(document).ready(function() {
    updateServiceControlUI('bind');

    $("#grid-dns-forwarders").UIBootgrid({
        'search': '/api/bind/forwarder/search_dns_forwarder',
        'get': '/api/bind/forwarder/get_dns_forwarder/',
        'set': '/api/bind/forwarder/set_dns_forwarder/',
        'add': '/api/bind/forwarder/add_dns_forwarder/',
        'del': '/api/bind/forwarder/del_dns_forwarder/',
        'toggle': '/api/bind/forwarder/toggle_dns_forwarder/'
    });

    $("#grid-dot-forwarders").UIBootgrid({
        'search': '/api/bind/forwarder/search_dot_forwarder',
        'get': '/api/bind/forwarder/get_dot_forwarder/',
        'set': '/api/bind/forwarder/set_dot_forwarder/',
        'add': '/api/bind/forwarder/add_dot_forwarder/',
        'del': '/api/bind/forwarder/del_dot_forwarder/',
        'toggle': '/api/bind/forwarder/toggle_dot_forwarder/'
    });

    $("#saveAct_forwarders").click(function() {
        $("#saveAct_forwarders_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
            updateServiceControlUI('bind');
            $("#saveAct_forwarders_progress").removeClass("fa fa-spinner fa-pulse");
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
