{#
 # Copyright (C) 2017-2018 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 #  1. Redistributions of source code must retain the above copyright notice,
 #   this list of conditions and the following disclaimer.
 #
 #  2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script src="{{ cache_safe('/ui/js/nginx/lib/lodash.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/nginx/lib/backbone-min.js') }}"></script>
<script>
SNIHostnameUpstreamModel = Backbone.Model.extend({});
SNIHostnameUpstreamCollection = Backbone.Collection.extend({
    initialize: function() {
        let that = this;
        $('#snihostname\\.data').change(function () {
            that.regenerateFromView()
        })
    },
    regenerateFromView: function () {
        let data = JSON.parse($('#snihostname\\.data').val());
        if (!_.isArray(data)) {
            data = [];
        }
        this.reset(data);
    }
});
UpstreamCollection = Backbone.Collection.extend({
    url: '/api/nginx/settings/searchupstream',
    parse: function(response) {
        return response.rows;
    }
});
uc = new UpstreamCollection();

KeyValueMapFieldEntry = Backbone.View.extend({

    tagName: 'div',
    attributes: {'class': 'row'},
    events: {
        'keyup .key': function () {
            this.model.set('hostname', this.key.value);
        },
        'change .value': function () {
            this.model.set('upstream', this.value.value);
        },
        "click .delete" : "deleteEntry"
    },
    key: null,
    value: null,
    delBtn: null,
    first: null,
    second: null,
    third: null,
    upstreamCollection: null,
    initialize: function (params) {
        this.upstreamCollection = params.upstreamCollection;
        this.listenTo(this.upstreamCollection, "update reset add remove", this.regenerate_list);
        this.first = document.createElement('div');
        this.first.classList.add('col-sm-5');
        this.key = document.createElement('input');
        this.first.append(this.key);
        this.key.type = 'text';
        this.key.classList.add('key');
        this.key.value = this.model.get('hostname');

        this.second = document.createElement('div');
        this.second.classList.add('col-sm-5');
        this.value = document.createElement('select');
        this.second.append(this.value);
        this.value.classList.add('value');
        this.value.classList.add('form-control');
        this.value.value = this.model.get('upstream');

        this.third = document.createElement('div');
        this.third.classList.add('col-sm-2');
        this.third.style.textAlign = 'right';
        this.delBtn = document.createElement("button");
        this.delBtn.classList.add('delete');
        this.delBtn.classList.add('btn');
        this.delBtn.innerHTML = '<span class="fa fa-trash"></span>';
        this.third.append(this.delBtn);
        if (!this.model.has('upstream') ||
            this.upstreamCollection.where ({'uuid' : this.model.get('upstream')}).length === 0) {
            if (this.upstreamCollection.length > 0) {
                this.model.set('upstream', this.upstreamCollection.at(0).get('uuid'));
            }
        }


        this.$el.append(this.first).append(this.second).append(this.third);
    },
    render: function() {
        $(this.key).val(this.model.get('hostname'));
        this.regenerate_list();
        $(this.value).val(this.model.get('upstream'));
    },
    deleteEntry: function (e) {
        e.preventDefault();
        this.collection.remove(this.model);
    },
    regenerate_list: function () {
        // backup value
        const v = $(this.value);
        // clear the dropdown
        v.html('');
        this.upstreamCollection.each(
            (mdl) => v.append(`<option value="${mdl.escape('uuid')}">${mdl.escape('description')}</option>`)
        );
        // restore
        v.val(this.model.get('upstream'));
        v.selectpicker('refresh');
    }
});
KeyValueMapField = Backbone.View.extend({
    tagName: 'div',
    attributes: {'class': 'container-fluid'},
    child_views: [],
    upstreamCollection: null,
    initialize: function (params) {
        this.dataField = $(params.dataField);
        this.collection = new SNIHostnameUpstreamCollection();
        this.upstreamCollection = params.upstreamCollection;
        this.listenTo(this.collection, "add remove reset", this.render);
        this.listenTo(this.collection, "change", this.update);
        // inject our table holder
        this.dataField.after(this.$el);
    },
    events: {
        "click .add": "addEntry"
    },
    render: function () {
        // clear table
        this.child_views.forEach((model) => model.remove());
        this.$el.html('');
        this.child_views = [];
        this.update();
        this.collection.each((model) => {
            const childView = new KeyValueMapFieldEntry({
                model: model,
                collection: this.collection,
                upstreamCollection: this.upstreamCollection
            });
            this.child_views.push(childView);
            this.$el.append(childView.$el);
            childView.render();
        });
        this.$el.append($(`
                <div class="row">
                    <button class="btn btn-primary pull-right add">
                        <span class="fa fa-plus"></span>
                    </button>
                </div>`));
    },
    update: function () {
        this.dataField.val(JSON.stringify(this.collection.toJSON()));
    },
    addEntry: function (e) {
        e.preventDefault();
        this.collection.add(new SNIHostnameUpstreamModel({
            hostname: 'localhost',
        }));
    }
});
$( document ).ready(function() {

    let data_get_map = {'frm_nginx':'/api/nginx/settings/get'};

    // load initial data
    mapDataToFormUI(data_get_map).done(function(){
        formatTokenizersUI();
        $('select[data-allownew="false"]').selectpicker('refresh');
        updateServiceControlUI('nginx');
    });

    // update history on tab state and implement navigation
    if(window.location.hash !== "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });

    $('.reload_btn').click(function() {
      $(".reloadAct_progress").addClass("fa-spin");
      ajaxCall(url="/api/nginx/service/reconfigure", sendData={}, callback=function(data,status) {
          $(".reloadAct_progress").removeClass("fa-spin");
      });
    });


    // form save event handlers for all defined forms
    $('[id*="save_"]').each(function(){
        $(this).click(function(event) {
            let frm_id = $(this).closest("form").attr("id");
            let frm_title = $(this).closest("form").attr("data-title");
            // save data for General TAB
            saveFormToEndpoint(url="/api/nginx/settings/set", formid=frm_id, callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                ajaxCall(url="/api/nginx/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");

                    if (data !== undefined && (status !== "success" || data['status'] !== 'ok')) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: frm_title,
                            message: JSON.stringify(data),
                            draggable: true
                        });
                    } else {
                        updateServiceControlUI('nginx');
                    }
                });
            });
        });
    });
    ['upstream',
    'upstreamserver',
    'location',
    'credential',
    'userlist',
    'httpserver',
    'streamserver',
    'httprewrite',
    'custompolicy',
    'security_header',
    'limit_zone',
    'cache_path',
    'limit_request_connection',
    'snifwd',
    'naxsirule'].forEach(function(element) {
        $("#grid-" + element).UIBootgrid(
            { 'search':'/api/nginx/settings/search' + element,
              'get':'/api/nginx/settings/get' + element + '/',
              'set':'/api/nginx/settings/set' + element + '/',
              'add':'/api/nginx/settings/add' + element + '/',
              'del':'/api/nginx/settings/del' + element + '/',
              'options':{selection:false, multiSelect:false}
            }
        );
    });
    let naxsi_rule_download_button = $('#naxsiruledownloadbtn');
    naxsi_rule_download_button.click(function () {
        BootstrapDialog.show({
            type: BootstrapDialog.TYPE_INFO,
            title: "{{ lang._('Download NAXSI Rules') }}",
            message: "{{ lang._('You are about to download the core rules from the Repository of NAXSI. You have to accept its %slicense%s to download the rules.')|format("<a href='https://github.com/nbs-system/naxsi/blob/master/LICENSE' target='_blank'>", "</a>") }}",
            buttons: [{
                label: "{{ lang._('Accept And Download') }}",
                cssClass: 'btn-primary',
                icon: 'fa fa-download',
                action: function(dlg){
                    dlg.close();
                    ajaxCall(url="/api/nginx/settings/downloadrules", sendData={}, callback=function(data,status) {
                        $('#naxsiruledownloadalert').hide();
                        // reload view after installing rules
                        $('#grid-naxsirule').bootgrid('reload');
                        $('#grid-custompolicy').bootgrid('reload');
                    });
                }
            }, {
                label: '{{ lang._('Reject') }}',
                action: function(dlg){
                    dlg.close();
                }
            }]
        });
    });
    let snifield = new KeyValueMapField({
        dataField: document.getElementById('snihostname.data'),
        upstreamCollection: uc
    });
    window.snifield = snifield;
    snifield.render();
    $("#grid-upstream").on("loaded.rs.jquery.bootgrid", function ()
    {
        /* we always have to reload too after bootgrid reloads */
        uc.fetch();
    });
    uc.fetch();
});

</script>
<style>
    #frm_sni_hostname_mapdlg .col-md-4 {
        width: 50%;
    }
    #frm_sni_hostname_mapdlg td > input {
        width: 100%;
        max-width: 100%;
    }
    #frm_sni_hostname_mapdlg .col-md-5 {
        width: 25%;
    }
    #row_snihostname\.data .row div {
        padding: 0;
    }

</style>


<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settings]) }}
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-http-location').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0;"><b>{{ lang._('HTTP(S)')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-location" href="#subtab_nginx-http-location">{{ lang._('Location')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-credential" href="#subtab_nginx-http-credential">{{ lang._('Credential')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-userlist" href="#subtab_nginx-http-userlist">{{ lang._('User List')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-upstream-server" href="#subtab_nginx-http-upstream-server">{{ lang._('Upstream Server')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-upstream" href="#subtab_nginx-http-upstream">{{ lang._('Upstream')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-server" href="#subtab_nginx-http-httpserver">{{ lang._('HTTP Server')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-rewrite" href="#subtab_nginx-http-rewrite">{{ lang._('URL Rewriting')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-custompolicy" href="#subtab_nginx-http-custompolicy">{{ lang._('Naxsi WAF Policy')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-naxsirule" href="#subtab_nginx-http-naxsirule">{{ lang._('Naxsi WAF Rule')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-security_header" href="#subtab_nginx-http-security_header">{{ lang._('Security Headers')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-http-cache_path" href="#subtab_nginx-http-cache_path">{{ lang._('Cache Path')}}</a>
            </li>
        </ul>
    </li>
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown"
           href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-streams-streamserver').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0px;"><b>{{ lang._('Data Streams')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-streams-streamserver" href="#subtab_nginx-streams-streamserver">{{ lang._('Stream Servers')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-streams-snifwd" href="#subtab_nginx-streams-snifwd">{{ lang._('SNI Based Routing')}}</a>
            </li>
        </ul>
    </li>
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown"
           href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_nginx-access-request-limit').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0px;"><b>{{ lang._('Access')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-access-request-limit" href="#subtab_nginx-access-request-limit">{{ lang._('Limit Zone')}}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_nginx-access-request-limit-connection" href="#subtab_nginx-access-request-limit-connection">{{ lang._('Connection Limits')}}</a>
            </li>
        </ul>
    </li>
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':settings]) }}
    <div id="subtab_nginx-http-location" class="tab-pane fade">
        <table id="grid-location" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="locationdlg">
            <thead>
            <tr>
                <th data-column-id="description" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="urlpattern" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('URL Pattern') }}</th>
                <th data-column-id="path_prefix" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('URL Path Prefix') }}</th>
                <th data-column-id="matchtype" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Match Type') }}</th>
                <th data-column-id="enable_secrules" data-type="boolean" data-sortable="true"  data-visible="true">{{ lang._('WAF Enabled') }}</th>
                <th data-column-id="force_https" data-type="boolean" data-sortable="true"  data-visible="true">{{ lang._('Force HTTPS') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div id="subtab_nginx-http-upstream-server" class="tab-pane fade">
        <table id="grid-upstreamserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="upstreamserverdlg">
            <thead>
            <tr>
                <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="server" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Server') }}</th>
                <th data-column-id="port" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Port') }}</th>
                <th data-column-id="priority" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Priority') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>


    <div id="subtab_nginx-http-upstream" class="tab-pane fade">
        <table id="grid-upstream" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="upstreamdlg">
            <thead>
            <tr>
                <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="serverentries" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Servers') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-credential" class="tab-pane fade">
        <table id="grid-credential" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="credentialdlg">
            <thead>
            <tr>
                <th data-column-id="username" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-userlist" class="tab-pane fade">
        <table id="grid-userlist" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="userlistdlg">
            <thead>
            <tr>
                <th data-column-id="name" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Name') }}</th>
                <th data-column-id="users" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Users') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-httpserver" class="tab-pane fade">
        <table id="grid-httpserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="httpserverdlg">
            <thead>
                <tr>
                    <th data-column-id="servername" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Servername') }}</th>
                    <th data-column-id="https_only" data-type="boolean" data-sortable="true" data-visible="true">{{ lang._('HTTPS Only') }}</th>
                    <th data-column-id="certificate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Certificate') }}</th>
                    <th data-column-id="listen_http_port" data-type="string" data-sortable="true" data-visible="true">{{ lang._('HTTP Port') }}</th>
                    <th data-column-id="listen_https_port" data-type="string" data-sortable="true" data-visible="true">{{ lang._('HTTPS Port') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-streams-streamserver" class="tab-pane fade">
        <table id="grid-streamserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="streamserverdlg">
            <thead>
                <tr>
                    <th data-column-id="certificate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Certificate') }}</th>
                    <th data-column-id="udp" data-type="string" data-sortable="true" data-visible="true">{{ lang._('UDP') }}</th>
                    <th data-column-id="listen_port" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Port') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-rewrite" class="tab-pane fade">
        <table id="grid-httprewrite" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="httprewritedlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="source" data-type="boolean" data-sortable="true" data-visible="true">{{ lang._('Source URL') }}</th>
                    <th data-column-id="destination" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Destination URL') }}</th>
                    <th data-column-id="flag" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Flag') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-custompolicy" class="tab-pane fade">
        {% if (show_naxsi_download_button) %}
        <div class="alert alert-info" id="naxsiruledownloadalert" role="alert" style="vertical-align: middle;display: table;width: 100%;">
            <div style="display: table-cell;vertical-align: middle;">{{ lang._('It looks like you are not having any rules installed. You may want to download the NAXSI core rules.') }}</div>
            <div class="pull-right" style="vertical-align: middle;display: table-cell;">
                <button id="naxsiruledownloadbtn" class="btn btn-primary">
                    <i class="fa fa-download" aria-hidden="true"></i> {{ lang._('Download') }}
                </button>
            </div>
        </div>
        {% endif %}
        <table id="grid-custompolicy" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="custompolicydlg">
            <thead>
                <tr>
                    <th data-column-id="name" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="operator" data-type="boolean" data-sortable="true" data-visible="true">{{ lang._('Operator') }}</th>
                    <th data-column-id="value" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Value') }}</th>
                    <th data-column-id="action" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Action') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-naxsirule" class="tab-pane fade">
        <table id="grid-naxsirule" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="naxsiruledlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="ruletype" data-type="boolean" data-sortable="true" data-visible="true">{{ lang._('Rule Type') }}</th>
                    <th data-column-id="message" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Message') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-security_header" class="tab-pane fade">
        <table id="grid-security_header" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="security_headersdlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-http-cache_path" class="tab-pane fade">
        <table id="grid-cache_path" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="cache_pathdlg">
            <thead>
                <tr>
                    <th data-column-id="path" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Path') }}</th>
                    <th data-column-id="size" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="inactive" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="max_size" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-access-request-limit" class="tab-pane fade">
        <table id="grid-limit_zone" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="limit_zonedlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="key" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Key') }}</th>
                    <th data-column-id="size" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Size') }}</th>
                    <th data-column-id="rate" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Rate') }}</th>
                    <th data-column-id="rate_unit" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Rate Unit') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-access-request-limit-connection" class="tab-pane fade">
        <table id="grid-limit_request_connection" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="limit_request_connectiondlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="limit_zone" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Limit Zone') }}</th>
                    <th data-column-id="connection_count" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Connection Count') }}</th>
                    <th data-column-id="burst" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Burst') }}</th>
                    <th data-column-id="nodelay" data-type="string" data-sortable="true" data-visible="true">{{ lang._('No Delay') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_nginx-streams-snifwd" class="tab-pane fade">
        <table id="grid-snifwd" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="sni_hostname_mapdlg">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string" data-sortable="true" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>


{{ partial("layout_partials/base_dialog",['fields': upstream,'id':'upstreamdlg', 'label':lang._('Edit Upstream')]) }}
{{ partial("layout_partials/base_dialog",['fields': upstream_server,'id':'upstreamserverdlg', 'label':lang._('Edit Upstream')]) }}
{{ partial("layout_partials/base_dialog",['fields': location,'id':'locationdlg', 'label':lang._('Edit Location')]) }}
{{ partial("layout_partials/base_dialog",['fields': credential,'id':'credentialdlg', 'label':lang._('Edit Credential')]) }}
{{ partial("layout_partials/base_dialog",['fields': userlist,'id':'userlistdlg', 'label':lang._('Edit User List')]) }}
{{ partial("layout_partials/base_dialog",['fields': httpserver,'id':'httpserverdlg', 'label':lang._('Edit HTTP Server')]) }}
{{ partial("layout_partials/base_dialog",['fields': streamserver,'id':'streamserverdlg', 'label':lang._('Edit Stream Server')]) }}
{{ partial("layout_partials/base_dialog",['fields': httprewrite,'id':'httprewritedlg', 'label':lang._('Edit URL Rewrite')]) }}
{{ partial("layout_partials/base_dialog",['fields': naxsi_custom_policy,'id':'custompolicydlg', 'label':lang._('Edit WAF Policy')]) }}
{{ partial("layout_partials/base_dialog",['fields': naxsi_rule,'id':'naxsiruledlg', 'label':lang._('Edit Naxsi Rule')]) }}
{{ partial("layout_partials/base_dialog",['fields': security_headers,'id':'security_headersdlg', 'label':lang._('Edit Security Headers')]) }}
{{ partial("layout_partials/base_dialog",['fields': limit_request_connection,'id':'limit_request_connectiondlg', 'label':lang._('Edit Request Connection Limit')]) }}
{{ partial("layout_partials/base_dialog",['fields': limit_zone,'id':'limit_zonedlg', 'label':lang._('Edit Limit Zone')]) }}
{{ partial("layout_partials/base_dialog",['fields': cache_path,'id':'cache_pathdlg', 'label':lang._('Edit Cache Path')]) }}
{{ partial("layout_partials/base_dialog",['fields': sni_hostname_map,'id':'sni_hostname_mapdlg', 'label':lang._('Edit SNI Hostname Mapping')]) }}
