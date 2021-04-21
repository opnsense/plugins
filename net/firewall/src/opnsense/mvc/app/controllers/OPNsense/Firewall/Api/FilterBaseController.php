<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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
namespace OPNsense\Firewall\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class FilterBaseController implements actions for various types
 * @package OPNsense\Firewall\Api
 */
abstract class FilterBaseController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'filter';
    protected static $internalModelClass = 'OPNsense\Firewall\Filter';

    public function applyAction($rollback_revision = null)
    {
        if ($this->request->isPost()) {
            if ($rollback_revision != null) {
                // background rollback timer
                (new Backend())->configdpRun('pfplugin rollback_timer', [$rollback_revision], true);
            }
            return array("status" => (new Backend())->configdRun('filter reload'));
        } else {
            return array("status" => "error");
        }
    }

    public function cancelRollbackAction($rollback_revision)
    {
        if ($this->request->isPost()) {
            return array(
                "status" => (new Backend())->configdpRun('pfplugin cancel_rollback', [$rollback_revision])
            );
        } else {
            return array("status" => "error");
        }
    }

    public function savepointAction()
    {
        if ($this->request->isPost()) {
            // trigger a save, so we know revision->time matches our running config
            Config::getInstance()->save();
            return array(
                "status" => "ok",
                "retention" => (string)Config::getInstance()->backupCount(),
                "revision" => (string)Config::getInstance()->object()->revision->time
            );
        } else {
            return array("status" => "error");
        }
    }

    public function revertAction($revision)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $filename = Config::getInstance()->getBackupFilename($revision);
            if (!$filename) {
                Config::getInstance()->unlock();
                return ["status" => gettext("unknown (or removed) savepoint")];
            }
            $this->getModel()->rollback($revision);
            Config::getInstance()->unlock();
            (new Backend())->configdRun('filter reload');
            return ["status" => "ok"];
        } else {
            return array("status" => "error");
        }
    }
}
