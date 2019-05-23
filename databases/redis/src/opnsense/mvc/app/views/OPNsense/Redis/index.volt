{#
 # Copyright (C) 2017 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
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
    var data_get_map = {'frm_redis':'/api/redis/settings/get'};

    // load initial data
    mapDataToFormUI(data_get_map).done(function(){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        // request service status on load and update status box
        ajaxCall(url="/api/redis/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });
    });

    // update history on tab state and implement navigation
    if(window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });

    $('#save_redis-general-settings').after('<button class="btn btn-default" style="margin-left:5px" id="resetdbAct" type="button"><b>{{ lang._('Reset') }}</b> <i id="resetdbAct_progress" class=""></i></button>');

    // form save event handlers for all defined forms
    $('[id*="save_"]').each(function(){
        $(this).click(function() {
            var frm_id = $(this).closest("form").attr("id");
            var frm_title = $(this).closest("form").attr("data-title");
            // save data for General TAB
            saveFormToEndpoint(url="/api/redis/settings/set", formid=frm_id, callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                ajaxCall(url="/api/redis/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");

                    if (status != "success" || data['status'] != 'ok' ) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: frm_title,
                            message: JSON.stringify(data),
                            draggable: true
                        });
                    } else {
                        ajaxCall(url="/api/redis/service/status", sendData={}, callback=function(data,status) {
                            updateServiceStatusUI(data['status']);
                        });
                    }
                });
            });
        });
    });
    $("#resetdbAct").click(function () {
        stdDialogConfirm(
            '{{ lang._('Confirm database reset') }}',
            '{{ lang._('Do you want to reset the database?') }}',
            '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                $("#resetdbAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/redis/service/resetdb", sendData={}, callback=function(data,status) {
                    ajaxCall(url="/api/redis/service/reconfigure", sendData={}, callback=function(data,status) {
                    updateServiceControlUI('redis');
                    $("#resetdbAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });
});
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settings]) }}
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':settings]) }}
</div>
