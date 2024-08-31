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

<script type="text/javascript">
    $(document).ready(function() {
        // Initial setup
        mapDataToFormUI({'frm_general': "/api/caddy/general/get"}).done(function() {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('caddy');
        });

        // Show alert function
        function showAlert(message, type = "error") {
            const alertClass = type === "error" ? "alert-danger" : "alert-success";
            const messageArea = $("#messageArea");

            messageArea.stop(true, true).hide();
            messageArea.removeClass("alert-success alert-danger").addClass(alertClass).html(message);
            messageArea.fadeIn(500).delay(15000).fadeOut(500, function() {
                $(this).html('');
            });
        }

        // Spinner management function
        function setSpinner(generalId, action) {
            const $progressIcon = $("#" + generalId + "_progress");
            if (action === 'start') {
                $progressIcon.addClass("fa fa-spinner fa-pulse");
            } else if (action === 'stop') {
                $progressIcon.removeClass("fa fa-spinner fa-pulse");
            }
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

        // Reconfigure service function
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

        // Centralized error handling
        function handleError(type, message) {
            showAlert(message, type);
        }
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
