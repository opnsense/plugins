{#
 # Copyright (C) 2017-2018 Michael Muenz <m.muenz@gmail.com>
 # Copyright (C) 2014-2017 Deciso B.V.
 # Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
 #
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

        var data_get_map = {'frm_nut':'/api/nut/general/get'};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        updateServiceControlUI('nut');

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function() {
                var frm_id = $(this).closest("form").attr("id");
                var frm_title = $(this).closest("form").attr("data-title");
                // save data for General TAB
                saveFormToEndpoint(url="/api/nut/general/set", formid=frm_id, callback_ok=function(){
                    // on correct save, perform restart, set progress animation when reloading
                    $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/nut/service/reconfigure", sendData={}, callback=function(data,status){
                        // when done, disable progress animation.
                        $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");
                        updateServiceControlUI('nut');
                    });
                });
            });
        });

    });


</script>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':generalForm]) }}
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':generalForm]) }}
</div>
