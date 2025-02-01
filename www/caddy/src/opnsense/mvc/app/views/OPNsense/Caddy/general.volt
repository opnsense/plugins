{#
 # Copyright (c) 2023-2024 Cedrik Pischem
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
                    showAlert("{{ lang._('Configuration applied successfully.') }}", "success");
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

        // Event binding for saving forms
        $('[id*="save_general-"]').on('click', function() {
            const generalId = this.form.id;
            setSpinner(generalId, 'start');
            saveFormToEndpoint("/api/caddy/general/set", generalId, function() {
                // Validate Caddyfile after saving the form
                ajaxGet("/api/caddy/service/validate", {}, function(data, status) {
                    if (status === "success" && data && data.status.toLowerCase() === 'ok') {
                        reconfigureService(generalId);
                    } else {
                        handleError("error", data.message || "{{ lang._('Validation Error') }}");
                        setSpinner(generalId, 'stop');
                    }
                }).fail(function() {
                    handleError("error", "{{ lang._('Validation request failed.') }}");
                    setSpinner(generalId, 'stop');
                });
            }, true, function(errorData) {
                handleError("error", errorData.message || "{{ lang._('Validation Error') }}");
                setSpinner(generalId, 'stop');
            });
        });

        // Add buttons that redirect users to the correct model relation fields for better UX
        const addButton = $(`
            <button data-action="add" type="button" class="btn btn-xs btn-secondary" style="margin-top: 5px;" data-toggle="tooltip" title="{{ lang._('Create Item') }}">
                <span class="fa fa-plus"></span>
            </button>
        `);

        function appendButton(selectors, idPrefix) {
            $(selectors).each(function(index) {
                $(this).after(addButton.clone().attr("id", idPrefix + index));
            });
        }

        appendButton("#select_caddy\\.general\\.accesslist", "btnAddAccessList");
        appendButton("#select_caddy\\.general\\.ClientIpHeaders, #select_caddy\\.general\\.AuthCopyHeaders", "btnAddHeader");

        $(document).on("click", "[id^='btnAddAccessList']", function(e) {
            e.preventDefault();
            window.location.href = "/ui/caddy/reverse_proxy#btnAddAccessList";
        });

        $(document).on("click", "[id^='btnAddHeader']", function(e) {
            e.preventDefault();
            window.location.href = "/ui/caddy/reverse_proxy#btnAddHeader";
        });

        $('[data-toggle="tooltip"]').tooltip();

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
