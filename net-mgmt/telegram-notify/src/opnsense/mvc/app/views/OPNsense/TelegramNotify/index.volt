<script>
$(document).ready(function () {
    mapDataToFormUI({'frm_telegramnotify': '/api/telegramnotify/settings/get'});

    $('#saveAct').click(function () {
        saveFormToEndpoint('/api/telegramnotify/settings/set', 'frm_telegramnotify', function () {
            $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html('{{ lang._('Settings saved.') }}');
        });
    });

    $('#testAct').click(function () {
        var msg = $('#testMessage').val();
        var eventType = $('#testEventType').val();
        ajaxCall('/api/telegramnotify/service/test', {'message': msg, 'event_type': eventType}, function (data, status) {
            if (status === 'success' && data['status'] === 'ok') {
                $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html(data['message']);
            } else {
                var err = (data && data['message']) ? data['message'] : '{{ lang._('Test failed.') }}';
                $('#responseMsg').removeClass('hidden alert-info').addClass('alert-danger').html(err);
            }
        });
    });

    $('#enableIdsAutoAct').click(function () {
        ajaxCall('/api/telegramnotify/service/enableIdsCron', {}, function (data, status) {
            if (status === 'success' && (data['status'] === 'ok' || data['result'] === 'ok')) {
                $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html(data['message'] || '{{ lang._('IDS auto alerts cron rule created.') }}');
            } else {
                var err = (data && data['message']) ? data['message'] : '{{ lang._('Unable to create IDS auto alerts cron rule.') }}';
                $('#responseMsg').removeClass('hidden alert-info').addClass('alert-danger').html(err);
            }
        });
    });

    $('#enableMonitAutoAct').click(function () {
        ajaxCall('/api/telegramnotify/service/enableMonitCron', {}, function (data, status) {
            if (status === 'success' && (data['status'] === 'ok' || data['result'] === 'ok')) {
                $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html(data['message'] || '{{ lang._('Monit auto alerts cron rule created.') }}');
            } else {
                var err = (data && data['message']) ? data['message'] : '{{ lang._('Unable to create Monit auto alerts cron rule.') }}';
                $('#responseMsg').removeClass('hidden alert-info').addClass('alert-danger').html(err);
            }
        });
    });

    $('#disableMonitAutoAct').click(function () {
        ajaxCall('/api/telegramnotify/service/disableMonitCron', {}, function (data, status) {
            if (status === 'success' && (data['status'] === 'ok' || data['result'] === 'ok')) {
                $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html(data['message'] || '{{ lang._('Monit auto alerts cron rule disabled.') }}');
            } else {
                var err = (data && data['message']) ? data['message'] : '{{ lang._('Unable to disable Monit auto alerts cron rule.') }}';
                $('#responseMsg').removeClass('hidden alert-info').addClass('alert-danger').html(err);
            }
        });
    });

    $('#toggleMonitInfo').click(function () {
        $('#monitInfoBox').toggleClass('hidden');
    });

    $('#toggleIdsInfo').click(function () {
        $('#idsInfoBox').toggleClass('hidden');
    });

    $('#disableIdsAutoAct').click(function () {
        ajaxCall('/api/telegramnotify/service/disableIdsCron', {}, function (data, status) {
            if (status === 'success' && (data['status'] === 'ok' || data['result'] === 'ok')) {
                $('#responseMsg').removeClass('hidden alert-danger').addClass('alert-info').html(data['message'] || '{{ lang._('IDS auto alerts cron rule disabled.') }}');
            } else {
                var err = (data && data['message']) ? data['message'] : '{{ lang._('Unable to disable IDS auto alerts cron rule.') }}';
                $('#responseMsg').removeClass('hidden alert-info').addClass('alert-danger').html(err);
            }
        });
    });
});
</script>

<div class="alert alert-info" role="alert">
    {{ lang._('Configure your Telegram bot and test delivery with a live message.') }}
</div>

<div class="alert alert-info" role="alert" style="margin-bottom:10px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong>{{ lang._('Monit integration') }}</strong>
        <div>
            <button class="btn btn-default" id="toggleMonitInfo" type="button" title="{{ lang._('Show details') }}"><b>i</b></button>
            <button class="btn btn-default" id="enableMonitAutoAct" type="button"><b>{{ lang._('Enable Monit Auto Alerts (Cron)') }}</b></button>
            <button class="btn btn-default" id="disableMonitAutoAct" type="button"><b>{{ lang._('Disable') }}</b></button>
        </div>
    </div>
    <div id="monitInfoBox" class="hidden" style="margin-top:8px;">
        {{ lang._('In Monit, use an alert exec command like:') }}<br/>
        <code>/usr/local/sbin/configctl telegramnotify monit "$SERVICE" "$EVENT" "$ACTION" "$DESCRIPTION" "$HOST" "$DATE"</code><br/>
        {{ lang._('Or enable automatic Monit log polling:') }}<br/>
        <code>/usr/local/sbin/configctl telegramnotify monit.poll 3</code>
    </div>
</div>

<div class="alert alert-info" role="alert" style="margin-bottom:10px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong>{{ lang._('IDS integration (Suricata)') }}</strong>
        <div>
            <button class="btn btn-default" id="toggleIdsInfo" type="button" title="{{ lang._('Show details') }}"><b>i</b></button>
            <button class="btn btn-default" id="enableIdsAutoAct" type="button"><b>{{ lang._('Enable IDS Auto Alerts (Cron)') }}</b></button>
            <button class="btn btn-default" id="disableIdsAutoAct" type="button"><b>{{ lang._('Disable') }}</b></button>
        </div>
    </div>
    <div id="idsInfoBox" class="hidden" style="margin-top:8px;">
        {{ lang._('Run periodically via Automation/Cron to send blocked IDS alerts from eve.json:') }}<br/>
        <code>/usr/local/sbin/configctl telegramnotify ids.poll 5</code><br/>
        {{ lang._('The number is max blocked alerts per run (1-50).') }}
    </div>
</div>

<div class="alert hidden" role="alert" id="responseMsg"></div>

<div class="col-md-12">
    {{ partial("layout_partials/base_form",['fields':settings,'id':'frm_telegramnotify'])}}
</div>

<div class="col-md-12" style="margin-top: 10px;">
    <div class="form-group">
        <label for="testEventType">{{ lang._('Test Event Type') }}</label>
        <select id="testEventType" class="form-control">
            <option value="system">{{ lang._('System') }}</option>
            <option value="gateway">{{ lang._('Gateway') }}</option>
            <option value="service">{{ lang._('Service') }}</option>
            <option value="vpn">{{ lang._('VPN') }}</option>
            <option value="security">{{ lang._('Security') }}</option>
            <option value="updates">{{ lang._('Updates') }}</option>
        </select>
    </div>
    <div class="form-group">
        <label for="testMessage">{{ lang._('Test Message') }}</label>
        <input id="testMessage" class="form-control" type="text" value="OPNsense test notification" />
    </div>
</div>

<div class="col-md-12">
    <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
    <button class="btn btn-default" id="testAct" type="button"><b>{{ lang._('Send Test') }}</b></button>
</div>
