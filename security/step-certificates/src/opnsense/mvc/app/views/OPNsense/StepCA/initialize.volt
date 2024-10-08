{#

Copyright (C) 2024 Volodymyr Paprotski

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
    $( document ).ready(function() {
        updateServiceControlUI('stepca');
        ajaxCall(url="/api/stepca/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });

        $("#Initialize\\.root\\.CreateTemplate,#Initialize\\.intermediate\\.CreateTemplate").css({"font-family": "'Courier New'"});

        // load initial data
        var data_get_map = {'frm_InitializeSettings':"/api/stepca/initialize/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // link apply button to API set action
        $("#reconfigureAct").click(function(){
            var sourceR = $("#Initialize\\.root\\.Source").val();
            var sourceI = $("#Initialize\\.intermediate\\.Source").val();
            var message = '{{ lang._('Continue?') }}';
            if (sourceR == 'yubikeyC' || sourceI == 'yubikeyC') {
                message = '{{ lang._('This will destroy existing CA keys! Continue?') }}';
            }
            stdDialogConfirm(
                '{{ lang._('Confirm CA Initialization') }}', message,
                '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                    ajaxCall(url="/api/stepca/service/stop", sendData={}, callback=function(data,status) {
                        $("#frm_InitializeSettings_progress").addClass("fa fa-spinner fa-pulse");
                        saveFormToEndpoint(url="/api/stepca/initialize/set",formid='frm_InitializeSettings',callback_ok=function(){
                            ajaxCall(url="/api/stepca/service/initca", sendData={}, callback=function(data,status) {
                                if (status != "success" || data['status'] != 'success') {
                                    $("#frm_InitializeSettings_progress").removeClass("fa fa-spinner fa-pulse");
                                    updateServiceControlUI('stepca');
                                    stdDialogInform("{{ lang._('Error initializing StepCA') }}", 
                                        "{{ lang._('Error initializing StepCA. See Logs.') }}", 'OK');
                                } else {
                                    ajaxCall(url="/api/stepca/service/reconfigure", sendData={}, callback=function(data,status) {
                                        $("#frm_InitializeSettings_progress").removeClass("fa fa-spinner fa-pulse");
                                        updateServiceControlUI('stepca');
                                        if (status != "success" || data['status'] != 'ok') {
                                            stdDialogInform("{{ lang._('Error reconfiguring StepCA') }}", 
                                                "{{ lang._('Error reconfiguring StepCA. See Logs.') }}", 'OK');
                                        }
                                    });
                                }
                            });
                        });
                    });
                }
            );
        });

        // Hide form fields based on drop-down selections
        // - Hide all .key{I,R}
        // - Show one of sw{I,R}, ykl{I,R}, ykc{I,R}
        $(".selectSource").change(function() {
            var sourceR = $("#Initialize\\.root\\.Source").val();
            var sourceI = $("#Initialize\\.intermediate\\.Source").val();
            const rowSelector = "tr[id^=row_Initialize\\.]";

            if (sourceR == 'yubikeyC' && sourceI != 'yubikeyC') {
                sourceI = 'yubikeyC';
                $("#Initialize\\.intermediate\\.Source").val(sourceI);
            }

            $(".keyR, .keyI").parents(rowSelector).hide();
            if (sourceR == 'trust') {
                $('.swR').parents(rowSelector).show();
            } else if (sourceR == 'yubikeyL') {
                $('.yklR').parents(rowSelector).show();
            } else if (sourceR == 'yubikeyC') {
                $('.ykcR').parents(rowSelector).show();
            }
            if (sourceI == 'trust') {
                $('.swI').parents(rowSelector).show();
            } else if (sourceI == 'yubikeyL') {
                $('.yklI').parents(rowSelector).show();
            } else if (sourceI == 'yubikeyC') {
                $('.ykcI').parents(rowSelector).show();
            }
        });
    });
</script>

<div  class="col-md-12">
    {{ partial("layout_partials/base_form",['fields':initializeForm,'id':'frm_InitializeSettings','apply_btn_id':'reconfigureAct'])}}
</div>
{#
<div class="col-md-12">
    <table class="table table-striped table-condensed">
        <tr id="row_Initialize.help" >
            <td class="keyR ykcR ykcI" id="Initialize.help">
<strong>Example template:</strong>
                <pre>
{{ exampleTemplate }}
                </pre>
<strong>Full template:</strong>
                <pre>
{{ fullTemplate }}
                </pre>
            </td>
        </tr>
    </table>
</div>
#}
