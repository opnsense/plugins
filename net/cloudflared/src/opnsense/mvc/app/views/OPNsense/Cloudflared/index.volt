{#
 # Copyright (C) 2026 Richard Aspden <rick+github@insanityinside.net>
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

<script>
$(document).ready(function() {
    function updateSysctlWarning() {
        var protocol = $("#cloudflared\\.general\\.protocol").val();
        if (protocol !== 'auto' && protocol !== 'quic') {
            $("#sysctl_warning").hide();
            return;
        }
        ajaxCall("/api/cloudflared/settings/sysctlCheck", {}, function(data) {
            var issues = [];
            $.each({'kern.ipc.maxsockbuf': 16777216, 'net.inet.udp.recvspace': 8388608}, function(key, min) {
                if (data[key] && !data[key].ok) {
                    issues.push(key + " {{ lang._('(current:') }} " + data[key].value +
                        "{{ lang._(',  recommended: ≥') }} " + min + ")");
                }
            });
            if (issues.length > 0) {
                $("#sysctl_issues").html(issues.join("<br>"));
                $("#sysctl_warning").show();
            } else {
                $("#sysctl_warning").hide();
            }
        });
    }

    mapDataToFormUI({'frm_GeneralSettings': "/api/cloudflared/settings/get"}).done(function() {
        $('.selectpicker').selectpicker('refresh');
        updateServiceControlUI('cloudflared');
        updateSysctlWarning();
    });

    $("#cloudflared\\.general\\.protocol").on('change', function() {
        updateSysctlWarning();
    });

    $("#reconfigureAct").SimpleActionButton({
        onPreAction: function() {
            const dfObj = $.Deferred();
            saveFormToEndpoint("/api/cloudflared/settings/set", 'frm_GeneralSettings', dfObj.resolve, true, dfObj.reject);
            return dfObj;
        },
    });
});
</script>

<div class="content-box">
    {{ partial('layout_partials/base_form', ['fields': general, 'id': 'frm_GeneralSettings']) }}
    <div id="sysctl_warning" class="alert alert-warning" role="alert" style="margin: 10px; display: none;">
        {{ lang._("QUIC performance: the following UDP buffer sysctl(s) are below the recommended values. Set them under") }}
        <a href="/ui/core/tunables">{{ lang._("System > Settings > Tuneables") }}</a>
        {{ lang._("for optimal tunnel throughput.") }}
        <br><span id="sysctl_issues"></span>
    </div>
    <div class="alert alert-warning" role="alert" style="margin: 10px;">
        {{ lang._("Traffic received via the Cloudflare Tunnel bypasses OPNsense firewall rules. Access control for tunnelled services must be enforced within Cloudflare Access. Backend services must also be reachable from the router's own IP address, as cloudflared forwards connections from the router itself.") }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/cloudflared/service/reconfigure', 'data_service_widget': 'cloudflared'}) }}
