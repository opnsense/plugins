<?php

/*
 * Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Nginx\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Nginx\Nginx';
    protected static $internalModelName = 'nginx';
    protected static $internalModelUseSafeDelete = true;

    // download rules
    public function downloadrulesAction()
    {
        if (!$this->request->isPost()) {
            return array('error' => 'Must be called via POST');
        }
        $backend = new Backend();
        return array('result' => trim($backend->configdRun('nginx naxsidownloadrules')));
    }

    // User List

    public function searchuserlistAction()
    {
        return $this->searchBase('userlist', array('name', 'users'));
    }

    public function getuserlistAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('userlist', 'userlist', $uuid);
    }

    public function adduserlistAction()
    {
        return $this->addBase('userlist', 'userlist');
    }

    public function deluserlistAction($uuid)
    {
        return $this->delBase('userlist', $uuid);
    }

    public function setuserlistAction($uuid)
    {
        return $this->setBase('userlist', 'userlist', $uuid);
    }

    // Credential
    public function searchcredentialAction()
    {
        return $this->searchBase('credential', array('username'));
    }

    public function getcredentialAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('credential', 'credential', $uuid);
    }

    public function addcredentialAction()
    {
        return $this->addBase('credential', 'credential');
    }

    public function delcredentialAction($uuid)
    {
        return $this->delBase('credential', $uuid);
    }

    public function setcredentialAction($uuid)
    {
        return $this->setBase('credential', 'credential', $uuid);
    }

    // Upstream
    public function searchupstreamAction()
    {
        return $this->searchBase('upstream', array('uuid', 'description', 'serverentries', 'tls_enable', 'load_balancing_algorithm'));
    }

    public function getupstreamAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('upstream', 'upstream', $uuid);
    }

    public function addupstreamAction()
    {
        return $this->addBase('upstream', 'upstream');
    }

    public function delupstreamAction($uuid)
    {
        return $this->delBase('upstream', $uuid);
    }

    public function setupstreamAction($uuid)
    {
        return $this->setBase('upstream', 'upstream', $uuid);
    }

    // Upstream Server
    public function searchupstreamserverAction()
    {
        return $this->searchBase('upstream_server', array('uuid', 'description', 'server', 'port', 'priority'));
    }

    public function getupstreamserverAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('upstream_server', 'upstream_server', $uuid);
    }

    public function addupstreamserverAction()
    {
        return $this->addBase('upstream_server', 'upstream_server');
    }

    public function delupstreamserverAction($uuid)
    {
        return $this->delBase('upstream_server', $uuid);
    }

    public function setupstreamserverAction($uuid)
    {
        return $this->setBase('upstream_server', 'upstream_server', $uuid);
    }

    // Location
    public function searchlocationAction()
    {
        $data = $this->searchBase('location', array(
            'uuid', 'description', 'urlpattern', 'path_prefix', 'matchtype',
            'upstream', 'enable_secrules', 'enable_learning_mode', 'force_https',
            'xss_block_score', 'sqli_block_score', 'custom_policy'
        ));

        // Create "waf_status" column (enabled/disabled/learning)
        foreach ($data['rows'] as &$row) {
            if ($row['enable_secrules']) {
                if ($row['enable_learning_mode']) {
                    $row['waf_status'] = gettext('learning');
                } else {
                    $row['waf_status'] = gettext('enabled');
                }
            } else {
                $row['waf_status'] = gettext('disabled');
            }
        }

        return $data;
    }

    public function getlocationAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('location', 'location', $uuid);
    }

    public function addlocationAction()
    {
        return $this->addBase('location', 'location');
    }

    public function dellocationAction($uuid)
    {
        return $this->delBase('location', $uuid);
    }

    public function setlocationAction($uuid)
    {
        return $this->setBase('location', 'location', $uuid);
    }

    // Custom Policy
    public function searchcustompolicyAction()
    {
        return $this->searchBase('custom_policy', array('name', 'operator', 'value', 'action', 'naxsi_rules'));
    }

    public function getcustompolicyAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('custompolicy', 'custom_policy', $uuid);
    }

    public function addcustompolicyAction()
    {
        return $this->addBase('custompolicy', 'custom_policy');
    }

    public function delcustompolicyAction($uuid)
    {
        return $this->delBase('custom_policy', $uuid);
    }

    public function setcustompolicyAction($uuid)
    {
        return $this->setBase('custompolicy', 'custom_policy', $uuid);
    }

    // Resolver
    public function searchresolverAction()
    {
        return $this->searchBase('resolver', array('uuid', 'description', 'address', 'valid', 'timeout'));
    }

    public function getresolverAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('resolver', 'resolver', $uuid);
    }

    public function addresolverAction()
    {
        return $this->addBase('resolver', 'resolver');
    }

    public function delresolverAction($uuid)
    {
        return $this->delBase('resolver', $uuid);
    }

    public function setresolverAction($uuid)
    {
        return $this->setBase('resolver', 'resolver', $uuid);
    }

    // http server
    public function searchhttpserverAction()
    {
        return $this->searchBase('http_server', array(
            'uuid', 'servername', 'locations', 'root', 'https_only', 'certificate',
            'listen_http_address', 'listen_https_address', 'default_server'
        ));
    }

    public function gethttpserverAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('httpserver', 'http_server', $uuid);
    }

    public function addhttpserverAction()
    {
        return $this->addBase('httpserver', 'http_server');
    }

    public function delhttpserverAction($uuid)
    {
        return $this->delBase('http_server', $uuid);
    }

    public function sethttpserverAction($uuid)
    {
        return $this->setBase('httpserver', 'http_server', $uuid);
    }

    // stream server
    public function searchstreamserverAction()
    {
        return $this->searchBase('stream_server', array('uuid', 'description', 'certificate', 'udp', 'listen_address'));
    }

    public function getstreamserverAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('streamserver', 'stream_server', $uuid);
    }

    public function addstreamserverAction()
    {
        return $this->addBase('streamserver', 'stream_server');
    }

    public function delstreamserverAction($uuid)
    {
        return $this->delBase('stream_server', $uuid);
    }

    public function setstreamserverAction($uuid)
    {
        return $this->setBase('streamserver', 'stream_server', $uuid);
    }

    // naxsi rules
    public function searchnaxsiruleAction()
    {
        return $this->searchBase('naxsi_rule', array('description', 'identifier', 'ruletype', 'match_type', 'score', 'match_value', 'message'));
    }

    public function getnaxsiruleAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('naxsi_rule', 'naxsi_rule', $uuid);
    }

    public function addnaxsiruleAction()
    {
        return $this->addBase('naxsi_rule', 'naxsi_rule');
    }

    public function delnaxsiruleAction($uuid)
    {
        return $this->delBase('naxsi_rule', $uuid);
    }

    public function setnaxsiruleAction($uuid)
    {
        return $this->setBase('naxsi_rule', 'naxsi_rule', $uuid);
    }

    // http url rewriting
    public function searchhttprewriteAction()
    {
        return $this->searchBase('http_rewrite', array('description', 'source', 'destination', 'flag'));
    }

    public function gethttprewriteAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('httprewrite', 'http_rewrite', $uuid);
    }

    public function addhttprewriteAction()
    {
        return $this->addBase('httprewrite', 'http_rewrite');
    }

    public function delhttprewriteAction($uuid)
    {
        return $this->delBase('http_rewrite', $uuid);
    }

    public function sethttprewriteAction($uuid)
    {
        return $this->setBase('httprewrite', 'http_rewrite', $uuid);
    }

    // http security headers
    public function searchsecurityHeaderAction()
    {
        $data = $this->searchBase(
            'security_header',
            ['description', 'referrer', 'xssprotection', 'strict_transport_security_time',
            'enable_csp', 'csp_report_only', 'csp_default_src_enabled', 'csp_script_src_enabled', 'csp_img_src_enabled',
            'csp_style_src_enabled', 'csp_media_src_enabled', 'csp_font_src_enabled', 'csp_frame_src_enabled',
            'csp_frame_ancestors_enabled',
            'csp_form_action_enabled']
        );

        // Create "hsts" column (disabled/time)
        foreach ($data['rows'] as &$row) {
            if (intval($row['strict_transport_security_time']) > 0) {
                $row['hsts'] = sprintf(gettext("%d sec"), intval($row['strict_transport_security_time']));
            } else {
                $row['hsts'] = gettext('disabled');
            }
        }

        // Create "csp" column (enabled/report only/disabled)
        foreach ($data['rows'] as &$row) {
            if ($row['enable_csp']) {
                if ($row['csp_report_only']) {
                    $row['csp'] = gettext('report only');
                } else {
                    $row['csp'] = gettext('enabled');
                }
            } else {
                $row['csp'] = gettext('disabled');
            }
        }

        // Create "csp_details" column
        foreach ($data['rows'] as &$row) {
            if ($row['enable_csp']) {
                $enabled = [];
                if ($row['csp_default_src_enabled']) {
                    $enabled[] = gettext("Default Source");
                }
                if ($row['csp_script_src_enabled']) {
                    $enabled[] = gettext("Script Source");
                }
                if ($row['csp_img_src_enabled']) {
                    $enabled[] = gettext("Image Source");
                }
                if ($row['csp_style_src_enabled']) {
                    $enabled[] = gettext("Style Source");
                }
                if ($row['csp_media_src_enabled']) {
                    $enabled[] = gettext("Media Source");
                }
                if ($row['csp_font_src_enabled']) {
                    $enabled[] = gettext("Font Source");
                }
                if ($row['csp_frame_src_enabled']) {
                    $enabled[] = gettext("Frame Source");
                }
                if ($row['csp_frame_ancestors_enabled']) {
                    $enabled[] = gettext("Frame Ancestors");
                }
                if ($row['csp_form_action_enabled']) {
                    $enabled[] = gettext("Form Action");
                }

                if (count($enabled)) {
                    $row['csp_details'] = implode(', ', $enabled);
                } else {
                    $row['csp_details'] = gettext('none');
                }
            } else {
                $row['csp_details'] = '';
            }
        }

        return $data;
    }

    public function getsecurityHeaderAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('security_header', 'security_header', $uuid);
    }

    public function addsecurityHeaderAction()
    {
        return $this->addBase('security_header', 'security_header');
    }

    public function delsecurityHeaderAction($uuid)
    {
        return $this->delBase('security_header', $uuid);
    }

    public function setsecurityHeaderAction($uuid)
    {
        return $this->setBase('security_header', 'security_header', $uuid);
    }

    // access limit zone headers
    public function searchlimitZoneAction()
    {
        return $this->searchBase(
            'limit_zone',
            array('description', 'key', 'size', 'rate', 'rate_unit')
        );
    }

    public function getlimitZoneAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('limit_zone', 'limit_zone', $uuid);
    }

    public function addlimitZoneAction()
    {
        return $this->addBase('limit_zone', 'limit_zone');
    }

    public function dellimitZoneAction($uuid)
    {
        return $this->delBase('limit_zone', $uuid);
    }

    public function setlimitZoneAction($uuid)
    {
        return $this->setBase('limit_zone', 'limit_zone', $uuid);
    }

    // Error pages
    public function searcherrorpageAction()
    {
        return $this->searchBase('errorpage', array('name', 'statuscodes', 'response'));
    }

    public function geterrorpageAction($uuid = null)
    {
        $this->sessionClose();
        $data = $this->getBase('errorpage', 'errorpage', $uuid);
        // Decode base64 encoded page content
        $data['errorpage']['pagecontent'] = base64_decode($data['errorpage']['pagecontent']);
        return $data;
    }

    public function adderrorpageAction()
    {
        return $this->addBase('errorpage', 'errorpage', array(
            // Encode page content with base64
            'pagecontent' => base64_encode($this->request->getPost('errorpage')['pagecontent'])
        ));
    }

    public function delerrorpageAction($uuid)
    {
        return $this->delBase('errorpage', $uuid);
    }

    public function seterrorpageAction($uuid)
    {
        return $this->setBase('errorpage', 'errorpage', $uuid, array(
            // Encode page content with base64
            'pagecontent' => base64_encode($this->request->getPost('errorpage')['pagecontent'])
        ));
    }

    // TLS fingerprints for MITM detection
    public function searchtlsFingerprintAction()
    {
        return $this->searchBase('tls_fingerprint', array('description'));
    }

    public function gettlsFingerprintAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('tls_fingerprint', 'tls_fingerprint', $uuid);
    }

    public function addtlsFingerprintAction()
    {
        return $this->addBase('tls_fingerprint', 'tls_fingerprint');
    }

    public function deltlsFingerprintAction($uuid)
    {
        return $this->delBase('tls_fingerprint', $uuid);
    }

    public function settlsFingerprintAction($uuid)
    {
        return $this->setBase('tls_fingerprint', 'tls_fingerprint', $uuid);
    }

    // limit_request_connection
    public function searchlimitRequestConnectionAction()
    {
        return $this->searchBase(
            'limit_request_connection',
            array('description', 'limit_zone', 'nodelay', 'burst', 'connection_count')
        );
    }

    public function getlimitRequestConnectionAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('limit_request_connection', 'limit_request_connection', $uuid);
    }

    public function addlimitRequestConnectionAction()
    {
        return $this->addBase('limit_request_connection', 'limit_request_connection');
    }

    public function dellimitRequestConnectionAction($uuid)
    {
        return $this->delBase('limit_request_connection', $uuid);
    }

    public function setlimitRequestConnectionAction($uuid)
    {
        return $this->setBase('limit_request_connection', 'limit_request_connection', $uuid);
    }
    // cache path
    public function searchcachePathAction()
    {
        return $this->searchBase(
            'cache_path',
            array('path', 'inactive', 'size', 'max_size')
        );
    }

    public function getcachePathAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('cache_path', 'cache_path', $uuid);
    }

    public function addcachePathAction()
    {
        return $this->addBase('cache_path', 'cache_path');
    }

    public function delcachePathAction($uuid)
    {
        return $this->delBase('cache_path', $uuid);
    }

    public function setcachePathAction($uuid)
    {
        return $this->setBase('cache_path', 'cache_path', $uuid);
    }

    // SNI Forward
    public function searchsnifwdAction()
    {
        return $this->searchBase('sni_hostname_upstream_map', array('description'));
    }

    public function getsnifwdAction($uuid = null)
    {
        $this->sessionClose();
        $base = $this->getBase('snihostname', 'sni_hostname_upstream_map', $uuid);
        return $this->convert_sni_fwd_for_client($base);
    }

    public function addsnifwdAction()
    {
        if ($this->request->isPost()) {
            $this->regenerate_hostname_map(null);
            return $this->addBase('snihostname', 'sni_hostname_upstream_map');
        }
        return [];
    }

    public function delsnifwdAction($uuid)
    {
        $nginx = $this->getModel();
        $uuid_attached = $nginx->find_sni_hostname_upstream_map_entry_uuids($uuid);

        $ret = $this->delBase('sni_hostname_upstream_map', $uuid);
        if ($ret['result'] == 'deleted') {
            foreach ($uuid_attached as $old_uuid) {
                $this->delBase('sni_hostname_upstream_map_item', $old_uuid);
            }
        }
        return $ret;
    }

    public function setsnifwdAction($uuid)
    {
        if ($this->request->isPost()) {
            $this->regenerate_hostname_map($uuid);
            return $this->setBase('snihostname', 'sni_hostname_upstream_map', $uuid);
        }
        return [];
    }

    // IP / Network based ACLs
    public function searchipaclAction()
    {
        return $this->searchBase('ip_acl', array('description'));
    }

    public function getipaclAction($uuid = null)
    {
        $this->sessionClose();
        $base = $this->getBase('ipacl', 'ip_acl', $uuid);
        return $this->convert_ipacl_for_client($base);
    }

    public function addipaclAction()
    {
        if ($this->request->isPost()) {
            $this->regenerate_ipacl(null);
            return $this->addBase('ipacl', 'ip_acl');
        }
        return [];
    }

    public function delipaclAction($uuid)
    {
        $nginx = $this->getModel();
        $uuid_attached = $nginx->find_ip_acl_uuids($uuid);

        $ret = $this->delBase('ip_acl', $uuid);
        if ($ret['result'] == 'deleted') {
            foreach ($uuid_attached as $old_uuid) {
                $this->delBase('ip_acl_item', $old_uuid);
            }
        }
        return $ret;
    }

    public function setipaclAction($uuid)
    {
        if ($this->request->isPost()) {
            $this->regenerate_ipacl($uuid);
            return $this->setBase('ipacl', 'ip_acl', $uuid);
        }
        return [];
    }
    /*
     * worker code starts here
     */

    private function convert_sni_fwd_for_client($response_data)
    {
        if (!isset($response_data['snihostname']['data'])) {
            return $response_data;
        }
        $nginx = $this->getModel();
        $uuids_map = explode(',', $response_data['snihostname']['data']);
        $response_data['snihostname']['data'] = [];
        foreach ($uuids_map as $uuid_line) {
            $rowdata = $nginx->getNodeByReference('sni_hostname_upstream_map_item.' . $uuid_line);
            if ($rowdata != null) {
                $response_data['snihostname']['data'][] =
                    array('hostname' => (string)$rowdata->hostname,
                        'upstream' => (string)$rowdata->upstream);
            }
        }
        return $response_data;
    }
    private function convert_ipacl_for_client($response_data)
    {
        if (!isset($response_data['ipacl']['data'])) {
            return $response_data;
        }
        $nginx = $this->getModel();
        $uuids_map = explode(',', $response_data['ipacl']['data']);
        $response_data['ipacl']['data'] = [];
        foreach ($uuids_map as $uuid_line) {
            $rowdata = $nginx->getNodeByReference('ip_acl_item.' . $uuid_line);
            if ($rowdata != null) {
                $response_data['ipacl']['data'][] =
                    array('network' => (string)$rowdata->network,
                        'action' => (string)$rowdata->action);
            }
        }
        return $response_data;
    }

    /**
     * @param null $uuid the uuid which should get cleared before
     * @throws \ReflectionException if the model was not found
     */
    private function regenerate_hostname_map($uuid = null)
    {
        $nginx = $this->getModel();
        if ($this->request->hasPost('snihostname') && is_array($_POST['snihostname']['data'])) {
            if ($uuid != null) {
                // for an update, we have to clear it.
                $this->delete_uuids(
                    $nginx->find_sni_hostname_upstream_map_entry_uuids($uuid),
                    'sni_hostname_upstream_map_item'
                );
            }
            $ids = [];
            $postdata = $_POST['snihostname']['data'];
            foreach ($postdata as $post_item) {
                $item = $nginx->sni_hostname_upstream_map_item->Add();
                $ids[] = $item->getAttributes()['uuid'];
                $item->hostname = $post_item['hostname'];
                $item->upstream = $post_item['upstream'];
            }
            $nginx->serializeToConfig();
            $_POST['snihostname']['data'] = implode(',', $ids);
        }
    }

    /**
     * @param null $uuid the uuid which should get cleared before
     * @throws \ReflectionException if the model was not found
     */
    private function regenerate_ipacl($uuid = null)
    {
        $nginx = $this->getModel();
        if ($this->request->hasPost('ipacl') && is_array($_POST['ipacl']['data'])) {
            if ($uuid != null) {
                // for an update, we have to clear it.
                $this->delete_uuids(
                    $nginx->find_ip_acl_uuids($uuid),
                    'ip_acl_item'
                );
            }
            $ids = [];
            $postdata = $_POST['ipacl']['data'];
            foreach ($postdata as $post_item) {
                $item = $nginx->ip_acl_item->Add();
                $ids[] = $item->getAttributes()['uuid'];
                $item->network = $post_item['network'];
                $item->action = $post_item['action'];
            }
            $nginx->serializeToConfig();
            $_POST['ipacl']['data'] = implode(',', $ids);
        }
    }

    /**
     * @param $uuids array list of UUIDs
     * @param $path string the model prefix from the element to delete
     */
    private function delete_uuids($uuids, $path): void
    {
        foreach ($uuids as $item_uuid) {
            try {
                $this->delBase($path, $item_uuid);
            } catch (\Exception $e) {
                // we don't care about then.
            }
        }
    }
    // SYSLOG targets
    public function searchsyslogTargetAction()
    {
        return $this->searchBase('syslog_target', array('description', 'host', 'facility', 'severity'));
    }

    public function getsyslogTargetAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('syslog_target', 'syslog_target', $uuid);
    }

    public function addsyslogTargetAction()
    {
        return $this->addBase('syslog_target', 'syslog_target');
    }

    public function delsyslogTargetAction($uuid)
    {
        return $this->delBase('syslog_target', $uuid);
    }

    public function setsyslogTargetAction($uuid)
    {
        return $this->setBase('syslog_target', 'syslog_target', $uuid);
    }

    public function showconfigAction()
    {
        $backend = new Backend();
        $response = json_decode($backend->configdRun("nginx show_config"), true);
        return $response;
    }

    public function testconfigAction()
    {
        $backend = new Backend();
        $response = trim($backend->configdRun("nginx test_config"));
        return array("response" => $response);
    }
}
