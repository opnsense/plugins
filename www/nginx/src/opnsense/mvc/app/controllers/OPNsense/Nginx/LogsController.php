<?php

/*

    Copyright (C) 2020 Manuel Faux
    Copyright (C) 2018-2020 Fabian Franz
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

/**
* Class IndexController
* @package OPNsense/Nginx
*/
class LogsController extends \OPNsense\Base\IndexController
{
    /**
     * show the configuration page /ui/nginx/logs
     * @throws \Exception when a form cannot be loaded
     */
    public function indexAction()
    {
        $this->view->log = 'global';
        $this->view->pick('OPNsense/Nginx/logs');
    }

    /**
     * show the nginx logs page /ui/nginx/logs/accesses
     */
    public function accessesAction()
    {
        $this->view->log = 'accesses';
        $this->view->pick('OPNsense/Nginx/logs');
    }

    /**
     * show the nginx logs page /ui/nginx/logs/accesses
     */
    public function errorsAction()
    {
        $this->view->log = 'errors';
        $this->view->pick('OPNsense/Nginx/logs');
    }

    /**
     * show the nginx logs page /ui/nginx/logs/accesses
     */
    public function stream_accessesAction()
    {
        $this->view->log = 'stream_accesses';
        $this->view->pick('OPNsense/Nginx/logs');
    }

    /**
     * show the nginx logs page /ui/nginx/logs/accesses
     */
    public function stream_errorsAction()
    {
        $this->view->log = 'stream_errors';
        $this->view->pick('OPNsense/Nginx/logs');
    }
}
