<?php

/**
 *    Copyright (C) 2016 EURO-LOG AG
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

namespace OPNsense\FtpProxy\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;
use \OPNsense\FtpProxy\FtpProxy;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\FtpProxy
 */
class SettingsController extends ApiControllerBase
{
	/**
	 * retrieve ftpproxy settings or return defaults
	 * @param $uuid item unique id
	 * @return array
	 */
	public function getProxyAction($uuid = null)
	{
		$mdlFtpProxy = new FtpProxy();
		if ($uuid != null) {
			$node = $mdlFtpProxy->getNodeByReference('ftpproxies.ftpproxy.' . $uuid);
			if ($node != null) {
				// return node
				return array("ftpproxy" => $node->getNodes());
			}
		} else {
			// generate new node, but don't save to disc
			$node = $mdlFtpProxy->ftpproxies->ftpproxy->Add();
			return array("ftpproxy" => $node->getNodes());
		}
		return array();
	}

	/**
	 * update ftpproxy with given properties
	 * @param $uuid item unique id
	 * @return array
	 */
	public function setProxyAction($uuid)
	{
		if ($this->request->isPost() && $this->request->hasPost("ftpproxy")) {
			$mdlFtpProxy = new FtpProxy();
			// keep a list to detect duplicates later
			$CurrentProxies =  $mdlFtpProxy->getNodes();
			if ($uuid != null) {
				$node = $mdlFtpProxy->getNodeByReference('ftpproxies.ftpproxy.' . $uuid);
				if ($node != null) {
					$Enabled = $node->enabled->__toString();
					// get current ftp-proxy flags for stopping it later
					$OldFlags = $mdlFtpProxy->configToFlags($node);
					$result = array("result" => "failed", "validations" => array());
					$proxyInfo = $this->request->getPost("ftpproxy");
					
					$node->setNodes($proxyInfo);
					$valMsgs = $mdlFtpProxy->performValidation();
					foreach ($valMsgs as $field => $msg) {
						$fieldnm = str_replace($node->__reference, "ftpproxy", $msg->getField());
						$result["validations"][$fieldnm] = $msg->getMessage();
					}

					if (count($result['validations']) == 0) {
						// check for duplicates
						foreach ($CurrentProxies['ftpproxies']['ftpproxy'] as $CurrentUUID => &$CurrentProxy) {
							if ($node->listenaddress->__toString() == $CurrentProxy['listenaddress'] && 
								$node->listenport->__toString() == $CurrentProxy['listenport'] &&
								$uuid != $CurrentUUID) {
								return array(
										  "result" => "failed", 
										  "validations" => array(
										     "ftpproxy.listenaddress" => "Listen address in combination with Listen port already exists.",
										     "ftpproxy.listenport" => "Listen port in combination with Listen address already exists."
								          )
									   );
							}
						}
						// retrieve ftp-proxy flags and set defaults
				        $NewFlags = $mdlFtpProxy->configToFlags($node);
				        // save config if validated correctly
						$mdlFtpProxy->serializeToConfig();
						Config::getInstance()->save();
						
						$backend = new Backend();
						// apply new settings to the ftp-proxy process
						// stop ftp-proxy with old flags
						if ($Enabled == 1) {
							$backend->configdpRun('ftpproxy stop ', array($OldFlags));
						}
						$node = $mdlFtpProxy->getNodeByReference('ftpproxies.ftpproxy.' . $uuid);
						// start ftp-proxy with new flags
						if ($node != null && $node->enabled->__toString() == 1) {
							$backend->configdpRun('ftpproxy start ', array($NewFlags));
						}
						// make the changes boot resistant in /etc/rc.conf.d/ftpproxy
						$backend->configdRun("template reload OPNsense.FtpProxy");
						$result = array("result" => "saved");
					}
					return $result;
				}
			}
		}
		return array("result" => "failed");
	}

	/**
	 * add new ftpproxy and set with attributes from post
	 * @return array
	 */
	public function addProxyAction()
	{
		$result = array("result" => "failed");
		if ($this->request->isPost() && $this->request->hasPost("ftpproxy")) {
			$result = array("result" => "failed", "validations" => array());
			$mdlFtpProxy = new FtpProxy();
			// keep a list to detect duplicates later
			$CurrentProxies =  $mdlFtpProxy->getNodes();
			$node = $mdlFtpProxy->ftpproxies->ftpproxy->Add();
			$node->setNodes($this->request->getPost("ftpproxy"));
			
			$valMsgs = $mdlFtpProxy->performValidation();

			foreach ($valMsgs as $field => $msg) {
				$fieldnm = str_replace($node->__reference, "ftpproxy", $msg->getField());
				$result["validations"][$fieldnm] = $msg->getMessage();
			}
			
			if (count($result['validations']) == 0) {
				foreach ($CurrentProxies['ftpproxies']['ftpproxy'] as &$CurrentProxy) {
					if ($node->listenaddress->__toString() == $CurrentProxy['listenaddress'] 
							&& $node->listenport->__toString() == $CurrentProxy['listenport']) {
						return array(
								  "result" => "failed",
								  "validations" => array(
								     "ftpproxy.listenaddress" => "Listen address in combination with Listen port already exists.",
								     "ftpproxy.listenport" => "Listen port in combination with Listen address already exists."
								   )
  						       );
					}
				}
				// retrieve ftp-proxy flags and set defaults
				$Flags = $mdlFtpProxy->configToFlags($node);
				// save config if validated correctly
				$mdlFtpProxy->serializeToConfig();
				Config::getInstance()->save();
				if ($node->enabled->__toString() == 1) {
					$backend = new Backend();
					$backend->configdpRun('ftpproxy start ', array($Flags));
					// add it to /etc/rc.conf.d/ftpproxy
					$backend->configdRun("template reload OPNsense.FtpProxy");
				}
				$result = array("result" => "saved");
			}
			return $result;
		}
		return $result;
	}

	/**
	 * delete ftpproxy by uuid
	 * @param $uuid item unique id
	 * @return array status
	 */
	public function delProxyAction($uuid)
	{

		$result = array("result" => "failed");
		if ($this->request->isPost()) {
			$mdlFtpProxy = new FtpProxy();
			if ($uuid != null) {
				$node = $mdlFtpProxy->getNodeByReference('ftpproxies.ftpproxy.' . $uuid);
				if ($node != null) {
					$backend = new Backend();
					// stop if the ftp-proxy is running
					if ($node->enabled->__toString() == 1) {
						$backend->configdpRun('ftpproxy stop ', array($mdlFtpProxy->configToFlags($node)));
					}
					if ($mdlFtpProxy->ftpproxies->ftpproxy->del($uuid) == true) {
						// if item is removed, serialize to config and save
						$mdlFtpProxy->serializeToConfig();
						Config::getInstance()->save();
						$result['result'] = 'deleted';
						// remove it from /etc/rc.conf.d/ftpproxy
						$backend->configdRun("template reload OPNsense.FtpProxy");
					}
				} else {
					$result['result'] = 'not found';
				}
			}
		}
		return $result;
	}

	/**
	 * toggle ftpproxy by uuid (enable/disable)
	 * @param $uuid item unique id
	 * @return array status
	 */
	public function toggleProxyAction($uuid)
	{

		$result = array("result" => "failed");

		if ($this->request->isPost()) {
			$mdlFtpProxy = new FtpProxy();
			if ($uuid != null) {
				$node = $mdlFtpProxy->getNodeByReference('ftpproxies.ftpproxy.' . $uuid);
				if ($node != null) {
					$backend = new Backend();
					if ($node->enabled->__toString() == "1") {
						$result['result'] = "Disabled";
						$node->enabled = "0";
						$response = $backend->configdpRun('ftpproxy stop ', array($mdlFtpProxy->configToFlags($node)));
					} else {
						$result['result'] = "Enabled";
						$node->enabled = "1";
						$response = $backend->configdpRun('ftpproxy start ', array($mdlFtpProxy->configToFlags($node)));
					}
					
					// if item has toggled, serialize to config and save
					$mdlFtpProxy->serializeToConfig();
					Config::getInstance()->save();
					$backend->configdRun("template reload OPNsense.FtpProxy");
				}
			}
		}
		return $result;
	}

	/**
	 *
	 * search ftpproxy
	 * @return array
	 */
	public function searchProxyAction()
	{
		$this->sessionClose();
		$fields = array(
				"enabled",
				"listenaddress",
				"listenport",
				"sourceaddress",
				"rewritesourceport",
				"idletimeout",
				"maxsessions",
				"reverseaddress",
				"reverseport",
				"logconnections",
				"debuglevel",
				"description"
		);
		$mdlFtpProxy = new FtpProxy();
		
		$grid = new UIModelGrid($mdlFtpProxy->ftpproxies->ftpproxy);
		$response = $grid->fetchBindRequest(
				$this->request,
				$fields,
				"listenport"
				);
		
		$backend = new Backend();
		foreach($response['rows'] as &$row) {
			$node = $mdlFtpProxy->getNodeByReference('ftpproxies.ftpproxy.' . $row['uuid']);
			$status = trim($backend->configdpRun('ftpproxy status ', array($mdlFtpProxy->configToFlags($node))));
			if ($status == 'OK') {
				$row['status'] = 0;
				continue;
			}
			$row['status'] = 2;
		}
			
		return $response;
	}
}
