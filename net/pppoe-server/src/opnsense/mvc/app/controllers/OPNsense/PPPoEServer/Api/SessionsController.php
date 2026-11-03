<?php

/*
 * Copyright (C) 2026 VEQNORA
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\PPPoEServer\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Class SessionsController active PPPoE session management
 * @package OPNsense\PPPoEServer\Api
 */
class SessionsController extends ApiControllerBase
{
    private const SESSION_ID_PATTERN = '/^[0-9A-Za-z._-]{1,32}$/';
    private const USERNAME_PATTERN = '/^[0-9a-zA-Z._@-]{1,64}$/';
    private const BUNDLE_PATTERN = '/^[0-9A-Za-z._-]{1,32}$/';

    /**
     * fetch active sessions from the daemon console
     * @return array
     */
    private function fetchSessions()
    {
        $response = (new Backend())->configdRun('pppoe_server sessions');
        $payload = json_decode((string)$response, true);
        if (!is_array($payload) || ($payload['status'] ?? '') != 'ok') {
            return null;
        }
        return $payload['sessions'] ?? [];
    }

    /**
     * bootgrid compatible list of active sessions
     * @return array
     */
    public function searchAction()
    {
        $sessions = $this->fetchSessions();
        if ($sessions === null) {
            return ['rows' => [], 'rowCount' => 0, 'total' => 0, 'current' => 1, 'status' => 'failed'];
        }
        return [
            'rows' => $sessions,
            'rowCount' => count($sessions),
            'total' => count($sessions),
            'current' => 1,
            'status' => 'ok',
        ];
    }

    /**
     * disconnect a single session by its session id
     * @return array
     */
    public function disconnectAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost('session_id')) {
            return ['status' => 'failed', 'message' => gettext('Invalid request.')];
        }
        $sessionId = (string)$this->request->getPost('session_id');
        if (!preg_match(self::SESSION_ID_PATTERN, $sessionId)) {
            return ['status' => 'failed', 'message' => gettext('Invalid session id.')];
        }
        $response = (new Backend())->configdpRun('pppoe_server disconnect', [$sessionId]);
        $payload = json_decode((string)$response, true);
        return is_array($payload) ? $payload : ['status' => 'failed'];
    }

    /**
     * disconnect all sessions on an access concentrator (bundle name)
     * @return array
     */
    public function disconnectAcAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost('bundle')) {
            return ['status' => 'failed', 'message' => gettext('Invalid request.')];
        }
        $bundle = (string)$this->request->getPost('bundle');
        if (!preg_match(self::BUNDLE_PATTERN, $bundle)) {
            return ['status' => 'failed', 'message' => gettext('Invalid bundle name.')];
        }
        $response = (new Backend())->configdpRun('pppoe_server disconnect_ac', [$bundle]);
        $payload = json_decode((string)$response, true);
        return is_array($payload) ? $payload : ['status' => 'failed'];
    }

    /**
     * disconnect all sessions belonging to a username
     * @return array
     */
    public function disconnectUserAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost('username')) {
            return ['status' => 'failed', 'message' => gettext('Invalid request.')];
        }
        $username = (string)$this->request->getPost('username');
        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            return ['status' => 'failed', 'message' => gettext('Invalid username.')];
        }
        $response = (new Backend())->configdpRun('pppoe_server disconnect_user', [$username]);
        $payload = json_decode((string)$response, true);
        return is_array($payload) ? $payload : ['status' => 'failed'];
    }
}
