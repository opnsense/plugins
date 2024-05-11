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
        // Fetch and display Caddyfile and JSON configuration
        fetchAndDisplay('/api/caddy/diagnostics/caddyfile', '#caddyfileDisplay', false);
        fetchAndDisplay('/api/caddy/diagnostics/config', '#jsonDisplay', true);

        /**
         * Fetches data from the specified URL and displays it within a given element on the page.
         * The Caddyfile is raw content and will be displayed directly.
         * The JSON configuration is a double encoded JSON which will be stringified and nicely formatted.
         *
         * @param {string} url - The URL from which to fetch data.
         * @param {string} displaySelector - jQuery selector for the element where data should be displayed.
         * @param {boolean} isJson - Flag indicating whether the response should be treated as JSON that needs parsing.
         */
        function fetchAndDisplay(url, displaySelector, isJson) {
            $.ajax({
                url: url,
                type: "GET",
                success: function(response) {
                    if (response.status === "success") {
                        if (isJson) {
                            try {
                                // Assuming response.content is a JSON object containing a stringified JSON in 'config_data'
                                let parsedContent = JSON.parse(response.content.config_data); // Parse the stringified JSON
                                let formattedContent = JSON.stringify(parsedContent, null, 2); // Format it nicely
                                $(displaySelector).text(formattedContent);
                            } catch (error) {
                                // If JSON parsing fails, display an error message
                                $(displaySelector).text("JSON parsing error: " + error.message);
                            }
                        } else {
                            // If the data is not JSON, directly display the raw content
                            $(displaySelector).text(response.content);
                        }
                    } else {
                        // If the response status is not 'success', handle it by showing an appropriate message
                        $(displaySelector).text("Failed to load content: " + response.message || "Unknown error");
                    }
                },
                error: function(xhr, status, error) {
                    // Handle errors from the AJAX request itself
                    $(displaySelector).text("AJAX error accessing the API: " + error);
                }
            });
        }

        $("#downloadJSONConfig").click(function() {
            let content = $("#jsonDisplay").text();
            let timestamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').split('.')[0];
            let filename = "caddy_config_" + timestamp + ".json";
            downloadContent(content, filename, "application/json");
        });

        $("#downloadCaddyfile").click(function() {
            let content = $("#caddyfileDisplay").text();
            let timestamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').split('.')[0];
            let filename = "Caddyfile_" + timestamp;
            downloadContent(content, filename, "text/plain");
        });

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

        // Event handler for the Validate Caddyfile button
        $('#validateCaddyfile').click(function() {
            $.ajax({
                url: '/api/caddy/service/validate',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data && data['status'].toLowerCase() === 'ok') {
                        alert('Validation successful: ' + data['message']);  // Show success message
                    } else {
                        alert('Validation error: ' + data['message']);  // Show error message from the API
                    }
                },
                error: function(xhr, status, error) {
                    alert('Validation request failed: ' + error);  // Show AJAX error
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
    <li class="active"><a data-toggle="tab" href="#caddyfileTab">Caddyfile</a></li>
    <li><a data-toggle="tab" href="#jsonConfigTab">JSON Configuration</a></li>
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
