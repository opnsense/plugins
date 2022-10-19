{#
 # Copyright (c) 2014-2018 Deciso B.V.
 # Copyright (c) 2022 agh1467 <agh1467@protonmail.com>
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

{#
 # This is the template for the settings page.
 #
 # This is the main page for this plugin.
 #
 # Variables sent in by the controller:
 # plugin_name  string  name of this plugin, used for API calls
 # this_form    array   the form XML in an array
 #}

<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':this_form,'id':'frm_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary"  id="saveAct" type="button">
                    <b>{{ lang._('Save and Apply') }}</b>
                    <i id="saveAct_progress"></i>
                </button>
            </div>
        </div>
    </div>
</div>


<script>

$( document ).ready(function() {
    var data_get_map = {'frm_settings':"/api/sslh/settings/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/{{ api_name }}/settings/set", formid='frm_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/{{ api_name }}/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('{{ plugin_name }}');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    updateServiceControlUI('{{ plugin_name }}');
});
</script>
