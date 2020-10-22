{#
 # Copyright (c) 2020 Deciso B.V.
 # Copyright (c) 2020 Michael Muenz <m.muenz@gmail.com>
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

<div class="content-box" style="padding-bottom: 1.5em;">
    <div class="col-md-12">
        <hr />
    </div>
    <div id="hourly" class="tab-pane col-md-12 fade in">
      <pre id="listreport"></pre>
    </div>
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="reportAct" type="button"><b>{{ lang._('Generate') }}</b> <i id="reportAct_progress"></i></button>
    </div>
</div>

<script>
    $(function() {

        // link save button to API set action
        $("#reportAct").click(function(){
                $("#reportAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/hwprobe/service/report", sendData={}, callback=function(data,status) {
                    $("#listreport").text(data['response']);
                    $("#reportAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
        });
    });
</script>
