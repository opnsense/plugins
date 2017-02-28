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
use \OPNsense\Core\Backend;
use \OPNsense\Monit\Monit;


/**
 * Class ServiceController
 * @package OPNsense\Monit
 */
class ServiceController extends ApiControllerBase
{
	/**
	 * test monit configuration
	 * @return array
	 */
	public function configtestAction()
	{
		if ($this->request->isPost()) {
			$this->sessionClose();
		}
		$result['function'] = "configtest";

		$result['template'] = $this->callBackend('template');
		if ($result['template'] != 'OK')
		{
			$result['result'] = "Template error: " . $result['template'];
			return $result;
		}

		$result['result'] = $this->callBackend('configtest');
		return $result;
	}

	/**
	 * reload monit with new configuration
	 * @return array
	 */
	public function reloadAction()
	{
		if ($this->request->isPost()) {
			$this->sessionClose();
		}
		$result['function'] = "reload";

		$result['template'] = $this->callBackend('template');
		if ($result['template'] != 'OK')
		{
			$result['result'] = "Template error: " . $result['template'];
			return $result;
		}

		$status = $this->callBackend('status');
		if (substr($status, 0, 16) != 'monit is running') {
			$result['result'] = "Monit is not running";
			return $result;
		}

		$result['result'] = $this->callBackend('reload');
		return $result;
	}

	/**
	 * get status of monit process
	 * @return array
	 */
	public function statusAction()
	{
		if ($this->request->isPost()) {
			$this->sessionClose();
		}
		$result['function'] = "status";
		$result['result'] = "failed";
		$result['status'] = 'stopped';
		$status = $this->callBackend('status');
		if (substr($status, 0, 16) == 'monit is running') {
			$result['result'] = "ok";
			$result['status'] = 'running';
		} else {
			$result['error'] = $status;
		}
		return $result;
	}

	/**
	 * start monit service
	 * @return array
	 */
	public function startAction()
	{
		$result = array("result" => "failed", "function" => "start");
		if ($this->request->isPost()) {
			$this->sessionClose();
			$result['result'] = $this->callBackend('start');
		}
		return $result;
	}

	/**
	 * stop monit service
	 * @return array
	 */
	public function stopAction()
	{
		$result = array("result" => "failed", "function" => "stop");
		if ($this->request->isPost()) {
			$this->sessionClose();
			$result['result'] = $this->callBackend('stop');
		}
		return $result;
	}

	/**
	 * restart monit service
	 * @return array
	 */
	public function restartAction()
	{
		$result = array("result" => "failed", "function" => "restart");
		if ($this->request->isPost()) {
			$this->sessionClose();
			$result['result'] = $this->callBackend('restart');
		}
		return $result;
	}

	/**
	 * call backend functions
	 * @param action
	 * @return string
	 */
	protected function callBackend($action)
	{
		$backend = new Backend();
		if ($action == 'template') {
			return trim($backend->configdRun('template reload OPNsense/Monit'));
		} else {
			return trim($backend->configdRun('monit ' . $action));
		}
	}
}
