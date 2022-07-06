<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\CrowdSec\CrowdSec;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\CrowdSec
 */
class DecisionsController extends ApiControllerBase
{
    /**
     * retrieve list of decisions
     * @return array of decisions
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function getAction()
    {
        $backend = new Backend();
        $bckresult = json_decode(trim($backend->configdRun("crowdsec decisions-list")), true);
        if ($bckresult !== null) {
            // only return valid json type responses
            return $bckresult;
        }
        return array("message" => "unable to list decisions");
    }

    public function deleteAction($decision_id)
    {
        if ($this->request->isDelete()) {
            $backend = new Backend();
            $bckresult = $backend->configdRun("crowdsec decisions-delete ${decision_id}");
            if ($bckresult !== null) {
                // why does the action return \n\n for empty output?
                if (trim($bckresult) === '') {
                    return array("message" => "OK");
                }
                // TODO handle error
                return array("message" => $bckresult);
            }
            return array("message" => "OK");
        } else {
            $this->response->setStatusCode(405, "Method Not Allowed");
            $this->response->setHeader("Allow", "DELETE");
        }
    }
}
