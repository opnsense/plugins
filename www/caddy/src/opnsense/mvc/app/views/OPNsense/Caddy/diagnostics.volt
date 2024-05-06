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
        $("#downloadCaddyConfig").click(function() {
            $.ajax({
                url: "/api/caddy/diagnostics/showconfig",  // Custom API endpoint that shows a validated json configuration
                type: "GET",
                success: function(response) {
                    if (response.status === "success") {
                        let timestamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').split('.')[0];
                        let filename = "caddy_autosave_" + timestamp + ".json";
                        download_content(JSON.stringify(response.content), filename, "application/json");
                    } else {
                        alert("Failed to download configuration: " + response.message);
                    }
                },
                error: function() {
                    alert("Error accessing the API.");
                }
            });
        });

        function download_content(payload, filename, file_type) {
            let a_tag = $('<a></a>').attr('href', 'data:' + file_type + ';charset=utf-8,' + encodeURIComponent(payload))
                                    .attr('download', filename)
                                    .appendTo('body');

            a_tag[0].click();
            a_tag.remove();
        }
    });
</script>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <h2>{{ lang._('Caddy configuration file') }}</h2>
            <ul>
                <li>{{ lang._('Downloads the current configuration file from:') }} <code>/var/db/caddy/config/caddy/autosave.json</code></li>
                <li>{{ lang._('The running configuration is adapted from:') }} <code>/usr/local/etc/caddy/Caddyfile</code></li>
                <li>{{ lang._('Custom imports are included.') }} <code>*.conf</code> {{ lang._('and') }} <code>*.global</code> {{ lang._('from:') }} <code>/usr/local/etc/caddy/caddy.d/</code></li>
            </ul>
            <hr/>
            <button class="btn btn-primary" id="downloadCaddyConfig" type="button">{{ lang._('Download') }}</button>
            <br/><br/>
        </div>
    </div>
</section>

