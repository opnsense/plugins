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

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#about">{{ lang._('About') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':settingsForm,'id':'frm_Settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAction" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
                <button class="btn btn-primary"  id="testAction" type="button"><b>{{ lang._('Test Credentials') }}</b></button>
            </div>
        </div>
    </div>
    <div id="about" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">

            <div  class="col-md-12">
                <h1>Configuration Sync</h1>
                <p>
                    Configuration Sync is a tool designed to one-way synchronize the system 
                    configuration files from the OPNsense host to S3 compatible object data 
                    storage in close to real time.  While the tool has the side effect of
                    being a great configuration backup tool the intent is to provide a tool
                    that stores the OPNsense system configuration in a location that is
                    readily addressable in DevOps automation scenarios.
                </p>
                
                <p>
                    The ability to start an OPNsense instance using automation tools means
                    OPNsense becomes a first-class choice for building and managing network 
                    infrastructure within cloud-compute providers.
                </p>
                
                <h2>Stored Configurations</h2>
                <p>
                    The user-interface within the Configuration Sync plugin provides no
                    direct way to access or download the configuration files that
                    have been synced out to the storage-provider.  Use the storage-provider
                    web-console or other toolchain to access and download as required.
                </p>
                
                <h2>Supported Versions</h2>
                <p>
                    Configuration Sync is a tool requires OPNsense 18.7.1 or better
                    to correctly display alert-message dialogues in the Settings page.  The
                    alert-messages are purely a cosmetic user-interface thing and previous
                    OPNsense versions are known to work under the hood even if the user
                    message appears to get "stuck" and not progress.
                </p>
                
                <h2>Example AWS IAM policy</h1>
                <p>Consider the AWS IAM policy below to restrict the resources and actions available 
                    to the AWS access-key used.  Of particular note is that this policy does not 
                    require <code>GetObject</code> actions which hence prevents these credentials
                    being re-purposed to gain access to previous system configurations via AWS-S3
                    storage.
                </p>
<pre>{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [ "s3:ListBucket" ],
            "Resource": [ "arn:aws:s3:::BUCKET_NAME" ]
        },
        {
            "Effect": "Allow",
            "Action": [ "s3:ListBucket", "s3:PutObject" ],
            "Resource": [ "arn:aws:s3:::BUCKET_NAME/PATH_NAME/*" ]
        }
    ]
}</pre>
                <p><strong>Note:</strong> Be sure to replace <code>BUCKET_NAME</code> and <code>PATH_NAME</code> with your own values above.</p>

                <hr />
                
                <h1>Author</h1>
                <p>Configuration Sync is a Verb Networks plugin for OPNsense - we make other tools for OPNsense too!</p>

                <h1>License</h1>
                <p>BSD-2-Clause - see LICENSE file for full details.</p>

                <h1>Copyright</h1>
                <p>Copyright 2018 - All rights reserved - <a href="https://verbnetworks.com/">Verb Networks Pty Ltd</a></p>
                
            </div>

        </div>
    </div>
</div>

<style>
    #configsync\.settings\.StorageFullURI, #configsync\.settings\.SystemHostid {
        line-height: 34px;
        display: inline-block;
        vertical-align: middle;
        
        font-size: 80%;
        font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
    }
</style>

<script>
    
    /**
     * updateFullURI
     */
    function updateFullURI() {
        if($('#configsync\\.settings\\.Provider').val() === 'awss3') {
            var link_url = 'https://s3.console.aws.amazon.com/s3/buckets/';
            link_url += $('#configsync\\.settings\\.StorageBucket').val() + '/';
            link_url += $('#configsync\\.settings\\.StoragePath').val() + '/';

            var link_text = 's3://';
            link_text += $('#configsync\\.settings\\.StorageBucket').val() + '/';
            link_text += $('#configsync\\.settings\\.StoragePath').val();
        }
        $('#configsync\\.settings\\.StorageProviderLink').html(
            $('<a/>', {
                text: link_text,
                target: '_blank',
                href: link_url,
            })
        );
    }
    
    $(document).ready(function() {
        
        updateServiceControlUI('configsync');
        
        $("#configsync\\.settings\\.StorageBucket").change(updateFullURI);
        $("#configsync\\.settings\\.StoragePath").change(updateFullURI);
        
        mapDataToFormUI({frm_Settings:"/api/configsync/settings/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateFullURI();
        });

        $("#testAction").click(function(){
            $("#responseMsg").removeClass("hidden").removeClass("alert-danger").addClass('alert-info').html("{{ lang._('Running tests') }}...");
            saveFormToEndpoint(
                url = '/api/configsync/settings/test', 
                formid ='frm_Settings', 
                callback_ok = function(data){
                    if(data['status'] !== 'success') {
                        $("#responseMsg").removeClass("alert-info").addClass('alert-danger');
                    }
                    $("#responseMsg").html(data['message']);
                },
                disable_dialog = true
            );
        });
        
        $("#saveAction").click(function(){
            $("#responseMsg").removeClass("hidden").removeClass("alert-danger").addClass('alert-info').html("{{ lang._('Saving settings') }}...");
            saveFormToEndpoint(
                url = '/api/configsync/settings/set', 
                formid = 'frm_Settings', 
                callback_ok = function(){
                    $("#responseMsg").html("{{ lang._('Configuration Sync service settings saved') }}.");
                    ajaxCall(url = "/api/configsync/service/reload", sendData = {}, callback = function(data, status) {
                        $("#responseMsg").html("{{ lang._('Configuration Sync service settings saved and reloaded') }}.");
                    });
                },
                disable_dialog = true 
            );
            updateServiceControlUI('configsync');
        });
    });
</script>
