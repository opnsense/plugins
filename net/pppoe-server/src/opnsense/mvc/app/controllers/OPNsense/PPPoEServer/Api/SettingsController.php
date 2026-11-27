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

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class SettingsController handles PPPoE server settings
 * @package OPNsense\PPPoEServer\Api
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'pppoeserver';
    protected static $internalModelClass = '\OPNsense\PPPoEServer\PPPoEServer';

    /**
     * full model fetch with all stored secrets stripped
     * @return array
     */
    public function getAction()
    {
        $result = parent::getAction();
        if (isset($result[static::$internalModelName])) {
            $data = &$result[static::$internalModelName];
            if (isset($data['users']['user'])) {
                foreach ($data['users']['user'] as &$user) {
                    if (isset($user['password'])) {
                        $user['password'] = '';
                    }
                }
            }
            if (isset($data['radius']['server'])) {
                foreach ($data['radius']['server'] as &$server) {
                    if (isset($server['secret'])) {
                        $server['secret'] = '';
                    }
                }
            }
            if (isset($data['radius']['coa']['secret'])) {
                $data['radius']['coa']['secret'] = '';
            }
        }
        return $result;
    }

    /**
     * preserve the write-only CoA secret when the General form is saved
     * without re-entering it (empty posted value keeps the stored secret)
     * @return array
     */
    public function setAction()
    {
        $post = $this->request->getPost(static::$internalModelName);
        if (
            is_array($post) &&
            isset($post['radius']['coa']['secret']) &&
            $post['radius']['coa']['secret'] === ''
        ) {
            $current = (string)$this->getModel()->radius->coa->secret;
            if ($current !== '') {
                $post['radius']['coa']['secret'] = $current;
                $_POST[static::$internalModelName] = $post;
            }
        }
        return parent::setAction();
    }

    /* access concentrators */

    public function searchAcAction()
    {
        return $this->searchBase(
            'acs.ac',
            ['enabled', 'description', 'acname', 'interface', 'gateway'],
            'description'
        );
    }

    public function getAcAction($uuid = null)
    {
        return $this->getBase('ac', 'acs.ac', $uuid);
    }

    public function addAcAction()
    {
        return $this->addBase('ac', 'acs.ac');
    }

    public function setAcAction($uuid)
    {
        return $this->setBase('ac', 'acs.ac', $uuid);
    }

    public function delAcAction($uuid)
    {
        return $this->delBase('acs.ac', $uuid);
    }

    public function toggleAcAction($uuid, $enabled = null)
    {
        return $this->toggleBase('acs.ac', $uuid, $enabled);
    }

    /* address pools */

    public function searchPoolAction()
    {
        return $this->searchBase(
            'pools.pool',
            ['enabled', 'name', 'description', 'start', 'end'],
            'name'
        );
    }

    public function getPoolAction($uuid = null)
    {
        return $this->getBase('pool', 'pools.pool', $uuid);
    }

    public function addPoolAction()
    {
        return $this->addBase('pool', 'pools.pool');
    }

    public function setPoolAction($uuid)
    {
        return $this->setBase('pool', 'pools.pool', $uuid);
    }

    public function delPoolAction($uuid)
    {
        return $this->delBase('pools.pool', $uuid);
    }

    public function togglePoolAction($uuid, $enabled = null)
    {
        return $this->toggleBase('pools.pool', $uuid, $enabled);
    }

    /* RADIUS servers -- shared secret is never returned by the API */

    public function searchRadiusAction()
    {
        return $this->searchBase(
            'radius.server',
            ['enabled', 'description', 'host', 'authport', 'acctport', 'priority'],
            'priority'
        );
    }

    public function getRadiusAction($uuid = null)
    {
        $result = $this->getBase('server', 'radius.server', $uuid);
        if (isset($result['server']['secret'])) {
            $result['server']['secret'] = '';
        }
        return $result;
    }

    public function addRadiusAction()
    {
        return $this->addBase('server', 'radius.server');
    }

    public function setRadiusAction($uuid)
    {
        // an empty posted secret means "keep the stored one"
        $post = $this->request->getPost('server');
        if (is_array($post) && isset($post['secret']) && $post['secret'] === '') {
            $node = $this->getModel()->getNodeByReference('radius.server.' . $uuid);
            if ($node != null) {
                $post['secret'] = (string)$node->secret;
                $_POST['server'] = $post;
            }
        }
        return $this->setBase('server', 'radius.server', $uuid);
    }

    public function delRadiusAction($uuid)
    {
        return $this->delBase('radius.server', $uuid);
    }

    public function toggleRadiusAction($uuid, $enabled = null)
    {
        return $this->toggleBase('radius.server', $uuid, $enabled);
    }

    /* local users -- password is never returned by the API */

    public function searchUserAction()
    {
        return $this->searchBase(
            'users.user',
            ['enabled', 'username', 'description', 'staticip'],
            'username'
        );
    }

    public function getUserAction($uuid = null)
    {
        $result = $this->getBase('user', 'users.user', $uuid);
        if (isset($result['user']['password'])) {
            $result['user']['password'] = '';
        }
        return $result;
    }

    public function addUserAction()
    {
        return $this->addBase('user', 'users.user');
    }

    public function setUserAction($uuid)
    {
        // an empty posted password means "keep the stored one"
        $post = $this->request->getPost('user');
        if (is_array($post) && isset($post['password']) && $post['password'] === '') {
            $node = $this->getModel()->getNodeByReference('users.user.' . $uuid);
            if ($node != null) {
                $post['password'] = (string)$node->password;
                $_POST['user'] = $post;
            }
        }
        return $this->setBase('user', 'users.user', $uuid);
    }

    public function delUserAction($uuid)
    {
        return $this->delBase('users.user', $uuid);
    }

    public function toggleUserAction($uuid, $enabled = null)
    {
        return $this->toggleBase('users.user', $uuid, $enabled);
    }
}
