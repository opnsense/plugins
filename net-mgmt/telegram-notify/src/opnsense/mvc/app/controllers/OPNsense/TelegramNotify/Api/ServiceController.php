<?php

namespace OPNsense\TelegramNotify\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\TelegramNotify\TelegramNotify;

class ServiceController extends ApiControllerBase
{
    private function getEventMap()
    {
        return [
            'system' => ['field' => 'eventSystem', 'label' => 'System'],
            'gateway' => ['field' => 'eventGateway', 'label' => 'Gateway'],
            'service' => ['field' => 'eventService', 'label' => 'Service'],
            'vpn' => ['field' => 'eventVpn', 'label' => 'VPN'],
            'security' => ['field' => 'eventSecurity', 'label' => 'Security'],
            'updates' => ['field' => 'eventUpdates', 'label' => 'Updates'],
        ];
    }

    public function testAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }

        if (!function_exists('curl_init')) {
            return ['status' => 'failed', 'message' => 'PHP cURL extension is not available'];
        }

        $model = new TelegramNotify();
        $general = $model->general;

        if ((string)$general->enabled === '0') {
            return ['status' => 'failed', 'message' => 'Telegram notifications are disabled'];
        }

        $token = trim((string)$general->botToken);
        $chatId = trim((string)$general->chatId);

        if ($token === '' || $chatId === '') {
            return ['status' => 'failed', 'message' => 'Bot token and Chat ID are required'];
        }

        $eventType = strtolower(trim((string)$this->request->getPost('event_type')));
        if ($eventType === '') {
            $eventType = 'system';
        }

        $eventMap = $this->getEventMap();
        if (empty($eventMap[$eventType])) {
            return ['status' => 'failed', 'message' => 'Invalid event type'];
        }

        $eventField = $eventMap[$eventType]['field'];
        if ((string)$general->$eventField === '0') {
            return ['status' => 'failed', 'message' => 'Selected event type is disabled in settings'];
        }

        $message = trim((string)$this->request->getPost('message'));
        if ($message === '') {
            $message = 'OPNsense test notification from Telegram Notify plugin.';
        }

        $message = '[' . $eventMap[$eventType]['label'] . '] ' . $message;

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'disable_web_page_preview' => ((string)$general->disableWebPagePreview === '1') ? 'true' : 'false',
            'disable_notification' => ((string)$general->disableNotification === '1') ? 'true' : 'false'
        ];

        $threadId = trim((string)$general->threadId);
        if ($threadId !== '') {
            $payload['message_thread_id'] = $threadId;
        }

        $parseMode = (string)$general->parseMode;
        if ($parseMode !== '' && $parseMode !== 'None') {
            $payload['parse_mode'] = $parseMode;
        }

        $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
        if ($ch === false) {
            return ['status' => 'failed', 'message' => 'Unable to initialize cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['status' => 'failed', 'message' => 'Telegram API request failed: ' . $curlError];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['status' => 'failed', 'message' => 'Telegram API returned invalid JSON'];
        }

        if (!empty($decoded['ok'])) {
            return [
                'status' => 'ok',
                'message' => 'Test message sent successfully',
                'event_type' => $eventType
            ];
        }

        $apiMessage = !empty($decoded['description']) ? $decoded['description'] : 'unknown Telegram API error';
        if ($statusCode === 0) {
            return ['status' => 'failed', 'message' => 'Telegram API request did not receive an HTTP response'];
        }

        return ['status' => 'failed', 'message' => 'Telegram API error (' . $statusCode . '): ' . $apiMessage];
    }
}
