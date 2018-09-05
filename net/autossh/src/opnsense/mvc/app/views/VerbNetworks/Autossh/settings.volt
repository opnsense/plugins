{#
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#}

<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>
<div class="alert alert-info hidden" role="alert" id="applyChangesMessage">
   <button class="btn btn-primary pull-right" id="btnApplyChanges" type="button"><b>{{ lang._('Apply changes') }}</b> <i id="btnApplyChangesProgress"></i></button>
   {{ lang._('Configuration changed, please apply changes to reconfigure the Autossh service tunnels.')}}<br><br>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#tunnels">{{ lang._('Tunnels') }}</a></li>
    <li><a data-toggle="tab" href="#keys">{{ lang._('Keys') }}</a></li>
    <li><a data-toggle="tab" href="#about">{{ lang._('About') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    
    <div id="tunnels" class="tab-pane fade in active">
        <table id="grid-tunnels" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogTunnels">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="row_toggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('Id') }}</th>
                <th data-column-id="connection" data-type="string" data-visible="true">{{ lang._('Connection') }}</th>
                <th data-column-id="local_forward" data-type="string" data-visible="true" data-formatter="code_wrap">{{ lang._('Local Forward') }}</th>
                <th data-column-id="dynamic_forward" data-type="string" data-visible="true" data-formatter="code_wrap">{{ lang._('Dynamic Forward') }}</th>
                <th data-column-id="remote_forward" data-type="string" data-visible="true" data-formatter="code_wrap">{{ lang._('Remote Forward') }}</th>
                <th data-column-id="commands" data-width="10em" data-formatter="tunnel_commands" data-sortable="false">{{ lang._('Commands') }}</th>
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

    <div id="keys" class="tab-pane fade in">
        <table id="grid-keys" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogKeys">
            <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('Id') }}</th>
                <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                <th data-column-id="key_fingerprint" data-type="string" data-visible="true">{{ lang._('SSH Key Fingerprint') }}</th>
                <th data-column-id="type" data-width="8em" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                <th data-column-id="timestamp" data-width="14em" data-type="string" data-visible="true">{{ lang._('Created') }}</th>
                <th data-column-id="commands" data-width="10em" data-formatter="key_commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    
    <div id="about" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">

            <div  class="col-md-12">
                <h1>Autossh</h1>
                <p>
                    The Autossh plugin for OPNsense is a tool for establishing, maintaining and managing 
                    reliable SSH tunnels with remote hosts.  It can be used to solve a wide range of 
                    connection challenges through the (sometimes creative) use of TCP port-forwards.
                </p>
                
                <!-- documentation work in progress -->
                
                <!--
                <h2>Tunnel configuration</h2>
                <h3>Local Forward</h3>
                <p>
                    Describe how to expose a remote TCP port into the local network
                </p>
                
                <h3>Remote Forward</h3>
                <p>
                    Describe how to expose a TCP port in the local network at a remote system
                </p>
                
                <h3>Dynamic Forward</h3>
                <p>
                    Describe how to write an expression that creates a SOCKS proxy for the local network
                </p>
                
                <h3>Gateway Ports</h3>
                <p>
                    Describe the situations where this is important and required
                </p>
                
                <h3>Strict Host Key Checking</h3>
                <p>
                    Describe what this is all about and the interaction with the "Update Host Keys" property
                </p>
                
                <h2>Key management</h2>
                <h3>Private Key</h3>
                <p>
                    Describe how keys are stored and the potential risks
                    Describe the key types and the sometimes limited support for newer key types
                </p>
                
                <h3>Public Key</h3>
                <p>
                    Describe how to access it
                    Describe the importance of the key permission prefix to prevent abuse
                    Describe where to place the public key value on the remote system
                </p>
                
                <h3>External Keys</h3>
                <p>
                    Describe that no external keys are currently possible as a matter of preventing unwanted problem scenarios
                    Willing to listen to feedback and introduce a key import feature if warranted
                </p>
                
                <h2>Connection status</h2>
                <p>
                    Notes about forwards
                    Description of status attributes
                    Describe the autossh health check with a "ping" every minute
                </p>
                
                -->
                
                <hr />
                
                <h2>Features</h2>
                <ul>
                    <li>Default ssh-key permissions that prevent unwanted remote ssh-server shell access abuses.</li>
                    <li>Ability to define local-forward and remote-forward TCP tunnels.</li>
                    <li>Ability to define local network SOCKS proxy via a remote host (aka dynamic-forward).</li>
                    <li>Ability to bind outbound ssh connections to different (external) interfaces.</li>
                    <li>Ability to configure many (27x) of the ssh-client connection parameters, including all cryptographic options.</li>
                    <li>Ability to observe the health status of the tunnel at a glance.</li>
                    <li>Can rely on Autossh to reestablish a tunnel after a connectivity outage.</li>
                </ul>
                
                <h2>Various use cases</h2>
                <ul>
                    <li>Provide remote network access to a site that has no public addresses, such as when ISPs use NAT.</li>
                    <li>Ensure redundant multipath remote access via primary and secondary connections via interface binding.</li>
                    <li>Create your own "privacy" VPN system for all local network users using a SOCKS proxy (dynamic-forward) to a remote system.</li>
                    <li>Provide local network access to remote system services such as a SMTP relay or another SSH service.</li>
                    <li>Provide remote system access to a local network services such as a database or RDP service.</li>
                    <li>Provide access remote system access to other remote network acting as a middle-man TCP-port connector.</li>
                    <li>... just because you can, does not mean you should.</li>
                </ul>
                
                <hr />
                
                <h1>Author</h1>
                <p>Autossh is a Verb Networks plugin for OPNsense - we make other tools for OPNsense too!</p>

                <h1>License</h1>
                <p>BSD-2-Clause - see LICENSE file for full details.</p>

                <h1>Copyright</h1>
                <p>Copyright 2018 - All rights reserved - <a href="https://verbnetworks.com/">Verb Networks Pty Ltd</a></p>
                
            </div>

        </div>
    </div>
</div>

{# include dialogs #}
{{ partial('layout_partials/base_dialog',['fields':formDialogKeys,'id':'DialogKeys','label':lang._('SSH key')])}}
{{ partial('layout_partials/base_dialog',['fields':formDialogTunnels,'id':'DialogTunnels','label':lang._('SSH tunnel')])}}

<style>
    div.type-info div.modal-dialog div.modal-body div.bootstrap-dialog-message {
        padding: 0.5em;
        border: 1px solid #999999;
        font-size: 100%;
        font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
        word-break: break-all;  
    }
</style>

<script>
    
    $(document).ready(function() {
        
        $("#grid-tunnels").UIBootgrid(
            {   search:'/api/autossh/tunnels/search',
                get:'/api/autossh/tunnels/get/',
                set:'/api/autossh/tunnels/set/',
                add:'/api/autossh/tunnels/add/',
                del:'/api/autossh/tunnels/del/',
                info:'/api/autossh/tunnels/info/',
                toggle:'/api/autossh/tunnels/toggle/',
                
                options:{
                    ajax: true,
                    selection: true,
                    multiSelect: true,
                    rowCount:[10, 25, 100, -1] ,
                    formatters:{
                        tunnel_commands: function(column, row) {
                            return '<button type="button" class="btn btn-xs btn-default command-info"   data-row-id="' + row.uuid + '"><span class="fa fa-key"></span></button> ' +
                                   '<button type="button" class="btn btn-xs btn-default command-edit"   data-row-id="' + row.uuid + '"><span class="fa fa-pencil"></span></button> ' +
                                   '<button type="button" class="btn btn-xs btn-default command-copy"   data-row-id="' + row.uuid + '"><span class="fa fa-clone"></span></button> ' +
                                   '<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="' + row.uuid + '"><span class="fa fa-trash-o"></span></button>';
                        },
                        row_toggle: function (column, row) {
                            if (parseInt(row[column.id], 2) === 1) {
                                return '<span style="cursor: pointer;" class="fa fa-check-square-o command-toggle" data-value="1" data-row-id="' + row.uuid + '"></span>';
                            } else {
                                return '<span style="cursor: pointer;" class="fa fa-square-o command-toggle" data-value="0" data-row-id="' + row.uuid + '"></span>';
                            }
                        },
                        code_wrap: function(column, row) {
                            if(row[column.id].length > 0) {
                                return '<code>' + row[column.id] + '</code>';
                            }
                            return '';
                        },
                    }
                },                
            },
        );
        
        $("#grid-keys").UIBootgrid(
            {   search:'/api/autossh/keys/search',
                get:'/api/autossh/keys/get/',
                set:'/api/autossh/keys/set/',
                add:'/api/autossh/keys/add/',
                del:'/api/autossh/keys/del/',
                info:'/api/autossh/keys/info/',
                
                options:{
                    ajax: true,
                    selection: true,
                    multiSelect: true,
                    rowCount:[10, 25, 100, -1],
                    formatters:{
                        key_commands: function (column, row) {
                            return '<button type="button" class="btn btn-xs btn-default command-info"   data-row-id="' + row.uuid + '"><span class="fa fa-key"></span></button> ' +
                                   '<button type="button" class="btn btn-xs btn-default command-edit"   data-row-id="' + row.uuid + '"><span class="fa fa-pencil"></span></button> '+
                                   '<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="' + row.uuid + '"><span class="fa fa-trash-o"></span></button>';
                        }
                    }
                },                
            },
        );
        
        function setResponseMessageKeysCreate() {
            if ($('#grid-keys tbody tr').children().length <= 1) {
                $("#responseMsg").removeClass("hidden").removeClass("alert-danger").addClass('alert-info').html("{{ lang._('Please create an ssh-key before creating an ssh-tunnel.')}}");
            }
        }
        
        function checkApplyChangesMessage() {
            ajaxGet(url='/api/autossh/service/isConfigChange', sendData={}, callback=function(data,status) {
                if (status === 'success') {
                    if (data.data === true) {
                        $('#applyChangesMessage').removeClass('hidden');
                        $('#responseMsg').removeClass('hidden').addClass('hidden');
                    } else {
                        $('#applyChangesMessage').removeClass('hidden').addClass('hidden');
                    }
                }
            });
        }
        
        $('#btnApplyChanges').unbind('click').click(function(){
            $('#btnApplyChangesProgress').addClass('fa fa-spinner fa-pulse');
            ajaxCall(url='/api/autossh/service/reload', sendData={}, callback=function(data,status) {
                if (status === 'success') {
                    $('#responseMsg').removeClass('hidden').html(data.message);
                    $('#btnApplyChanges').blur();
                    $('#applyChangesMessage').removeClass('hidden').addClass('hidden');
                }
                $('#btnApplyChangesProgress').removeClass('fa fa-spinner fa-pulse');
           });
        });
        
        $('#grid-keys').bootgrid().on('loaded.rs.jquery.bootgrid', setResponseMessageKeysCreate);
        $('#grid-tunnels').bootgrid().on('loaded.rs.jquery.bootgrid', checkApplyChangesMessage);
        
        $('#DialogKeys').change(function() {
            if($('#key\\.key_fingerprint').text().length >= 1) {
                $('#row_key\\.type div.btn-group button.dropdown-toggle').attr('data-toggle', '');
            } else {
                $('#row_key\\.type div.btn-group button.dropdown-toggle').attr('data-toggle', 'dropdown');
            }
        });
        
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');

    });
    
</script>
