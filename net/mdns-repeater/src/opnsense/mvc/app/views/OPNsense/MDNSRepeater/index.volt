{#
 # Copyright © 2017 Fabian Franz
 # Copyright (c) 2024 Cedrik Pischem
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
$(document).ready(function() {
    mapDataToFormUI({'frm_GeneralSettings': "/api/mdnsrepeater/settings/get"}).done(function() {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('mdnsrepeater');
    });

    $("#reconfigureAct").SimpleActionButton({
        onPreAction: function() {
            const dfObj = $.Deferred();
            saveFormToEndpoint("/api/mdnsrepeater/settings/set", 'frm_GeneralSettings', dfObj.resolve, true, dfObj.reject);
            return dfObj;
        },
        onAction: function(data, status) {
            if (status === "success" && data.status === 'ok') {
                ajaxCall("/api/mdnsrepeater/service/reconfigure", {}, function(reconfigData, reconfigStatus) {
                    if (reconfigStatus === "success" && reconfigData.status === 'ok') {
                        updateServiceControlUI('mdnsrepeater');
                    }
                });
            }
        }
    });

    // Initialize service control UI
    updateServiceControlUI('mdnsrepeater');
});
</script>

<section class="page-content-main">
    <div class="content-box">
        {{ partial("layout_partials/base_form", ['fields': general, 'id': 'frm_GeneralSettings']) }}
    </div>
    <br/>
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint="/api/mdnsrepeater/service/reconfigure"
                    data-label="{{ lang._('Apply') }}"
                    type="button">
                {{ lang._('Apply') }}
            </button>
            <br/><br/>
        </div>
    </div>
</section>
