{#
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

<script type="text/javascript">
    $(document).ready(function() {

        /**
         * Fetches data from the specified URL and displays it within a given element on the page.
         * The response is expected to be JSON and will be formatted for display.
         *
         * @param {string} url - The URL from which to fetch data.
         * @param {string} displaySelector - jQuery selector for the element where data should be displayed.
         */
        function fetchAndDisplay(url, displaySelector) {
            $.ajax({
                url: url,
                type: "GET",
                success: function(response) {
                    if (response.status === "success") {
                        let formattedContent;
                        if (typeof response.content === 'object') {
                            // If the content is an object, stringify and format it
                            formattedContent = JSON.stringify(response.content, null, 2);
                        } else {
                            // If the content is plain text (as with the Caddyfile), just use it directly
                            formattedContent = response.content;
                        }
                        $(displaySelector).text(formattedContent);
                    } else {
                        // If the response status is not 'success', display an error message
                        $(displaySelector).text("{{ lang._('Failed to load content: ') }}" + response.message || "{{ lang._('Unknown error') }}");
                    }
                },
                error: function(xhr, status, error) {
                    // Handle errors from the AJAX request itself
                    $(displaySelector).text("{{ lang._('AJAX error accessing the API: ') }}" + error);
                }
            });
        }

        /**
         * Initiates a file download directly from the browser using JavaScript. The function dynamically creates
         * an anchor (<a>) element, sets its attributes for downloading and simulates a click to start the download.
         *
         * @param {string} payload - The content to be downloaded, which could be any data string (e.g., JSON text, plain text).
         * @param {string} filename - The name of the file that will be suggested to the user when downloading.
         * @param {string} file_type - The MIME type of the file, which helps the browser to handle the file appropriately.
         */
        function downloadContent(payload, filename, file_type) {
            // Create an anchor tag (<a>) dynamically using jQuery
            let a_tag = $('<a></a>')
                .attr('href', 'data:' + file_type + ';charset=utf-8,' + encodeURIComponent(payload))  // Set the href attribute to a data URL containing the payload
                .attr('download', filename)  // Set the download attribute to suggest a filename
                .appendTo('body');  // Append the anchor tag to the body of the document to make it part of the DOM

            a_tag[0].click();  // Programmatically click the anchor tag to trigger the download
            a_tag.remove();  // Remove the anchor tag
        }

        /**
         * Shows a BootstrapDialog alert with custom settings.
         *
         * @param {string} type - Type of the dialog based on BootstrapDialog types.
         * @param {string} title - Title of the dialog.
         * @param {string} message - Message to be displayed in the dialog.
         */
        function showDialogAlert(type, title, message) {
            BootstrapDialog.show({
                type: type,
                title: title,
                message: message,
                buttons: [{
                    label: '{{ lang._('Close') }}',
                    action: function(dialogRef) {
                        dialogRef.close();
                    }
                }]
            });
        }

        // Fetch and display Caddyfile and JSON configuration
        fetchAndDisplay('/api/caddy/diagnostics/caddyfile', '#caddyfileDisplay');
        fetchAndDisplay('/api/caddy/diagnostics/config', '#jsonDisplay');

        // Event handler to initiate JSON configuration download
        $("#downloadJSONConfig").click(function() {
            let content = $("#jsonDisplay").text();
            let timestamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').split('.')[0];
            let filename = "caddy_config_" + timestamp + ".json";
            downloadContent(content, filename, "application/json");
        });

        // Event handler to initiate Caddyfile download
        $("#downloadCaddyfile").click(function() {
            let content = $("#caddyfileDisplay").text();
            let timestamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').split('.')[0];
            let filename = "Caddyfile_" + timestamp;
            downloadContent(content, filename, "text/plain");
        });

        // Event handler for the Validate Caddyfile button
        $('#validateCaddyfile').click(function() {
            $.ajax({
                url: '/api/caddy/service/validate',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data && data['status'].toLowerCase() === 'ok') {
                        showDialogAlert(BootstrapDialog.TYPE_SUCCESS, "{{ lang._('Validation Successful') }}", data['message']);
                    } else {
                        showDialogAlert(BootstrapDialog.TYPE_WARNING, "{{ lang._('Validation Error') }}", data['message']);  // Show error message from the API
                    }
                },
                error: function(xhr, status, error) {
                    showDialogAlert(BootstrapDialog.TYPE_DANGER, "{{ lang._('Validation Request Failed') }}", error);  // Show AJAX error
                }
            });
        });

    });
</script>

<style>
    .custom-style .content-box {
        padding: 20px; /* Adds padding around the contents of each tab */
    }

    .custom-style .display-area {
        overflow-y: scroll;
        /* Dynamic height management using clamp for varying screen sizes */
        height: clamp(50px, 50vh, 4000px);
        margin-bottom: 20px; /* Adds bottom margin to separate from the help text */
    }

    .custom-style .help-text {
        margin-top: 10px;
        margin-bottom: 20px;
        line-height: 1.4;
        /* Adjusting text size for readability on various displays */
        font-size: clamp(12px, 1.5vw, 16px);
    }
</style>

<!-- Tab Navigation -->
<ul class="nav nav-tabs" data-tabs="tabs" id="configTabs">
    <li class="active"><a data-toggle="tab" href="#caddyfileTab">{{ lang._('Caddyfile') }}</a></li>
    <li><a data-toggle="tab" href="#jsonConfigTab">{{ lang._('JSON Configuration') }}</a></li>
</ul>

<!-- Tab Content -->
<div class="tab-content content-box custom-style">
    <!-- Caddyfile Tab -->
    <div id="caddyfileTab" class="tab-pane fade in active">
        <div class="content-box">
            <pre id="caddyfileDisplay" class="display-area"></pre>
            <p class="help-text">{{ lang._("This is the generated configuration located at %sCaddyfile%s. It's the main configuration file to get support with. The validation button triggers a manual check for any configuration errors, which is the same check that is triggered by the Apply buttons automatically.") | format('<code>/usr/local/etc/caddy/', '</code>') }}</p>
            <button class="btn btn-primary download-btn" id="downloadCaddyfile" type="button">{{ lang._('Download') }}</button>
            <button class="btn btn-secondary" id="validateCaddyfile" type="button">{{ lang._('Validate Caddyfile') }}</button>
            <br/><br/>
        </div>
    </div>
    <!-- JSON Configuration Tab -->
    <div id="jsonConfigTab" class="tab-pane fade">
        <div class="content-box">
            <pre id="jsonDisplay" class="display-area"></pre>
            <p class="help-text">{{ lang._("Shows the running Caddy configuration located in %sautosave.json%s. It is automatically adapted from the Caddyfile and also includes any custom imported configurations from %scaddy.d%s.") | format('<code>/var/db/caddy/config/caddy/', '</code>', '<code>/usr/local/etc/caddy/', '</code>') }}</p>
            <button class="btn btn-primary download-btn" id="downloadJSONConfig" type="button">{{ lang._('Download') }}</button>
            <br/><br/>
        </div>
    </div>
</div>
