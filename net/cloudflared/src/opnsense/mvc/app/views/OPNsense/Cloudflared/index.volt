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
    mapDataToFormUI({'frm_GeneralSettings': "/api/cloudflared/settings/get"}).done(function() {
        $('.selectpicker').selectpicker('refresh');
    });

    function updateTunnelStatus() {
        ajaxGet('/api/cloudflared/service/tunnel_status', {}, function(data) {
            let label = 'label-default';
            let text = "{{ lang._('Stopped') }}";
            if (data.status === 'connected') {
                label = 'label-success';
                text = "{{ lang._('Connected (%s edge connections)') }}".replace('%s', data.connections);
            } else if (data.status === 'disconnected') {
                label = 'label-warning';
                text = "{{ lang._('Not connected') }}";
            } else if (data.status !== 'stopped') {
                text = "{{ lang._('Unknown') }}";
            }
            $('#tunnel_status').empty().append($('<span class="label"/>').addClass(label).text(text));
        });
    }

    updateServiceControlUI('cloudflared');
    updateTunnelStatus();
    setInterval(updateTunnelStatus, 10000);

    $("#reconfigureAct").SimpleActionButton({
        onPreAction: function() {
            const dfObj = $.Deferred();
            saveFormToEndpoint("/api/cloudflared/settings/set", 'frm_GeneralSettings', dfObj.resolve, true, dfObj.reject);
            return dfObj;
        },
        onAction: function() {
            updateServiceControlUI('cloudflared');
            updateTunnelStatus();
        },
    });
});
</script>

<div class="content-box">
    <table class="table table-condensed">
        <tbody>
            <tr>
                <td style="width: 22%;"><b>{{ lang._('Tunnel Status') }}</b></td>
                <td id="tunnel_status"><i class="fa fa-spinner fa-spin"></i></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="content-box">
    {{ partial('layout_partials/base_form', ['fields': general, 'id': 'frm_GeneralSettings']) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/cloudflared/service/reconfigure', 'data_service_widget': 'cloudflared'}) }}
