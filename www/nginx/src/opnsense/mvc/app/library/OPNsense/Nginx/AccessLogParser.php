<?php

/*

    Copyright (C) 2018-2020 Fabian Franz
    Copyright (C) 2020 Manuel Faux
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Nginx;

class AccessLogParser extends LogParserBase
{
    private const LogLineRegex = '/(\S+) - (\S+) \[([\d\sa-z\:\-\/\+]+)\] "([^"]+?)" (\d+) (\d+) "([^"]*?)" "([^"]*?)" "([^"]*?)"/i';

    protected function parse_line($line)
    {
        $container = new AccessLogLine();
        if (preg_match(self::LogLineRegex, $line, $data)) {
            $container->remote_ip = $data[1];
            $container->username = $data[2];
            $container->time = $data[3];
            $container->request_line = $data[4];
            $container->status = $data[5];
            $container->size = $data[6];
            $container->user_agent = $data[8];
            $container->http_referer = $data[7];
            $container->forwarded_for = $data[9];
        }
        return $container;
    }
}
