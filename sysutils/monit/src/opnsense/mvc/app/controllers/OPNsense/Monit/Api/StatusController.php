<?php

/**
 *    Copyright (C) 2017 EURO-LOG AG
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

namespace OPNsense\Monit\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Monit\Monit;

/**
 * Class StatusController
 * @package OPNsense\Monit
 */
class StatusController extends ApiControllerBase
{
    /**
     * get monit status page
     * see monit(1)
     * @return array
     */
    public function getAction()
    {
        $result = array("result" => "failed", "function" => "getStatus");

        // connect monit httpd socket defined in monitrc by 'set httpd ...'
        if (file_exists("/var/run/monit.sock") && filetype("/var/run/monit.sock") == "socket") {
            // throws an exception therefore no error handling
            $socket = stream_socket_client("unix:///var/run/monit.sock", $errno, $errstr);

            // get monit status page
            $request  = "GET /_status?format=text HTTP/1.0\r\n";

            // get credentials if configured
            $mdlMonit = new Monit();
            if ($mdlMonit->general->httpdUsername->__toString() != null && trim($mdlMonit->general->httpdUsername->__toString()) !== "" &&
                $mdlMonit->general->httpdPassword->__toString() != null && trim($mdlMonit->general->httpdPassword->__toString()) !== "") {
                   $request .= "Authorization: Basic " . base64_encode($mdlMonit->general->httpdUsername->__toString() . ":" . $mdlMonit->general->httpdPassword->__toString()) . "\r\n";
            }
            $request .= "\r\n";
            $count = fwrite($socket, $request);
            $result['count'] = $count;
            $result['status'] = '';
            $result['orig'] = '';
            $result['httpstatus'] = preg_replace("/\r|\n/", "", fgets($socket));
            $ignorelines = 1;
            if ($result['httpstatus'] == 'HTTP/1.0 200 OK') {
                while (!feof($socket)) {
                    $line = fgets($socket);
                    $result['orig'] .= $line;

                    // ignore lines (mostly HTTP headers) until a line starts with 'Monit' e.g. 'Monit 5.20.0 uptime: 2d 23h 2m'
                    if (substr($line, 0, 5) == 'Monit') {
                        $ignorelines = 0;
                    }
                    if ($ignorelines) {
                        continue;
                    }
                    $result['status'] .= $line;
                }
                $result['result'] = "ok";
            }
            fclose($socket);

            // response contains shell color escape codes; convert them to CSS
            $result['status'] = '<pre style="color:WhiteSmoke;background-color:DimGrey">' . $this->bashColorToCSS($result['status']) . '</pre>';
        } else {
            $result['status'] = '<pre style="color:WhiteSmoke;background-color:DimGrey">
Either the file /var/run/monit.sock does not exists or it is not a unix socket.
Please check if the Monit service is running.

If you have started Monit recently, wait for StartDelay seconds and refresh this page.</pre>';
        }
        return $result;
    }

    /**
     * convert bash color escape codes to CSS
     * @param $string
     * @return string
     */
    private function bashColorToCSS($string)
    {
        $colors = [
            '/\x1b\[0;30m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold">$1</span>',

            '/\x1b\[0;30m(.*?)\x1b\[0m/s' => '<span style="color:Black;">$1</span>',
            '/\x1b\[0;31m(.*?)\x1b\[0m/s' => '<span style="color:Red;">$1</span>',
            '/\x1b\[0;32m(.*?)\x1b\[0m/s' => '<span style="color:Green;">$1</span>',
            '/\x1b\[0;33m(.*?)\x1b\[0m/s' => '<span style="color:Yellow;">$1</span>',
            '/\x1b\[0;34m(.*?)\x1b\[0m/s' => '<span style="color:Blue;">$1</span>',
            '/\x1b\[0;35m(.*?)\x1b\[0m/s' => '<span style="color:Magents;">$1</span>',
            '/\x1b\[0;36m(.*?)\x1b\[0m/s' => '<span style="color:Cyan;">$1</span>',
            '/\x1b\[0;37m(.*?)\x1b\[0m/s' => '<span style="color:WhiteSmoke;">$1</span>',
            '/\x1b\[0;39m(.*?)\x1b\[0m/s' => '<span>$1</span>',

            '/\x1b\[1;30m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Black;">$1</span>',
            '/\x1b\[1;31m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Red;">$1</span>',
            '/\x1b\[1;32m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Green;">$1</span>',
            '/\x1b\[1;33m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Yellow;">$1</span>',
            '/\x1b\[1;34m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Blue;">$1</span>',
            '/\x1b\[1;35m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Magenta;">$1</span>',
            '/\x1b\[1;36m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:Cyan;">$1</span>',
            '/\x1b\[1;37m(.*?)\x1b\[0m/s' => '<span style="font-weight:bold; color:White:">$1</span>',

            '/\x1b\[0;90m(.*?)\x1b\[0m/s' => '<span style="color:DargGrey">$1</span>',
            '/\x1b\[0;91m(.*?)\x1b\[0m/s' => '<span style="color:LightCoral">$1</span>',
            '/\x1b\[0;92m(.*?)\x1b\[0m/s' => '<span style="color:LightGreen;">$1</span>',
            '/\x1b\[0;93m(.*?)\x1b\[0m/s' => '<span style="color:LightYellow;">$1</span>',
            '/\x1b\[0;94m(.*?)\x1b\[0m/s' => '<span style="color:LightSkyBlue;">$1</span>',
            '/\x1b\[0;95m(.*?)\x1b\[0m/s' => '<span style="color:LightPink;">$1</span>',
            '/\x1b\[0;96m(.*?)\x1b\[0m/s' => '<span style="color:LightCyan;">$1</span>',
            '/\x1b\[0;97m(.*?)\x1b\[0m/s' => '<span style="color:White;">$1</span>'
        ];
        return preg_replace(array_keys($colors), $colors, $string);
    }
}
