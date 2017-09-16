{#

Copyright © 2017 Giuseppe De Marco <giuseppe.demarco@unical.it>
All rights reserved.

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


<script type="text/javascript">
    $( document ).ready(function() {
        var data_get_map = {'frm_GeneralSettings':"/api/arpscanner/settings/get"};
        console.log(data_get_map);
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
                formatTokenizersUI();
                $('select').selectpicker('refresh');
        });

        // link save button to API set action
        $("#start_arpscanner").click(function(){
            saveFormToEndpoint(url="/api/arpscanner/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                // action to run after successful save, for example reconfigure service.
            });
        });


    });
</script>

<script type="text/javascript">

    /**
     * prepare for checking scan status
     */
    //~ function scanStatusPrepare(rerun) {
        //~ if ($rerun = false) {
            //~ $('#scan_status').hide();
            //~ $('#scanlist').show();
        //~ }
        //~ $("#checkscan_progress").addClass("fa fa-spinner fa-pulse");
        //~ $('#scanstatus').html("{{ lang._('Checking... (may take up to 30 seconds)') }}");
    //~ }

    /**
     * retrieve scan status from backend
     */
    function scanStatus() {
        // scan UI
        //~ scanStatusPrepare(false);

        // request status
        ajaxGet('/api/arpscanner/status',{},function(data,status){
            $("#checkscan_progress").removeClass("fa fa-spinner fa-pulse");
            $('#scanstatus').html(data['status_msg']);

            if (data['status'] == "1") {

                //~ run audit
                
            }
        });
    }

    /**
     * perform upgrade, install poller to scan status
     */
    function stopScan() {
        $('#scanlist').hide();
        $('#scan_status').show();
        $('#scantab > a').tab('show');
        $('#scanstatus').html("{{ lang._('Killing...') }}");
        $("#audit").attr("style","display:none");
        maj_suffix = '';
        if ($.upgrade_action == 'maj') {
            maj_suffix = '_maj';
        }
        $("#upgrade" + maj_suffix).attr("style","");
        $("#upgrade_progress" + maj_suffix).addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/arpscanner/stop',{upgrade:$.upgrade_action},function() {
            $('#scanlist').empty();
            setTimeout(trackStatus, 500);
        });
    }

    /**
     * perform audit, install poller to scan status
     */
    function audit() {
        $.upgrade_action = 'audit';
        $('#scanlist').hide();
        $('#scan_status').show();
        $('#scantab > a').tab('show');
        $('#scanstatus').html("{{ lang._('Auditing...') }}");
        $("#audit").attr("style","");
        $("#audit_progress").addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/arpscanner/audit', {}, function () {
            $('#scanlist').empty();
            setTimeout(trackStatus, 500);
        });
    }
</script>


<section class="col-xs-12">
    
    <div class="alert alert-info" role="alert" style="min-height: 65px;">
        <div class="pull-left" style="margin-top: 8px;" id="updatestatus">{{ lang._('ARPscanner is stopped')}}</div>
        <div class="pull-left" style="margin-top: 8px; display:none;" id="updatestatus">{{ lang._('ARPscanner is running')}}</div>   
        <button class='btn btn-primary pull-right' id="audit">{{ lang._('Audit now') }} <i id="audit_progress"></i></button>             
    </div>        
    
    <div class="content-box">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
        <div class="col-md-12" style="padding-bottom: 13px;">
            <button class='btn btn-default' id="stop_arpscanner" style="margin-right: 8px;">{{ lang._('Stop') }} <i id=""></i></button>            
            <button class="btn btn-primary pull-center"  id="start_arpscanner" type="button"><b>{{ lang._('Start') }}</b></button>
        </div>
    </div>
</section>

