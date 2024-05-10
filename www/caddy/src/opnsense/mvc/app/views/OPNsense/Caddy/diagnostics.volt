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
    });
</script>

<style>
    .custom-style .content-box {
        padding: 20px; /* Adds padding around the contents of each tab */
    }

    .custom-style .display-area {
        height: 800px;
        overflow-y: scroll;
        background-color: #f8f9fa;
        margin-bottom: 25px; /* Adds bottom margin to separate from the button */
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
            <button class="btn btn-primary download-btn" id="downloadCaddyfile" type="button">Download</button>
            <br/><br/>
        </div>
    </div>
    <!-- JSON Configuration Tab -->
    <div id="jsonConfigTab" class="tab-pane fade">
        <div class="content-box">
            <pre id="jsonDisplay" class="display-area"></pre>
            <button class="btn btn-primary download-btn" id="downloadJSONConfig" type="button">Download</button>
            <br/><br/>
        </div>
    </div>
</div>
