<?php

namespace OPNsense\TelegramNotify\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Cron\Cron;
use OPNsense\Core\Config;
use OPNsense\TelegramNotify\TelegramNotify;

class ServiceController extends ApiControllerBase
{
    private static $validEvents = [
        'system', 'gateway', 'service', 'vpn', 'security', 'updates',
    ];

    private static $eventFields = [
        'system'   => 'eventSystem',
        'gateway'  => 'eventGateway',
        'service'  => 'eventService',
        'vpn'      => 'eventVpn',
        'security' => 'eventSecurity',
        'updates'  => 'eventUpdates',
    ];

    public function testAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }

        $model = new TelegramNotify();
        $general = $model->general;

        if ((string)$general->enabled === '0') {
            return ['status' => 'failed', 'message' => 'Telegram notifications are disabled'];
        }

        if (trim((string)$general->botToken) === '' || trim((string)$general->chatId) === '') {
            return ['status' => 'failed', 'message' => 'Bot token and Chat ID are required'];
        }

        $eventType = strtolower(trim((string)$this->request->getPost('event_type')));
        if ($eventType === '') {
            $eventType = 'system';
        }

        if (!in_array($eventType, self::$validEvents, true)) {
            return ['status' => 'failed', 'message' => 'Invalid event type'];
        }

        $eventField = self::$eventFields[$eventType];
        if ((string)$general->$eventField === '0') {
            return ['status' => 'failed', 'message' => 'Selected event type is disabled in settings'];
        }

        $message = trim((string)$this->request->getPost('message'));
        if ($message === '') {
            $message = 'OPNsense test notification from Telegram Notify plugin.';
        }

        // Delegate the HTTP call to configd (runs as root). The backend script
        // uses drill @DNS to resolve api.telegram.org and curl --resolve to
        // bypass the system resolver, which avoids DNS timeouts from PHP-FPM.
        $raw = trim((new Backend())->configdpRun(
            'telegramnotify send',
            [$eventType, base64_encode($message)]
        ));

        if ($raw === '') {
            return ['status' => 'failed', 'message' => 'No response from backend (configd may not have loaded the new action yet — wait a few seconds and try again)'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['status' => 'failed', 'message' => 'Backend returned unexpected output: ' . substr($raw, 0, 200)];
        }

        if (!empty($decoded['ok'])) {
            return ['status' => 'ok', 'message' => 'Test message sent successfully', 'event_type' => $eventType];
        }

        $apiMessage = !empty($decoded['description']) ? $decoded['description'] : 'Unknown Telegram API error';
        return ['status' => 'failed', 'message' => $apiMessage];
    }

    public function enableIdsCronAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }

        $model = new TelegramNotify();
        $backend = new Backend();
        $cronModel = new Cron();

        $existingUuid = trim((string)$model->general->idsPollCron);
        if ($existingUuid !== '' && $cronModel->getNodeByReference('jobs.job.' . $existingUuid) != null) {
            return [
                'status' => 'ok',
                'result' => 'ok',
                'message' => 'IDS auto alerts cron rule already exists',
                'uuid' => $existingUuid
            ];
        }

        foreach ($cronModel->getNodeByReference('jobs.job')->iterateItems() as $cronItem) {
            if ((string)$cronItem->origin === 'TelegramNotify' && (string)$cronItem->command === 'telegramnotify ids poll') {
                $foundUuid = $cronItem->getAttributes()['uuid'];
                $model->general->idsPollCron = $foundUuid;
                $model->serializeToConfig($validateFullModel = false, $disable_validation = true);
                Config::getInstance()->save();
                return [
                    'status' => 'ok',
                    'result' => 'ok',
                    'message' => 'Linked existing IDS auto alerts cron rule',
                    'uuid' => $foundUuid
                ];
            }
        }

        $uuid = $cronModel->newDailyJob(
            'TelegramNotify',
            'telegramnotify ids poll',
            'Poll IDS alerts and send Telegram notifications',
            '5',
            '1'
        );

        $cronJob = $cronModel->getNodeByReference('jobs.job.' . $uuid);
        if ($cronJob != null) {
            $cronJob->minutes = '*';
            $cronJob->hours = '*';
            $cronJob->days = '*';
            $cronJob->months = '*';
            $cronJob->weekdays = '*';
        }

        if ($cronModel->performValidation()->count() > 0) {
            return ['status' => 'failed', 'message' => 'Unable to add IDS auto alerts cron rule'];
        }

        $cronModel->serializeToConfig();
        $model->general->idsPollCron = $uuid;
        $model->serializeToConfig($validateFullModel = false, $disable_validation = true);
        Config::getInstance()->save();

        $backend->configdRun('template reload OPNsense/Cron');
        $backend->configdRun('cron restart');

        return [
            'status' => 'ok',
            'result' => 'ok',
            'message' => 'IDS auto alerts cron rule created (runs every minute, max 5 alerts per run)',
            'uuid' => $uuid
        ];
    }

    public function enableMonitCronAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }

        $model = new TelegramNotify();
        $backend = new Backend();
        $cronModel = new Cron();

        $existingUuid = trim((string)$model->general->monitPollCron);
        if ($existingUuid !== '' && $cronModel->getNodeByReference('jobs.job.' . $existingUuid) != null) {
            return [
                'status' => 'ok',
                'result' => 'ok',
                'message' => 'Monit auto alerts cron rule already exists',
                'uuid' => $existingUuid
            ];
        }

        foreach ($cronModel->getNodeByReference('jobs.job')->iterateItems() as $cronItem) {
            if ((string)$cronItem->origin === 'TelegramNotify' && (string)$cronItem->command === 'telegramnotify monit poll') {
                $foundUuid = $cronItem->getAttributes()['uuid'];
                $model->general->monitPollCron = $foundUuid;
                $model->serializeToConfig($validateFullModel = false, $disable_validation = true);
                Config::getInstance()->save();
                return [
                    'status' => 'ok',
                    'result' => 'ok',
                    'message' => 'Linked existing Monit auto alerts cron rule',
                    'uuid' => $foundUuid
                ];
            }
        }

        $uuid = $cronModel->newDailyJob(
            'TelegramNotify',
            'telegramnotify monit poll',
            'Poll Monit alerts and send Telegram notifications',
            '3',
            '1'
        );

        $cronJob = $cronModel->getNodeByReference('jobs.job.' . $uuid);
        if ($cronJob != null) {
            $cronJob->minutes = '*';
            $cronJob->hours = '*';
            $cronJob->days = '*';
            $cronJob->months = '*';
            $cronJob->weekdays = '*';
        }

        if ($cronModel->performValidation()->count() > 0) {
            return ['status' => 'failed', 'message' => 'Unable to add Monit auto alerts cron rule'];
        }

        $cronModel->serializeToConfig();
        $model->general->monitPollCron = $uuid;
        $model->serializeToConfig($validateFullModel = false, $disable_validation = true);
        Config::getInstance()->save();

        $backend->configdRun('template reload OPNsense/Cron');
        $backend->configdRun('cron restart');

        return [
            'status' => 'ok',
            'result' => 'ok',
            'message' => 'Monit auto alerts cron rule created (runs every minute, max 3 alerts per run)',
            'uuid' => $uuid
        ];
    }
}
