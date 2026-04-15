{#
 # Copyright (C) 2026 Alan Martines <alancpmartines@hotmail.com>
 # All rights reserved.
 #}

<script>
    $(document).ready(function() {
        var data_get_map = {'frm_general': "/api/cloudflared/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data) {
            $('.selectpicker').selectpicker('refresh');

            // Adiciona botão de mostrar/ocultar ao campo token
            var tokenInput = $("#Cloudflared\\.general\\.token");
            tokenInput.wrap('<div class="input-group"></div>');
            tokenInput.after(
                '<span class="input-group-btn">' +
                '<button class="btn btn-default" type="button" id="toggleTokenBtn" title="{{ lang._('Show/Hide') }}">' +
                '<i class="fa fa-eye" id="toggleTokenIcon"></i>' +
                '</button>' +
                '</span>'
            );
            $("#toggleTokenBtn").click(function() {
                var inp = $("#Cloudflared\\.general\\.token");
                var icon = $("#toggleTokenIcon");
                if (inp.attr("type") === "password") {
                    inp.attr("type", "text");
                    icon.removeClass("fa-eye").addClass("fa-eye-slash");
                } else {
                    inp.attr("type", "password");
                    icon.removeClass("fa-eye-slash").addClass("fa-eye");
                }
            });
        });

        var i18n = {
            running: "{{ lang._('Running') }}",
            stopped: "{{ lang._('Stopped') }}",
            healthy: "{{ lang._('Healthy') }}",
            connecting: "{{ lang._('Connecting') }}",
            tunnel_status: "{{ lang._('Tunnel') }}"
        };

        // Salvar e reconfigura o serviço
        $("#saveAct").click(function() {
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            saveFormToEndpoint("/api/cloudflared/settings/set", 'frm_general', function() {
                ajaxCall("/api/cloudflared/service/reconfigure", {}, function() {
                    updateServiceStatus(i18n);
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse").addClass("fa fa-check text-success");
                    setTimeout(function() {
                        $("#saveAct_progress").removeClass("fa fa-check text-success");
                    }, 2000);
                });
            });
        });

        // Instalar / atualizar binário
        $("#installBtn").click(function() {
            $("#installBtn").prop('disabled', true);
            $("#installIcon").addClass('fa-spinner fa-spin').removeClass('fa-download');
            ajaxCall("/api/cloudflared/service/install", {}, function(data, status) {
                $("#installBtn").prop('disabled', false);
                $("#installIcon").addClass('fa-download').removeClass('fa-spinner fa-spin');
                var output = (data && data.response) ? data.response.trim() : '';
                var dlgType = (output.indexOf("successful") !== -1)
                    ? BootstrapDialog.TYPE_SUCCESS
                    : BootstrapDialog.TYPE_DANGER;
                BootstrapDialog.show({
                    type: dlgType,
                    title: "{{ lang._('Install/Update Binary') }}",
                    message: $('<pre/>').text(output || "{{ lang._('No response from server. Check if configd is running.') }}"),
                    buttons: [{ label: 'OK', action: function(d) { d.close(); } }]
                });
            });
        });

        // Controle de serviço
        $("#startBtn").click(function() {
            ajaxCall("/api/cloudflared/service/start", {}, function() { updateServiceStatus(i18n); });
        });
        $("#stopBtn").click(function() {
            ajaxCall("/api/cloudflared/service/stop", {}, function() { updateServiceStatus(i18n); });
        });
        $("#restartBtn").click(function() {
            ajaxCall("/api/cloudflared/service/restart", {}, function() { updateServiceStatus(i18n); });
        });

        updateServiceStatus(i18n);
    });

    function updateServiceStatus(i18n) {
        ajaxCall("/api/cloudflared/service/status", {}, function(data) {
            var running = data && data.status === "running";
            if (running) {
                $("#svc_status").html(
                    '<span class="label label-success">'
                    + '<i class="fa fa-play fa-fw"></i> ' + i18n.running
                    + '</span>'
                );
                $("#startBtn").hide();
                $("#stopBtn, #restartBtn").show();
                updateTunnelStatus(i18n);
            } else {
                $("#svc_status").html(
                    '<span class="label label-danger">'
                    + '<i class="fa fa-stop fa-fw"></i> ' + i18n.stopped
                    + '</span>'
                );
                $("#tunnel_health").html('');
                $("#startBtn").show();
                $("#stopBtn, #restartBtn").hide();
            }
        });
    }

    function updateTunnelStatus(i18n) {
        ajaxCall("/api/cloudflared/service/tunnel_status", {}, function(data) {
            var state = data && data.tunnel ? data.tunnel : 'unknown';
            var html = '';
            if (state === 'healthy') {
                html = '&nbsp;<span class="label label-success">'
                    + '<i class="fa fa-cloud fa-fw"></i> ' + i18n.tunnel_status + ': ' + i18n.healthy
                    + '</span>';
            } else if (state === 'connecting') {
                html = '&nbsp;<span class="label label-warning">'
                    + '<i class="fa fa-cloud fa-fw"></i> ' + i18n.tunnel_status + ': ' + i18n.connecting
                    + '</span>';
            }
            $("#tunnel_health").html(html);
        });
    }
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General Settings') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box-main">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general'])}}
            <div class="col-md-12" style="padding-bottom: 15px;">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button">
                    <b>{{ lang._('Apply') }}</b> <i id="saveAct_progress"></i>
                </button>
                <button class="btn btn-default" id="installBtn" type="button">
                    <i id="installIcon" class="fa fa-download fa-fw"></i>
                    <b>{{ lang._('Install/Update Binary') }}</b>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="content-box" style="padding: 8px 15px; margin-top: 1em;">
    <table style="width: 100%">
        <tr>
            <td>
                <b>{{ lang._('Cloudflare Tunnel') }}</b>
                &nbsp;
                <span id="svc_status"><i class="fa fa-spinner fa-spin"></i></span>
                <span id="tunnel_health"></span>
            </td>
            <td style="text-align: right">
                <button class="btn btn-xs btn-default" id="startBtn" type="button" style="display:none;">
                    <i class="fa fa-play fa-fw"></i> {{ lang._('Start') }}
                </button>
                <button class="btn btn-xs btn-default" id="stopBtn" type="button" style="display:none;">
                    <i class="fa fa-stop fa-fw"></i> {{ lang._('Stop') }}
                </button>
                <button class="btn btn-xs btn-default" id="restartBtn" type="button" style="display:none;">
                    <i class="fa fa-repeat fa-fw"></i> {{ lang._('Restart') }}
                </button>
            </td>
        </tr>
    </table>
</div>
