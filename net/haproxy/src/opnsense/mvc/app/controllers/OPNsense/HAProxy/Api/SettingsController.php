<?php

/**
 *    Copyright (C) 2016-2022 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\HAProxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Config;
use OPNsense\HAProxy\HAProxy;

/**
 * Class SettingsController
 * @package OPNsense\HAProxy
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'haproxy';
    protected static $internalModelClass = '\OPNsense\HAProxy\HAProxy';
    protected static $internalModelUseSafeDelete = true;

    public function getFrontendAction($uuid = null)
    {
        return $this->getBase('frontend', 'frontends.frontend', $uuid);
    }

    public function setFrontendAction($uuid)
    {
        return $this->setBase('frontend', 'frontends.frontend', $uuid);
    }

    public function addFrontendAction()
    {
        return $this->addBase('frontend', 'frontends.frontend');
    }

    public function delFrontendAction($uuid)
    {
        return $this->delBase('frontends.frontend', $uuid);
    }

    public function toggleFrontendAction($uuid)
    {
        return $this->toggleBase('frontends.frontend', $uuid);
    }

    public function searchFrontendsAction()
    {
        return $this->searchBase('frontends.frontend', array('enabled', 'name', 'description'), 'name');
    }

    public function getBackendAction($uuid = null)
    {
        return $this->getBase('backend', 'backends.backend', $uuid);
    }

    public function setBackendAction($uuid)
    {
        return $this->setBase('backend', 'backends.backend', $uuid);
    }

    public function addBackendAction()
    {
        return $this->addBase('backend', 'backends.backend');
    }

    public function delBackendAction($uuid)
    {
        return $this->delBase('backends.backend', $uuid);
    }

    public function toggleBackendAction($uuid, $enabled = null)
    {
        return $this->toggleBase('backends.backend', $uuid);
    }

    public function searchBackendsAction()
    {
        return $this->searchBase('backends.backend', array('enabled', 'name', 'description'), 'name');
    }

    public function getServerAction($uuid = null)
    {
        return $this->getBase('server', 'servers.server', $uuid);
    }

    public function setServerAction($uuid)
    {
        return $this->setBase('server', 'servers.server', $uuid);
    }

    public function addServerAction()
    {
        return $this->addBase('server', 'servers.server');
    }

    public function delServerAction($uuid)
    {
        return $this->delBase('servers.server', $uuid);
    }

    public function toggleServerAction($uuid, $enabled = null)
    {
        return $this->toggleBase('servers.server', $uuid);
    }

    public function searchServersAction()
    {
        return $this->searchBase('servers.server', array('enabled', 'name', 'type', 'address', 'port', 'description'), 'name');
    }

    public function getHealthcheckAction($uuid = null)
    {
        return $this->getBase('healthcheck', 'healthchecks.healthcheck', $uuid);
    }

    public function setHealthcheckAction($uuid)
    {
        return $this->setBase('healthcheck', 'healthchecks.healthcheck', $uuid);
    }

    public function addHealthcheckAction()
    {
        return $this->addBase('healthcheck', 'healthchecks.healthcheck');
    }

    public function delHealthcheckAction($uuid)
    {
        return $this->delBase('healthchecks.healthcheck', $uuid);
    }

    public function searchHealthchecksAction()
    {
        return $this->searchBase('healthchecks.healthcheck', array('name', 'description'), 'name');
    }

    public function getAclAction($uuid = null)
    {
        return $this->getBase('acl', 'acls.acl', $uuid);
    }

    public function setAclAction($uuid)
    {
        return $this->setBase('acl', 'acls.acl', $uuid);
    }

    public function addAclAction()
    {
        return $this->addBase('acl', 'acls.acl');
    }

    public function delAclAction($uuid)
    {
        return $this->delBase('acls.acl', $uuid);
    }

    public function searchAclsAction()
    {
        return $this->searchBase('acls.acl', array('name', 'description'), 'name');
    }

    public function getActionAction($uuid = null)
    {
        return $this->getBase('action', 'actions.action', $uuid);
    }

    public function setActionAction($uuid)
    {
        return $this->setBase('action', 'actions.action', $uuid);
    }

    public function addActionAction()
    {
        return $this->addBase('action', 'actions.action');
    }

    public function delActionAction($uuid)
    {
        return $this->delBase('actions.action', $uuid);
    }

    public function searchActionsAction()
    {
        return $this->searchBase('actions.action', array('name', 'description'), 'name');
    }

    public function getLuaAction($uuid = null)
    {
        return $this->getBase('lua', 'luas.lua', $uuid);
    }

    public function setLuaAction($uuid)
    {
        return $this->setBase('lua', 'luas.lua', $uuid);
    }

    public function addLuaAction()
    {
        return $this->addBase('lua', 'luas.lua');
    }

    public function delLuaAction($uuid)
    {
        return $this->delBase('luas.lua', $uuid);
    }

    public function toggleLuaAction($uuid, $enabled = null)
    {
        return $this->toggleBase('luas.lua', $uuid);
    }

    public function searchLuasAction()
    {
        return $this->searchBase('luas.lua', array('enabled', 'name', 'description'), 'name');
    }

    public function getFcgiAction($uuid = null)
    {
        return $this->getBase('fcgi', 'fcgis.fcgi', $uuid);
    }

    public function setFcgiAction($uuid)
    {
        return $this->setBase('fcgi', 'fcgis.fcgi', $uuid);
    }

    public function addFcgiAction()
    {
        return $this->addBase('fcgi', 'fcgis.fcgi');
    }

    public function delFcgiAction($uuid)
    {
        return $this->delBase('fcgis.fcgi', $uuid);
    }

    public function searchFcgisAction()
    {
        return $this->searchBase('fcgis.fcgi', array('name', 'description'), 'name');
    }

    public function getErrorfileAction($uuid = null)
    {
        return $this->getBase('errorfile', 'errorfiles.errorfile', $uuid);
    }

    public function setErrorfileAction($uuid)
    {
        return $this->setBase('errorfile', 'errorfiles.errorfile', $uuid);
    }

    public function addErrorfileAction()
    {
        return $this->addBase('errorfile', 'errorfiles.errorfile');
    }

    public function delErrorfileAction($uuid)
    {
        return $this->delBase('errorfiles.errorfile', $uuid);
    }

    public function searchErrorfilesAction()
    {
        return $this->searchBase('errorfiles.errorfile', array('name', 'description'), 'name');
    }

    public function getMapfileAction($uuid = null)
    {
        return $this->getBase('mapfile', 'mapfiles.mapfile', $uuid);
    }

    public function setMapfileAction($uuid)
    {
        return $this->setBase('mapfile', 'mapfiles.mapfile', $uuid);
    }

    public function addMapfileAction()
    {
        return $this->addBase('mapfile', 'mapfiles.mapfile');
    }

    public function delMapfileAction($uuid)
    {
        return $this->delBase('mapfiles.mapfile', $uuid);
    }

    public function searchMapfilesAction()
    {
        return $this->searchBase('mapfiles.mapfile', array('name', 'description'), 'name');
    }

    public function getCpuAction($uuid = null)
    {
        return $this->getBase('cpu', 'cpus.cpu', $uuid);
    }

    public function setCpuAction($uuid)
    {
        return $this->setBase('cpu', 'cpus.cpu', $uuid);
    }

    public function addCpuAction()
    {
        return $this->addBase('cpu', 'cpus.cpu');
    }

    public function delCpuAction($uuid)
    {
        return $this->delBase('cpus.cpu', $uuid);
    }

    public function toggleCpuAction($uuid, $enabled = null)
    {
        return $this->toggleBase('cpus.cpu', $uuid);
    }

    public function searchCpusAction()
    {
        return $this->searchBase('cpus.cpu', array('enabled', 'name', 'thread_id', 'cpu_id'), 'name');
    }

    public function getGroupAction($uuid = null)
    {
        return $this->getBase('group', 'groups.group', $uuid);
    }

    public function setGroupAction($uuid)
    {
        return $this->setBase('group', 'groups.group', $uuid);
    }

    public function addGroupAction()
    {
        return $this->addBase('group', 'groups.group');
    }

    public function delGroupAction($uuid)
    {
        return $this->delBase('groups.group', $uuid);
    }

    public function toggleGroupAction($uuid, $enabled = null)
    {
        return $this->toggleBase('groups.group', $uuid);
    }

    public function searchGroupsAction()
    {
        return $this->searchBase('groups.group', array('enabled', 'name', 'description'), 'name');
    }

    public function getUserAction($uuid = null)
    {
        return $this->getBase('user', 'users.user', $uuid);
    }

    public function setUserAction($uuid)
    {
        return $this->setBase('user', 'users.user', $uuid);
    }

    public function addUserAction()
    {
        return $this->addBase('user', 'users.user');
    }

    public function delUserAction($uuid)
    {
        return $this->delBase('users.user', $uuid);
    }

    public function toggleUserAction($uuid, $enabled = null)
    {
        return $this->toggleBase('users.user', $uuid);
    }

    public function searchUsersAction()
    {
        return $this->searchBase('users.user', array('enabled', 'name', 'description'), 'name');
    }

    public function getresolverAction($uuid = null)
    {
        return $this->getBase('resolver', 'resolvers.resolver', $uuid);
    }

    public function setresolverAction($uuid)
    {
        return $this->setBase('resolver', 'resolvers.resolver', $uuid);
    }

    public function addresolverAction()
    {
        return $this->addBase('resolver', 'resolvers.resolver');
    }

    public function delresolverAction($uuid)
    {
        return $this->delBase('resolvers.resolver', $uuid);
    }

    public function toggleresolverAction($uuid, $enabled = null)
    {
        return $this->toggleBase('resolvers.resolver', $uuid);
    }

    public function searchresolversAction()
    {
        return $this->searchBase('resolvers.resolver', array('enabled', 'name', 'nameservers'), 'name');
    }

    public function getmailerAction($uuid = null)
    {
        return $this->getBase('mailer', 'mailers.mailer', $uuid);
    }

    public function setmailerAction($uuid)
    {
        return $this->setBase('mailer', 'mailers.mailer', $uuid);
    }

    public function addmailerAction()
    {
        return $this->addBase('mailer', 'mailers.mailer');
    }

    public function delmailerAction($uuid)
    {
        return $this->delBase('mailers.mailer', $uuid);
    }

    public function togglemailerAction($uuid, $enabled = null)
    {
        return $this->toggleBase('mailers.mailer', $uuid);
    }

    public function searchmailersAction()
    {
        return $this->searchBase('mailers.mailer', array('enabled', 'name', 'mailservers', 'sender', 'recipient'), 'name');
    }
}
