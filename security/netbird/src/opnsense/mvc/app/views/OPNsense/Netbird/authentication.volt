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
    function updateNetBirdStatusUI() {
        ajaxGet('/api/netbird/status/status', {}, (data) => {
            const $connectBtn = $("#connectBtn");
            const $disconnectBtn = $("#disconnectBtn");

            $("#netbird-actions").removeClass("hidden");

            const isConnected = data.management?.connected === true;
            const message = isConnected ? "NetBird is connected" : "NetBird is not connected";
            const type = isConnected ? "info" : "warning";

            $connectBtn.toggleClass("hidden", isConnected);
            $disconnectBtn.toggleClass("hidden", !isConnected);

            $("#status").removeClass().addClass("alert alert-" + type).text(message).show();
        });
    }

    $(document).ready(() => {
        mapDataToFormUI({
            'frmAuthentication': "/api/netbird/authentication/get"
        }).done(() => {
            updateServiceControlUI('netbird');
            updateNetBirdStatusUI();
        });

        $("#connectBtn").SimpleActionButton({
            onPreAction: () => {
                const dfObj = new $.Deferred();
                const setupKey = $("#authentication\\.setupKey").val();

                if (setupKey.includes("*")) {
                    dfObj.resolve();
                } else {
                    saveFormToEndpoint("/api/netbird/authentication/set", 'frmAuthentication', () => {
                        dfObj.resolve();
                    }, true, () => {
                        dfObj.reject();
                    });
                }
                return dfObj;
            },
            onAction: () => {
                updateServiceControlUI('netbird');
                updateNetBirdStatusUI();
            }
        });

        $("#disconnectBtn").SimpleActionButton({
            onAction: () => {
                updateServiceControlUI('netbird');
                updateNetBirdStatusUI();
            }
        });
    });
</script>

<div class="alert hidden" role="alert" id="status"></div>
<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':authenticationForm,'id':'frmAuthentication']) }}
</div>
<section class="page-content-main hidden" id="netbird-actions">
    <div class="content-box">
        <div class="col-md-12">
            </br>
            <button class="btn btn-primary hidden" id="connectBtn"
                data-endpoint="/api/netbird/authentication/up"
                data-label="{{ lang._('Connect') }}"
                data-error-title="{{ lang._('Error connecting NetBird') }}"
                type="button"
            ></button>
            <button class="btn btn-primary hidden" id="disconnectBtn"
                data-endpoint="/api/netbird/authentication/down"
                data-label="{{ lang._('Disconnect') }}"
                data-error-title="{{ lang._('Error disconnecting NetBird') }}"
                type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>
