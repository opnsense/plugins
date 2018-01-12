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


<script>

    function flush_table(){
        $('#netTable tr').slice(2).remove()
    }

    var check_state = 0;
    function check_scanner_status(ifname){
        // sets 0 if stopped and 1 if running
        sendData={'interface': ifname }
        //~ // action to run after successful save, for example reconfigure service.
        ajaxCall(url="/api/arpscanner/service/check", sendData, callback=function(data,status) {
            // action to run after reload
            if (data.length >= 1){
                $("#update_stop").hide();
                $("#update_start").show();
                check_state = 1;
                $("#scan_progress").addClass("fa fa-spinner fa-pulse");
                $("#startScanner").addClass("disabled")
                setTimeout(function(){
                    get_status(ifname)
                }, 2000 );
            } else {
                $("#update_stop").show();
                $("#update_start").hide();
                check_state = 0;
                $("#scan_progress").removeClass("fa fa-spinner fa-pulse");
                $("#startScanner").removeClass("disabled")
            }
        });
    }

    function get_status(ifname){
        flush_table();
        ajaxCall(url="/api/arpscanner/service/status",
                sendData={'interface':ifname},
                callback=function(data,status) {
                    $.each(data['peers'], function(key_x,peer) {
                        //~ console.log(peer);
                        ip = peer[0];
                        mac = peer[1];
                        vendor = peer[2];
                        $('#netTable tr:last').after("<tr><td>"+ip+"</td><td>"+mac+"</td><td>"+vendor+"</td></tr>")
                    })
                check_scanner_status(ifname)
                $("#ifname").text(ifname);
                $("#started").text(data['started']);
                $("#last").text(data['last']);
            });
        }

    $( document ).ready(function() {

        var data_get_map = {'frm_GeneralSettings': "/api/arpscanner/settings/get"};
        //~ console.log(data_get_map);
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
            formatTokenizersUI();
            $('select').selectpicker('refresh');
            // check if the scanner is already running
            first_status = $('#arpscanner\\.general\\.interface option:selected')[0].value;
            check_scanner_status(first_status);
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/arpscanner/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/arpscanner/service/reload", sendData={},callback=function(data,status) {
                // action to run after reload
                });

            });
        });

        $("#statusScanner").click(function(){
            value = $('#arpscanner\\.general\\.interface option:selected')[0].value;
            get_status(value);
        });

        $("#stopScanner").click(function(){
            // action to run after successful save, for example reconfigure service.
            value = $('#arpscanner\\.general\\.interface option:selected')[0].value;
            sendData={'interface': value }
            ajaxCall(url="/api/arpscanner/service/stop", sendData, callback=function(data,status) {
            // action to run after reload
            //~ console.log(data);
            $("#scan_progress").removeClass("fa fa-spinner fa-pulse");
            check_scanner_status(value);
            });
        });

        // CHECK STATUS
        // check the status opf the scanner on selected interface
        $("#arpscanner\\.general\\.interface").change(function(){
            value = $('#arpscanner\\.general\\.interface option:selected')[0].value;
            get_status(value);
            $("#ifname").text(value);
            $("#started").text('');
            $("#last").text('');
        });


        $("#startScanner").click(function(){
            //~ $("#responseMsg").removeClass("hidden");
            $("#scan_progress").addClass("fa fa-spinner fa-pulse");
            var ifname = $('#arpscanner\\.general\\.interface option:selected')[0].value;
            var networks = $('#arpscanner\\.general\\.networks').val();
            ajaxCall(url="/api/arpscanner/service/start",
                sendData={'interface':ifname, 'networks': networks},
                callback=function(data,status) {
                    // action to run after reload
                    //~ console.log(data);
                    $("#ifname").text(data['interface']);
                    $("#started").text(data['started']);
                    $("#last").text(data['last']);
                    flush_table();
                    check_scanner_status(ifname);
                });
        });


    }); // END
</script>

<section class="col-xs-12">
    <div  id="update_stop" class="alert alert-info" role="alert" style="min-height: 65px;">
        <div class="pull-left updatestatus" style="margin-top: 8px;">{{ lang._('Scan is stopped')}}</div>
    </div>

    <div id="update_start"  class="alert alert-warning" role="alert" style="min-height: 65px; display:none;">
        <div class="pull-left updatestatus" style="margin-top: 8px;">{{ lang._('Scan is running')}}</div>
    </div>

    <div class="content-box">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}

        <div class="col-md-12" style="padding-bottom: 13px; padding-top: 13px;">
            <button class='btn btn-default' id="stopScanner" style="margin-right: 8px;">{{ lang._('Stop') }} <i id=""></i></button>
            <button class='btn btn-default' id="statusScanner" style="margin-right: 8px;">{{ lang._('Refresh') }} <i id=""></i></button>
            <button class="btn btn-primary pull-center"  id="startScanner" type="button"><i id="scan_progress" class=""></i><b>{{ lang._('Start') }}</b></button>
        </div>
    </div>
</section>

<section class="col-xs-12">
    <div id="responseMsg" class="content-box" style="padding: 27px;">
        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><b>{{ lang._('Interface name') }}</b></th>
                    <th><b>{{ lang._('Started') }}</b></th>
                    <th><b>{{ lang._('Last update') }}</b></th>
                </tr>
            </thead>
            <tbody>
                <td><p id="ifname"></p></td>
                <td><p id="started"></p></td>
                <td><p id="last"></p></td>
            </tbody>

        </table>
        <hr>
        <table id="netTable">
            <thead>
                <tr>
                    <td><b>{{ lang._('IP') }}</b></td>
                    <td><b>{{ lang._('MAC') }}</b></td>
                    <td><b>{{ lang._('Vendor') }}</b></td>
                </tr>
            </thead>
            <tbody>
                <tr><td></td><td></td><td></td></tr>
            </tbody>
        </table>
        </div>
     </div>
<section>
