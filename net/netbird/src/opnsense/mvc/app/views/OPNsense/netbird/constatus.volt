<script>

    $(document).ready(function () {

        function refreshConStatus() {
            $("#refreshAct_progress").addClass("fa fa-spinner fa-pulse");

            ajaxCall(url = "/api/netbird/service/upDownStatus", sendData = {}, callback = function (data, status) {
                if (data['updown'] == "UP") {
                    $("#setUpAct").prop('disabled', true);
                    $("#setDownAct").prop('disabled', false);
                    $("#peers").prop('hidden', false);
                } else if (data['updown'] == "DOWN") {
                    $("#setUpAct").prop('disabled', false);
                    $("#setDownAct").prop('disabled', true);
                    $("#peers").prop('hidden', true);
                } else {
                    $("#setUpAct").prop('disabled', true);
                    $("#setDownAct").prop('disabled', true);
                    $("#peers").prop('hidden', true);
                }
                $("#updown").html(data['updown']);
                $("#constatustxt").html(data['status']);
                std_bootgrid_reload('grid-peers');
                $("#refreshAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        }

        $("#refreshAct").click(function () {
            refreshConStatus();
        });

        $("#setUpAct").click(function () {
            $("#setUp_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/netbird/service/setup", sendData = {}, callback = function (data, status) {
                setTimeout(function () {
                    refreshConStatus();
                    $("#setUp_progress").removeClass("fa fa-spinner fa-pulse");
                }, 3000);

            });
        });

        $("#setDownAct").click(function () {
            $("#setDown_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/netbird/service/setdown", sendData = {}, callback = function (data, status) {
                setTimeout(function () {
                    refreshConStatus();
                    $("#setDown_progress").removeClass("fa fa-spinner fa-pulse");
                }, 500);

            });
        });

        $("#service_status_container").click(function () {
            setTimeout(function () {
                refreshConStatus();
            }, 2000);
        });

        $("#grid-peers").UIBootgrid(
            {
                search: '/api/netbird/service/search'
            }
        );

        refreshConStatus();
        updateServiceControlUI('netbird');

    });
</script>
<div class="col-md-12">
    <h2>Netbird Connection</h2>
    <span id="updown"></span>
</div>
<div class="col-md-12">
    <button class="btn" id="setUpAct" type="button"><b>{{ lang._('Set UP') }}</b><i id="setUp_progress"></i></button>
    <button class="btn" id="setDownAct" type="button"><b>{{ lang._('Set DOWN') }}</b><i id="setDown_progress"></i>
    </button>
</div>

<div id="peers" class="col-md-12">
    <h2>Peers</h2>
    <table id="grid-peers" class="table table-condensed table-hover table-striped">
        <thead>
        <tr>
            <th data-column-id="fqdn" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('FQDN') }}</th>
            <th data-column-id="routes" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('Routes') }}</th>
            <th data-width="8%" data-column-id="netbirdIp" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('IP') }}</th>
            <th data-width="5%" data-column-id="direct" data-type="string" data-identifier="false"
                data-formatter="boolean"
                data-visible="true">{{ lang._('Direct') }}</th>
            <th data-width="5%" data-column-id="status" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('Status') }}</th>
            <th data-width="8%" data-column-id="lastWireguardHandshake" data-type="date" data-identifier="false"
                data-visible="true">{{ lang._('Last Handshake') }}</th>
            <th data-width="8%" data-column-id="lastStatusUpdate" data-type="date" data-identifier="false"
                data-visible="true">{{ lang._('Last Status Update') }}</th>
            <th data-width="5%" data-column-id="transferReceived" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('Received') }}</th>
            <th data-width="5%" data-column-id="transferSent" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('Sent') }}</th>
            <th data-width="5%" data-column-id="latency" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('Latency') }}</th>
            <th data-width="5%" data-column-id="connectionType" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('Connection Type') }}</th>
            <th data-width="5%" data-column-id="quantumResistance" data-type="string" data-identifier="false"
                data-formatter="boolean"
                data-visible="true">{{ lang._('QR') }}</th>
            <th data-width="5%" data-column-id="iceCandidateType.local" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('ICE TL') }}</th>
            <th data-width="5%" data-column-id="iceCandidateType.remote" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('ICE TR') }}</th>
            <th data-width="8%" data-column-id="iceCandidateEndpoint.local" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('ICE EP Local') }}</th>
            <th data-width="8%" data-column-id="iceCandidateEndpoint.remote" data-type="string" data-identifier="false"
                data-visible="true">{{ lang._('ICE EP Remote') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
<div class="col-md-12">
    <h2>{{ lang._('Status Output') }}</h2>
    <section id="constatustxt" class="col-xs-11">
    </section>
</div>

<div class="col-md-12">
    <button class="btn" id="refreshAct" type="button"><b>{{ lang._('Refresh') }}</b><i id="refreshAct_progress"></i>
    </button>
</div>
