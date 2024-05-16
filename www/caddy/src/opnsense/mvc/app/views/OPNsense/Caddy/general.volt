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
        let data_get_map = {'frm_GeneralSettings':"/api/caddy/General/get"};
        mapDataToFormUI(data_get_map).done(function(data){

            // Refresh selectpicker for these dropdowns
            $('.selectpicker').selectpicker('refresh');

            // Function to show alerts in the HTML message area
            function showAlert(message, type = "error") {
                let alertClass = type === "error" ? "alert-danger" : "alert-success";
                let messageArea = $("#messageArea");

                // Stop any current animation, clear the queue, and immediately hide the element
                messageArea.stop(true, true).hide();

                // Now set the class and message
                messageArea.removeClass("alert-success alert-danger").addClass(alertClass).html(message);

                // Use fadeIn to make the message appear smoothly, then fadeOut after a delay
                messageArea.fadeIn(500).delay(15000).fadeOut(500, function() {
                    // Clear the message after fading out to ensure it's clean for the next message
                    $(this).html('');
                });
            }

            // Hide message area when starting new actions
            $('input, select, textarea').on('change', function() {
                $("#messageArea").hide();
            });

            // Reconfigure the Caddy service, additional form save and validation with a validation API is made beforehand
            $("#reconfigureAct").SimpleActionButton({
                onPreAction: function() {
                    const dfObj = $.Deferred();

                    // Save the form before continuing
                    saveFormToEndpoint("/api/caddy/general/set", 'frm_GeneralSettings',
                        function() {  // callback_ok: What to do when save is successful
                            // After successful save, proceed with validation
                            $.ajax({
                                url: "/api/caddy/service/validate",
                                type: "GET",
                                dataType: "json",
                                success: function(data) {
                                    if (data && data['status'].toLowerCase() === 'ok') {
                                        dfObj.resolve(); // Configuration is valid
                                    } else {
                                        showAlert(data['message'], "{{ lang._('Validation Error') }}");
                                        dfObj.reject(); // Configuration is invalid
                                    }
                                },
                                error: function(xhr, status, error) {
                                    showAlert("{{ lang._('Validation request failed: ') }}" + error, "{{ lang._('Validation Error') }}");
                                    dfObj.reject(); // AJAX request failed
                                }
                            });
                        },
                        false, // disable_dialog: Show the dialog with the validation error
                        function(errorData) {  // callback_fail: What to do when save fails
                            // Handle failure due to validation errors or other issues
                            showAlert("{{ lang._('Configuration save failed: ') }}" + (errorData.message || "{{ lang._('Validation Error') }}"), "{{ lang._('Error') }}");
                            dfObj.reject(); // Reject the deferred object to stop the reconfigure action
                        }
                    );

                    return dfObj.promise();
                },
                onAction: function(data, status) {
                    if (status === "success" && data && data['status'].toLowerCase() === 'ok') {
                        // Configuration is valid and applied, possibly refresh UI or notify user
                        showAlert("{{ lang._('Configuration applied successfully.') }}", "{{ lang._('Apply Success') }}");
                        updateServiceControlUI('caddy');
                    } else {
                        // Handle errors or unsuccessful application
                        showAlert("{{ lang._('An error occurred while applying the configuration.') }}", "{{ lang._('Error') }}");
                    }
                }
            });

            $("#saveSettings").SimpleActionButton({
                onAction: function() {
                    const dfObj = $.Deferred();

                    // Save the form before continuing
                    saveFormToEndpoint("/api/caddy/general/set", 'frm_GeneralSettings',
                        function() {  // callback_ok: What to do when save is successful
                            showAlert("{{ lang._('Configuration saved successfully. Please do not forget to apply the configuration.') }}", "{{ lang._('Save Success') }}");
                            dfObj.resolve();
                        },
                        false, // disable_dialog: Show the dialog with the validation error
                        function(errorData) {  // callback_fail: What to do when save fails
                            showAlert("{{ lang._('Configuration save failed: ') }}" + (errorData.message || "{{ lang._('Validation Error') }}"), "{{ lang._('Error') }}");
                            dfObj.reject();
                        }
                    );

                    return dfObj.promise();
                },
            });

            // Initialize the service control UI for 'caddy'
            updateServiceControlUI('caddy');

        });
    });
</script>

<!-- Tab Navigation -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#generalTab">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#dnsProviderTab">{{ lang._('DNS Provider') }}</a></li>
    <li><a data-toggle="tab" href="#dynamicDnsTab">{{ lang._('Dynamic DNS') }}</a></li>
    <li><a data-toggle="tab" href="#logSettingsTab">{{ lang._('Log Settings') }}</a></li>
</ul>

<!-- Tab Content -->
<div class="tab-content content-box">
    <!-- General Tab -->
    <div id="generalTab" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form", ['fields': generalForm, 'action': '/ui/caddy/general', 'id': 'frm_GeneralSettings']) }}
    </div>
    <!-- DNS Provider Tab -->
    <div id="dnsProviderTab" class="tab-pane fade">
        {{ partial("layout_partials/base_form", ['fields': dnsproviderForm, 'action': '/ui/caddy/general', 'id': 'frm_GeneralSettings']) }}
    </div>
    <!-- Dynamic DNS Tab -->
    <div id="dynamicDnsTab" class="tab-pane fade">
        {{ partial("layout_partials/base_form", ['fields': dynamicdnsForm, 'action': '/ui/caddy/general', 'id': 'frm_GeneralSettings']) }}
    </div>
    <!-- Log Settings Tab -->
    <div id="logSettingsTab" class="tab-pane fade">
        {{ partial("layout_partials/base_form", ['fields': logsettingsForm, 'action': '/ui/caddy/general', 'id': 'frm_GeneralSettings']) }}
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <!-- Reconfigure Button with Pre-Action -->
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint="/api/caddy/service/reconfigure"
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring Caddy') }}"
                    type="button"
            ></button>
            <button class="btn btn-primary" id="saveSettings"
                    data-endpoint="/api/caddy/general/set"
                    data-label="{{ lang._('Save') }}"
                    type="button"
                    style="margin-left: 2px;"
            >{{ lang._('Save') }}</button>
            <br/><br/>
            <!-- Message Area for error/success messages -->
        <div id="messageArea" class="alert alert-info" style="display: none;"></div>
        </div>
    </div>
</section>
