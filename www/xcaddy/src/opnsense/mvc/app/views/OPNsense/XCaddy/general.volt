{#
 # Copyright (c) 2025 Cedrik Pischem
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
    $(document).ready(function () {
        // Load form data
        mapDataToFormUI({'frm_GeneralSettings': "/api/xcaddy/general/get"}).done(function () {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        function showAlert(message, type = "error") {
            // Normalize success to info
            if (type === "success") {
                type = "info";
            }

            const alertClass = type === "error" ? "alert-danger" : "alert-info";
            const closeButton = '<button type="button" class="close" onclick="$(\'#messageArea\').hide().html(\'\');">&times;</button>';
            const fullMessage = closeButton + message;

            $("#messageArea").stop(true, true).hide()
                .removeClass("alert-success alert-danger alert-info")
                .addClass("alert-dismissible fade in " + alertClass)
                .html(fullMessage)
                .fadeIn(300);
        }

        // Spinner control
        function setSpinner(action, target = "#frm_GeneralSettings_progress") {
            const $icon = $(target);
            if (action === 'start') {
                $icon.addClass("fa fa-spinner fa-pulse");
            } else {
                $icon.removeClass("fa fa-spinner fa-pulse");
            }
        }

        // Poll build status
        function pollCaddyBuildStatus() {
            let lastStatus = null;
            const statusMap = {
                "running": "{{ lang._('Build running. Please be patient, this might take a few minutes.') }}",
                "success": "{{ lang._('Build completed successfully. Caddy was restarted if it was running.') }}",
                "error": "{{ lang._('Build failed. Check /var/log/xcaddy for more information. Binary was not replaced.') }}"
            };

            const interval = setInterval(() => {
                ajaxGet("/api/xcaddy/general/build_status", {}, function (data, status) {
                    if (status === "success" && data.status) {
                        if (data.status !== lastStatus) {
                            lastStatus = data.status;
                            let msg = "{{ lang._('Status') }}: " + (statusMap[data.status] || "{{ lang._('Unknown status') }}");
                            if (data.ts) {
                                msg += "<br><small>" + data.ts + "</small>";
                            }
                            const type = data.status === "success" ? "success" : (data.status === "error" ? "error" : "info");
                            showAlert(msg, type);
                        }

                        if (data.status === "success" || data.status === "error") {
                            clearInterval(interval);
                            setSpinner('stop');
                        }
                    }
                });
            }, 5000);
        }

        // Build button click
        $("#buildCaddyBinary").click(function () {
            setSpinner('start');

            saveFormToEndpoint("/api/xcaddy/general/set", 'frm_GeneralSettings', function () {
                ajaxCall("/api/xcaddy/general/build_binary", {}, function (data, status) {
                    if (status === "success") {
                        showAlert("{{ lang._('Requesting a new Caddy build.') }}", "info");
                        pollCaddyBuildStatus();
                    } else {
                        showAlert("{{ lang._('Failed to start Caddy build.') }}", "error");
                        setSpinner('stop');
                    }
                }, "post");
            }, true, function (errorData) {
                showAlert(errorData.message || "{{ lang._('Validation Error') }}", "error");
                setSpinner('stop');
            });
        });

        // Update Modules button click
        $("#updateCaddyModules").click(function () {
            setSpinner('start', '#updateModules_progress');

            ajaxCall("/api/xcaddy/general/update_modules", {}, function (data, status) {
                setSpinner('stop', '#updateModules_progress');

                if (status === "success" && data.status === "success") {
                    const count = data.count || 0;
                    showAlert("{{ lang._('Module list updated successfully.') }} ({{ lang._('Modules') }}: " + count + ")", "success");
                    // Refresh form and selectpicker after update
                    mapDataToFormUI({'frm_GeneralSettings': "/api/xcaddy/general/get"}).done(function () {
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                    });
                } else {
                    const message = data.message || "{{ lang._('Failed to update module list.') }}";
                    showAlert("{{ lang._('Error') }}: " + message, "error");
                }
            }, "post");
        });

        // Resume build polling on page load if a build is already running
        ajaxGet("/api/xcaddy/general/build_status", {}, function (data, status) {
            if (status === "success" && data.status === "running") {
                showAlert("{{ lang._('Build already running. Fetching status.') }}", "info");
                setSpinner('start');
                pollCaddyBuildStatus();
            }
        });

    });
</script>

<style>
    .button-spaced {
        margin-right: 10px;
    }
</style>

<div class="content-box">
    {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_GeneralSettings']) }}
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary button-spaced" id="buildCaddyBinary" type="button">
                <b>{{ lang._('Build') }}</b> <i id="frm_GeneralSettings_progress"></i>
            </button>
            <button class="btn btn-secondary" id="updateCaddyModules" type="button">
                <b>{{ lang._('Update Modules') }}</b> <i id="updateModules_progress"></i>
            </button>
            <br/><br/>
            <div id="messageArea" class="alert alert-info alert-dismissible fade in" style="display: none;">
                <button type="button" class="close" onclick="$('#messageArea').hide();">&times;</button>
            </div>
        </div>
    </div>
</section>
