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
         * Info messages persists, all other messages fade away.
         *
         * @param {string} message - The message to display.
         * @param {string} [type="error"] - The type of alert (error or success).
         */
        function showAlert(message, type = "error") {
            const alertClass = type === "error" ? "alert-danger" : "alert-success";
            const messageArea = $("#messageArea");
            messageArea.stop(true, true).hide();
            messageArea.removeClass("alert-success alert-danger").addClass(alertClass).html(message).fadeIn(500);

            if (type !== "info") {
                messageArea.delay(30000).fadeOut(500, function () {
                    $(this).html('');
                });
            }
        }

        /**
         * Manages the spinner icon.
         * @param {string} generalId - The ID of the general form.
         * @param {string} action - The action to perform ("start" or "stop").
         */
        function setSpinner(generalId, action) {
            const $progressIcon = $("#" + generalId + "_progress");
            if (action === 'start') {
                $progressIcon.addClass("fa fa-spinner fa-pulse");
            } else if (action === 'stop') {
                $progressIcon.removeClass("fa fa-spinner fa-pulse");
            }
        }

        /**
         * Reconfigures the service.
         * @param {string} generalId - The ID of the general form (used for controlling the spinner).
         */
        function reconfigureService(generalId) {
            ajaxCall("/api/caddy/service/reconfigure", {}, function(data, status) {
                if (status !== "success" || data.status !== 'ok') {
                    showAlert("{{ lang._('Error applying configuration: ') }}" + JSON.stringify(data), "error");
                } else {
                    updateServiceControlUI('caddy');
                }
                setSpinner(generalId, 'stop');
            }).fail(function() {
                handleError("error", "{{ lang._('Reconfiguration request failed.') }}");
                setSpinner(generalId, 'stop');
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

        /**
         * Polls the backend continously for the current Caddy build status.
         * Displays a status message only when the status changes to avoid UI spam.
         * Automatically stops polling when the build completes (success or error).
         *
         * @param {string} formId - The ID of the form used to control the spinner state.
         */
         function pollCaddyBuildStatus(formId) {
            let lastStatus = null;

            // Localized status messages
            const localizedStatusMap = {
                "running": "{{ lang._('Build running. Please be patient, this might take a few minutes.') }}",
                "success": "{{ lang._('Build completed successfully. Services restarted if it was running.') }}",
                "error": "{{ lang._('Build failed. Check /var/log/caddy/caddy_build.log for more information.') }}"
            };

            const $applyButton = $(`#${formId}`).find('[id^="save_general-"]');
            $applyButton.prop('disabled', true);

            const interval = setInterval(() => {
                ajaxGet("/api/caddy/general/build_status", {}, function (data, status) {
                    if (status === "success" && data.status) {
                        if (data.status !== lastStatus) {
                            lastStatus = data.status;

                            const localizedStatus = localizedStatusMap[data.status] || "{{ lang._('Unknown status') }}";
                            const msg = "{{ lang._('Status') }}: " + localizedStatus;
                            const alertType = data.status === "success" ? "success" : (data.status === "error" ? "error" : "info");
                            showAlert(msg, alertType);
                        }

                        if (data.status === "success" || data.status === "error") {
                            clearInterval(interval);
                            setSpinner(formId, 'stop');
                            $applyButton.prop('disabled', false);
                        }
                    }
                });
            }, 10000);
        }

        // Event binding for saving forms
        $('[id^="save_general-"]').on('click', function () {
            const formId = this.form.id;
            const buttonId = this.id;

            // Trigger build only for save button with ID starting with 'save_general-modules'
            const triggerBuild = buttonId.startsWith("save_general-modules");

            setSpinner(formId, 'start');

            saveFormToEndpoint("/api/caddy/general/set", formId, function () {
                if (triggerBuild) {
                    ajaxCall("/api/caddy/general/build_binary", {}, function (data, status) {
                        if (status === "success") {
                            pollCaddyBuildStatus(formId);
                        } else {
                            showAlert("{{ lang._('Failed to start Caddy build.') }}", "error");
                            setSpinner(formId, 'stop');
                        }
                    }, "post");
                } else {
                    ajaxGet("/api/caddy/service/validate", {}, function (data, status) {
                        if (status === "success" && data && data.status.toLowerCase() === 'ok') {
                            reconfigureService(formId);
                        } else {
                            handleError("error", data.message || "{{ lang._('Validation Error') }}");
                            setSpinner(formId, 'stop');
                        }
                    }).fail(function () {
                        handleError("error", "{{ lang._('Validation request failed.') }}");
                        setSpinner(formId, 'stop');
                    });
                }
            }, true, function (errorData) {
                showAlert(errorData.message || "{{ lang._('Validation Error') }}", "error");
                setSpinner(formId, 'stop');
            });
        });

        // Rename "Apply" button to "Build" for the Modules tab
        $('#save_general-modules')
            .html('<b>{{ lang._("Build") }}</b> <i id="frm_general-modules_progress" class=""></i>');

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
