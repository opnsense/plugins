{#
 # Copyright (C) 2017 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 #  1. Redistributions of source code must retain the above copyright notice,
 #   this list of conditions and the following disclaimer.
 #
 #  2. Redistributions in binary form must reproduce the above copyright
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
    window.redis_installed = {{ redis_installed ? 'true' : 'false' }};
    $( document ).ready(function() {

        var data_get_map = {'frm_rspamd':'/api/rspamd/settings/get'};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // request service status on load and update status box
            ajaxCall(url="/api/rspamd/service/status", sendData={}, callback=function(data,status) {
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
        $('#rspamd\\.general\\.enable_redis_plugin').change(function (evt) {
            $('#missing_redis_plugin').hide();
            if (!window.redis_installed && $(this).is(':checked')) {
                $('#missing_redis_plugin').show();
            }
        });



        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function() {
                var frm_id = $(this).closest("form").attr("id");
                var frm_title = $(this).closest("form").attr("data-title");
                // save data for General TAB
                saveFormToEndpoint(url="/api/rspamd/settings/set", formid=frm_id, callback_ok=function(){
                    // on correct save, perform reconfigure. set progress animation when reloading
                    $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                    ajaxCall(url="/api/rspamd/service/reconfigure", sendData={}, callback=function(data,status){
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
                            ajaxCall(url="/api/rspamd/service/status", sendData={}, callback=function(data,status) {
                                updateServiceStatusUI(data['status']);
                            });
                        }
                    });
                });
            });
        });

    });


</script>

{% if !clamav_installed %}
<div class="alert alert-warning" role="alert" id="missing_clamav" style="min-height:65px;">
    <div style="margin-top: 8px;">{{ lang._('No ClamAV plugin found, please install via %sSystem > Firmware > Plugins%s. If the plugin is not installed and enabled, mails cannot be scanned for malware.')|format('<a href="/ui/core/firmware/#plugins">','</a>')}}</div>
</div>
{% endif %}
<div class="alert alert-danger" role="alert" id="missing_redis_plugin" style="min-height:65px;{% if !redis_plugin_enabled or redis_installed %} display:none;{% endif %}">
    <div style="margin-top: 8px;">{{ lang._('The Redis plugin is configured to use but it is not installed. Please install it via %sSystem > Firmware > Plugins%s.')|format('<a href="/ui/core/firmware/#plugins">','</a>')}}</div>
</div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settings]) }}
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':settings]) }}
</div>
