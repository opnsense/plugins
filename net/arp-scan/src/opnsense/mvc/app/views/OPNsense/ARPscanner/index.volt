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
        
        
        // CSS fixtures
        $('.table-responsive td').css('padding-left', '17px');
        // end CSS fixtures
        
    
        var data_get_map = {'frm_GeneralSettings':"/api/arpscanner/settings/get"};
        //~ console.log(data_get_map);
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
            formatTokenizersUI();
            $('select').selectpicker('refresh');
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/arpscanner/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/arpscanner/service/reload", sendData={},callback=function(data,status) {
                // action to run after reload
                });
                
                // useless test
                //~ data_get_map = {'frm_GeneralSettings':"/api/arpscanner/settings/get"};
                //~ mapDataToFormUI(data_get_map).done(function(data){
                    //~ $('select').selectpicker('refresh');
                //~ });
                        
            });
        });


        $("#stopScanner").click(function(){
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/arpscanner/service/stop", sendData={},callback=function(data,status) {
                // action to run after reload
                //~ console.log(data);
                $("#scan_progress").removeClass("fa fa-spinner fa-pulse");
                });
        });

        $("#startScanner").click(function(){
            //~ $("#responseMsg").removeClass("hidden");
            $("#scan_progress").addClass("fa fa-spinner fa-pulse");
            
            var ifname = $('#arpscanner\\.general\\.interface option:selected')[0].value;
            var networks = $('#arpscanner\\.general\\.networks').val();
            //~ console.log(networks);
            ajaxCall(url="/api/arpscanner/service/start", 
            sendData={'interface':ifname, 'networks': networks},
            callback=function(data,status) {
                // action to run after reload
                //~ console.log(data);
                $("#ifname").text(data['interface']);
                $("#datetime").text(data['datetime']);
                $('#netTable tr').slice(2).remove()
                
                $.each(data['networks'], function(key_x,network) {
                    //~ console.log(x,y);
                    $.each(network, function(key_z,node){
                        //~ console.log(q);
                        ip = node[0];
                        mac = node[1];
                        vendor = node[2];
                        network = node[3];
                        $('#netTable tr:last').after("<tr><td>"+ip+"</td><td>"+mac+"</td><td>"+vendor+"</td><td>"+network+"</td></tr>")
                    })
                })
                $("#scan_progress").removeClass("fa fa-spinner fa-pulse");
            });
            
        });
    });
</script>

<section class="col-xs-12">
    <div class="alert alert-info" role="alert" style="min-height: 65px;">
        <div class="pull-left updatestatus" style="margin-top: 8px;">{{ lang._('Scan is stopped')}}</div>
        <div class="pull-left updatestatus" style="margin-top: 8px; display:none;">{{ lang._('Scan is running')}}</div>   
<!--
        <button class='btn btn-primary pull-right' id="audit">{{ lang._('Audit now') }} <i id="audit_progress"></i></button>             
-->
    </div>        
    
    <div class="content-box">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
        <div class="col-md-12">
            <button class="btn btn-primary pull-right"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
        </div>
        
        <div class="col-md-12" style="padding-bottom: 13px;">
            <button class='btn btn-default' id="stopScanner" style="margin-right: 8px;">{{ lang._('Stop') }} <i id=""></i></button>            
            <button class="btn btn-primary pull-center"  id="startScanner" type="button"><i id="scan_progress" class=""></i><b>{{ lang._('Start') }}</b></button>
        </div>
    </div>
</section>

<section class="col-xs-12">
    <div id="responseMsg" class="content-box" style="padding: 27px;">
        
        <table>
            <thead>
                <tr>
                    <th><b>{{ lang._('Interface name') }}</b></th>
                    <th><b>{{ lang._('Date time') }}</b></th>
                </tr>
            </thead>
            <tbody>
                <td><p id="ifname"></p></td>
                <td><p id="datetime"></p></td>
            </tbody>
            
        </table>
        <hr>
        <table id="netTable">
            <thead>
                <tr>
                    <td><b>{{ lang._('IP') }}</b></td>
                    <td><b>{{ lang._('MAC') }}</b></td>
                    <td><b>{{ lang._('Vendor') }}</b></td>
                    <th><b>{{ lang._('Network') }}</b></th>                    
                </tr>
            </thead>
            <tbody>
                <tr><td></td><td></td><td></td><td></td></tr>
            </tbody>
        </table>
        
    </div>
<section>
