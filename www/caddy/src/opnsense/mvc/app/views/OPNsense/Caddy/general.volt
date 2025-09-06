{#
 # Copyright (c) 2023-2025 Cedrik Pischem
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
        // Initial setup
        mapDataToFormUI({'frm_general': "/api/caddy/general/get"}).done(function() {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('caddy');
        });

        /**
         * Displays an alert message to the user.
         *
         * @param {string} message - The message to display.
         * @param {string} [type="error"] - The type of alert (error or success).
         */
        function showAlert(message, type = "error") {
            const alertClass = type === "error" ? "alert-danger" : "alert-success";
            const messageArea = $("#messageArea");
            messageArea.stop(true, true).hide();
            messageArea.removeClass("alert-success alert-danger").addClass(alertClass).html(message);
            messageArea.fadeIn(500).delay(15000).fadeOut(500, function() {
                $(this).html('');
            });
        }

        /**
         * Centralizes error handling.
         * @param {string} type - The type of error.
         * @param {string} message - The error message.
         */
        function handleError(type, message) {
            showAlert(message, type);
        }

        // Event binding for saving forms
        $('[id^="save_general-"]').each(function () {
            const $btn = $(this);
            const formId = this.id.replace(/^save_/, 'frm_');

            $btn.attr({
                'data-label'    : "{{ lang._('Apply') }}",
                'data-endpoint' : "/api/caddy/service/reconfigure",
                'data-service-widget' : "caddy"
            });

            $btn.SimpleActionButton({
                onPreAction: function () {
                    const dfObj = new $.Deferred();

                    saveFormToEndpoint(
                        "/api/caddy/general/set",
                        formId,
                        function () {
                            ajaxGet("/api/caddy/service/validate", null, function (data, status) {
                                if (status === "success" && data && data['status'].toLowerCase() === 'ok') {
                                    dfObj.resolve();
                                } else {
                                    showAlert(data?.message || "{{ lang._('Validation Error') }}", "error");
                                    dfObj.reject();
                                }
                            }).fail(function(xhr, status, error) {
                                showAlert("{{ lang._('Validation request failed: ') }}" + error, "error");
                                dfObj.reject();
                            });
                        },
                        true,
                        function (errorData) {
                            showAlert(errorData.message || "{{ lang._('Validation Error') }}", "error");
                            dfObj.reject();
                        }
                    );

                    return dfObj.promise();
                }
            });
        });

    });
</script>

<!-- General Tab -->
<ul id="generalTabsHeader" class="nav nav-tabs" role="tablist">
    {{ partial("layout_partials/base_tabs_header", ['formData': generalForm]) }}
</ul>

<div id="generalTabsContent" class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content", ['formData': generalForm]) }}
    <!-- Message Area for error/success messages -->
    <div style="max-width: 98%; margin: 10px auto;">
        <div id="messageArea" class="alert alert-info" style="display: none;"></div>
    </div>
</div>
