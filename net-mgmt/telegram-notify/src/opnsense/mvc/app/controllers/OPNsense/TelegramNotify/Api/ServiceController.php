<?php

namespace OPNsense\TelegramNotify\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
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
}
