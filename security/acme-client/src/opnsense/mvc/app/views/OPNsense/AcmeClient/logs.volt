{#
 # Copyright (c) 2024 Frank Wall
 # Copyright (c) 2019 Deciso B.V.
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

<script>
    $( document ).ready(function() {
      // get entries from system log for 'AcmeClient'
      let grid_systemlog = $("#grid-systemlog").UIBootgrid({
          options:{
              // Hide nonfunctional search field
              navigation:2,
              sorting:false,
              rowSelect: false,
              selection: false,
              rowCount:[20,50,100,200,500,1000,-1],
              requestHandler: function(request){
                  // Show only log entries that match 'AcmeClient'
                  request['searchPhrase'] = 'acmeclient';
                  return request;
              },
          },
          search:'/api/diagnostics/log/core/system'
      });

      // get entries from acmeclient.log
      let grid_acmelog = $("#grid-acmelog").UIBootgrid({
          options:{
              sorting:false,
              rowSelect: false,
              selection: false,
              rowCount:[20,50,100,200,500,1000,-1],
          },
          search:'/api/diagnostics/log/core/acmeclient'
      });

      grid_systemlog.on("loaded.rs.jquery.bootgrid", function(){
          $(".action-page").click(function(event){
              event.preventDefault();
              $("#grid-systemlog").bootgrid("search",  "");
              let new_page = parseInt((parseInt($(this).data('row-id')) / $("#grid-log").bootgrid("getRowCount")))+1;
              $("input.search-field").val("");
              // XXX: a bit ugly, but clearing the filter triggers a load event.
              setTimeout(function(){
                  $("ul.pagination > li:last > a").data('page', new_page).click();
              }, 100);
          });
      });

      grid_acmelog.on("loaded.rs.jquery.bootgrid", function(){
          $(".action-page").click(function(event){
              event.preventDefault();
              $("#grid-acmelog").bootgrid("search",  "");
              let new_page = parseInt((parseInt($(this).data('row-id')) / $("#grid-log").bootgrid("getRowCount")))+1;
              $("input.search-field").val("");
              // XXX: a bit ugly, but clearing the filter triggers a load event.
              setTimeout(function(){
                  $("ul.pagination > li:last > a").data('page', new_page).click();
              }, 100);
          });
      });
    });
</script>


<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#systemlog"><b>{{ lang._('System Log') }}</b></a></li>
    <li><a data-toggle="tab" href="#acmelog">{{ lang._('ACME Log') }}</a></li>
</ul>

<div class="content-box tab-content">

    <div id="systemlog" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div  class="col-sm-12">
                <table id="grid-systemlog" class="table table-condensed table-hover table-striped table-responsive" data-store-selection="true">
                    <thead>
                    <tr>
                        <th data-column-id="timestamp" data-width="11em" data-type="string">{{ lang._('Date') }}</th>
                        <th data-column-id="process_name" data-width="11em" data-type="string">{{ lang._('Process') }}</th>
                        <th data-column-id="line" data-type="string">{{ lang._('Line') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="acmelog" class="tab-pane fade">
        <div class="content-box" style="padding-bottom: 1.5em;">
            <div  class="col-sm-12">
                <table id="grid-acmelog" class="table table-condensed table-hover table-striped table-responsive" data-store-selection="true">
                    <thead>
                    <tr>
                        <th data-column-id="timestamp" data-width="11em" data-type="string">{{ lang._('Date') }}</th>
                        <th data-column-id="process_name" data-width="11em" data-type="string">{{ lang._('Process') }}</th>
                        <th data-column-id="line" data-type="string">{{ lang._('Line') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
