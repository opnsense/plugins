<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
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
 */

namespace OPNsense\Proxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

/**
 * Class TemplateController
 * @package OPNsense\Proxy
 */
class TemplateController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'proxy';
    protected static $internalModelClass = '\OPNsense\Proxy\Proxy';

    /**
     * save template
     * @return array status
     * @throws \Phalcon\Filter\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function setAction()
    {
        if ($this->request->isPost() && $this->request->hasPost("content")) {
            $this->sessionClose();
            $mdl = $this->getModel();
            $mdl->error_pages->template = $this->request->getPost("content", "striptags");
            $result = $this->validate();
            if (empty($result['validations'])) {
                // save config if validated correctly
                $this->save();
                $result = array("result" => "saved");
            } else {
                $result["result"] = "failed";
            }
            return $result;
        } else {
            return array("result" => "failed");
        }
    }

    /**
     * reset error_pages template
     */
    public function resetAction()
    {
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            $mdl->error_pages->template = null;
            $this->save();
            return array("result" => "saved");
        }
        return array("result" => "failed");
    }

    /**
     * retrieve error pages template, overlay provided template zip file on top of OPNsense error pages
     * using configd calls
     */
    public function getAction()
    {
        $backend = new Backend();
        $backend->configdRun("template reload OPNsense/Proxy");
        $result = json_decode($backend->configdRun("proxy download_error_pages"), true);
        if ($result != null) {
            $this->response->setRawHeader("Content-Type: application/octet-stream");
            $this->response->setRawHeader("Content-Disposition: attachment; filename=proxy_template.zip");
            return base64_decode($result['payload']);
        } else {
            // return empty response on error
            return "";
        }
    }
}
