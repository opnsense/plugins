<?php

/*

    Copyright (C) 2018 Fabian Franz
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
class IndexController extends \OPNsense\Base\IndexController
{
    /**
     * show the configuration page /ui/nginx
     * @throws \Exception when a form cannot be loaded
     */
    public function indexAction()
    {
        $this->view->settings = $this->getForm("settings");
        $this->view->upstream_server = $this->getForm("upstream_server");
        $this->view->upstream = $this->getForm("upstream");
        $this->view->location = $this->getForm("location");
        $this->view->credential = $this->getForm("credential");
        $this->view->userlist = $this->getForm("userlist");
        $this->view->httpserver = $this->getForm("httpserver");
        $this->view->streamserver = $this->getForm("streamserver");
        $this->view->httprewrite = $this->getForm("httprewrite");
        $this->view->naxsi_rule = $this->getForm("naxsi_rule");
        $this->view->naxsi_custom_policy = $this->getForm("naxsi_custom_policy");
        $this->view->security_headers = $this->getForm("security_headers");
        $this->view->limit_request_connection = $this->getForm("limit_request_connection");
        $this->view->limit_zone = $this->getForm("limit_zone");
        $this->view->cache_path = $this->getForm("cache_path");
        $this->view->sni_hostname_map = $this->getForm("sni_hostname_map");
        $this->view->ipacl = $this->getForm("ipacl");
        $this->view->errorpage = $this->getForm("errorpage");
        $this->view->tls_fingerprint = $this->getForm("tls_fingerprint");
        $this->view->resolver = $this->getForm("resolver");
        $this->view->syslog_target = $this->getForm("syslog_target");
        $nginx = new Nginx();
        $this->view->show_naxsi_download_button =
            count(iterator_to_array($nginx->custom_policy->iterateItems())) == 0 &&
            count(iterator_to_array($nginx->naxsi_rule->iterateItems())) == 0;
        $this->view->pick('OPNsense/Nginx/index');
    }

    /**
     * show the nginx TLS handshakes page /ui/nginx/index/tls_handshakes
     */
    public function tlsHandshakesAction()
    {
        $this->view->pick('OPNsense/Nginx/tls_handshakes');
    }

    /**
     * display a viewer for banned IPs.
     */
    public function banAction()
    {
        $this->view->pick('OPNsense/Nginx/ban');
    }

    /**
     * display a viewer for traffic statistics.
     */
    public function vtsAction()
    {
        $this->view->pick('OPNsense/Nginx/vts');
    }
}
