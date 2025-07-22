{#
# Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
# Copyright (C) 2025 squared GmbH
# Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
# Copyright (C) 2025 NetBird GmbH
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

<script>
    $(document).ready(() => {
        mapDataToFormUI({
            'frmSettings': "/api/netbird/settings/get"
        }).done(() => {
            updateServiceControlUI('netbird');
        });

        $("#sync").SimpleActionButton({
            onPreAction: () => {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/netbird/settings/set", 'frmSettings', () => {
                    dfObj.resolve();
                }, true, () => {
                    dfObj.reject();
                });
                return dfObj;
            },
            onAction: () => {
                updateServiceControlUI('netbird');
            }
        });
    });
</script>
<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':settingsForm,'id':'frmSettings']) }}
</div>
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="sync"
                    data-endpoint='/api/netbird/settings/sync'
                    data-label="{{ lang._('Save') }}"
                    data-error-title="{{ lang._('Error syncing NetBird settings') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>