<?php

namespace OPNsense\RTSPHelper\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class StatusController extends ApiControllerBase
{
    public function connectionsAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('rtsphelper connections');
        $rows = array();

        foreach (explode("\n", $response) as $line) {
            if (preg_match("/on (.*) inet proto (.*) from (.*) to (.*) port = (.*) -> (.*)/", $line, $matches)) {
                $rows[] = array(
                    "interface" => $matches[1],
                    "proto" => $matches[2],
                    "source" => $matches[3],
                    "destination" => $matches[4],
                    "port" => $matches[5],
                    "redirect_to" => $matches[6]
                );
            }
        }

        return array("rows" => $rows);
    }
}
