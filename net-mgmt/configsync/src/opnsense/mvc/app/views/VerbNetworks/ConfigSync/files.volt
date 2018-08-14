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

<script>
    
    function updateGridFilesTable() {
    
        $("#grid-files").UIBootgrid(
            {   search:'/api/configsync/files/list',
                options:{
                    rowCount:[10, 25, 100, -1] ,
                    ajax: true,
                    url: "/api/configsync/files/list",
                },                
            },
        );    
    }

    $(document).ready(function() {
        updateServiceControlUI('configsync');
        updateGridFilesTable();
    });

</script>

<div class="container-fluid">
    <div class="row">
        <div class="alert alert-info hidden" role="alert" id="responseMsg"></div>
    </div>
    <div class="row">
        <div class="col-md-12" id="content">
            
            <table id="grid-files" class="table table-condensed table-hover table-striped table-responsive">
                <thead>
                <tr>
                    <th data-column-id="timestamp_created" data-width="14em" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Created') }}</th>
                    <th data-column-id="timestamp_synced" data-width="14em" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Synced') }}</th>
                    <th data-column-id="path" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Storage Provider Path') }}</th>
                    
                    <th data-column-id="storage_etag" data-type="string" data-sortable="false" data-visible="false">{{ lang._('Storage ETag') }}</th>
                    <th data-column-id="storage_class" data-type="string" data-sortable="false" data-visible="false">{{ lang._('Storage Class') }}</th>
                    <th data-column-id="storage_size" data-type="string" data-sortable="false" data-visible="false">{{ lang._('Storage Size') }}</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            &nbsp;
        </div>
    </div>
</div>
