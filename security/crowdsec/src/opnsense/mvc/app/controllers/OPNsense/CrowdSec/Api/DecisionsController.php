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
     * Retrieve list of decisions
     *
     * @return array of decisions
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function getAction()
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec decisions-list")), true);
        if ($result !== null) {
            // only return valid json type responses
            return $result;
        }
        return ["message" => "unable to list decisions"];
    }

    public function deleteAction($decision_id)
    {
        if ($this->request->isDelete()) {
            $result = (new Backend())->configdRun("crowdsec decisions-delete ${decision_id}");
            if ($result !== null) {
                // why does the action return \n\n for empty output?
                if (trim($result) === '') {
                    return ["message" => "OK"];
                }
                // TODO handle error
                return ["message" => result];
            }
            return ["message" => "OK"];
        } else {
            $this->response->setStatusCode(405, "Method Not Allowed");
            $this->response->setHeader("Allow", "DELETE");
        }
    }
}
