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
    
    function sortTable() {
        // Credit: https://www.w3schools.com/howto/howto_js_sort_table.asp
        var table, rows, switching, i, x, y, shouldSwitch;
        table = document.getElementById("filelist");
        switching = true;
        while (switching) {
            switching = false;
            rows = table.getElementsByTagName("tr");
            for (i = 1; i < (rows.length - 1); i++) {
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("td")[0];
                y = rows[i + 1].getElementsByTagName("td")[0];
                if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
            }
        }
    }    

    /**
     * updateFileTable
     */
    function updateFileTable() {
        
        $("#responseMsg").removeClass("hidden").removeClass("alert-danger").addClass('alert-info').html("Retreiving list of configuration files available at the storage-provider...");
        
        ajaxGet('/api/configsync/files/get', {}, function (data, status) {
            if(status == 'parsererror' || data['status'] != 'success') {
                $("#responseMsg").addClass("alert-danger").removeClass('alert-info').html("Unable to retrieve list of configuration files at the storage-provider");
                $('#filelist > tbody').empty();
            }
            else {
                $("#responseMsg").addClass('hidden').html("");
                $('#filelist > tbody').empty();
                $.each(data['data'], function(filename, filedata) {
                    $('#filelist > tbody').append(
                        '<tr>' +
                        '<td>' + filedata['Created'] + '</td>' +
                        '<td>' + filedata['LastModified'] + '</td>' +
                        '<td>' + filedata['Key'] + '</td>' +
                        '</tr>'
                    );
                });
                sortTable();
            }
        });
    }

    /**
     * $(document).ready
     */
    $(document).ready(function() {
        updateServiceControlUI('configsync');
        updateFileTable(true);
    });
    
</script>


<div class="container-fluid">
    <div class="row">
        <div class="alert alert-info hidden" role="alert" id="responseMsg"></div>
    </div>
    <div class="row">
        <div class="col-md-12" id="content">
            
            <table class="table table-striped table-condensed table-responsive" id="filelist">
              <thead>
                <tr>
                  <th>{{ lang._('Created') }}</th>
                  <th>{{ lang._('Synced') }}</th>
                  <th>{{ lang._('Storage Provider Path') }}</th>
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
