{#
 # Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
 # Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
 #
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
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

<div class="content-box tab-content">
    <div id="select_monitor">
        <select aria-label="safe"
            data-size="10"
            id="monitor"
            class="selectpicker"
            data-container="body"
            data-hint="Select a UPS to view"
            data-none-selected-text="Select a UPS to view"
            data-width="346px"
            data-allownew="false"
            data-sortable="false"
            data-live-search="true"
        ></select>
    </div>
    <div id="diagnostics" class="tab-pane fade in active">
      <pre id="listdiagnostics"></pre>
    </div>
</div>

<script>
    function update_diagnostics() {

        const selection = $("#monitor").selectpicker("val");
        if (!selection || !selection.includes("_")) {
            return
        }
        const [kind, uuid] = selection.split("_");
        if (kind !== "local" && kind !== "remote") {
            return;
        }
        ajaxCall(
            `/api/nut/monitors/status_${kind}_monitor/${uuid}`,
            {},
            function(data, status) {
                if (status == "success") {
                    $("#listdiagnostics").text(data["response"]);
                } else {
                    $("#listdiagnostics").text(
                        "NUT is not returning a status the selected UPS."
                    );
                }
            }
        );
    }

    $(function() {
        $("#monitor").each(function() { $(this).empty(); });
        ajaxGet("/api/nut/monitors/search_local_monitor", {}, function(data, status) {
            if (status == "success") {
                let options = []
                for (const monitor of data.rows) {
                    if (monitor.enabled === "1") {
                        const name = monitor["%ups"];
                        const user = monitor["%user"];
                        options.push(
                            $("<option>")
                                .val(`local_${monitor["uuid"]}`)
                                .text(`${name} (${user}@localhost)`)
                        );
                    }
                }
                $("#monitor").append(
                    $("<optgroup/>")
                        .attr("label", "Local Monitors")
                        .append(options)
                );
                $("#monitor").selectpicker("refresh");
            }
        });
        ajaxGet("/api/nut/monitors/search_remote_monitor", {}, function(data, status) {
            if (status == "success") {
                let options = []
                for (const monitor of data.rows) {
                    if (monitor.enabled === "1") {
                        const name = monitor["ups_name"];
                        const user = monitor["username"];
                        const hostname = monitor["hostname"];
                        const port = monitor["port"];
                        options.push(
                            $("<option>")
                                .val(`remote_${monitor["uuid"]}`)
                                .text(`${name} (${user}@${hostname}:${port})`)
                        );
                    }
                }
                $("#monitor").append(
                    $("<optgroup/>")
                        .attr("label", "Remote Monitors")
                        .append(options)
                );
                $("#monitor").selectpicker("refresh");
            }
        });

        // call function update_diagnostics with a auto-refresh of 2 seconds
        setInterval(update_diagnostics, 2000);
    });

</script>
