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

namespace OPNsense\FtpProxy;

use OPNsense\Base\BaseModel;

/**
 * Class FtpProxy
 * @package OPNsense\FtpProxy
 */
class FtpProxy extends BaseModel
{
	/**
	 * map config to ftp-proxy flags
	 * and set default values
	 * @param $node configuration
	 * @return string
	 */
	public function configToFlags($node)
	{
		$flags  = ' -b ' . $node->listenaddress->__toString();
		$flags .= ' -p ' . $node->listenport->__toString();
		if ($node->sourceaddress->__toString() != "") {
			$flags .= ' -a ' . $node->sourceaddress->__toString();
		}
		if ($node->rewritesourceport->__toString() == 1) {
			$flags .= ' -r ';
		}
		if ($node->idletimeout->__toString() == "") {
			$node->__set('idletimeout', 86400);
		}
		if ($node->idletimeout->__toString() != 86400) {
			$flags .= ' -t ' . $node->idletimeout->__toString();
		}
		if ($node->maxsessions->__toString() == "") {
			$node->__set('maxsessions', 100);
		}
		if ($node->maxsessions->__toString() != 100) {
			$flags .= ' -m ' . $node->maxsessions->__toString();
		}
		if ($node->reverseaddress->__toString() != "") {
			$flags .= ' -R ' . $node->reverseaddress->__toString();
		}
		if ($node->reverseport->__toString() == "") {
			$node->__set('reverseport', 21);
		}
		if ($node->reverseport->__toString() != 21) {
			$flags .= ' -P ' . $node->reverseport->__toString();
		}
		if ($node->logconnections->__toString() == 1) {
			$flags .= ' -v ';
		}
		if ($node->debuglevel->__toString() == "") {
			$node->__set('debuglevel', 5);
		}
		if ($node->debuglevel->__toString() != 5) {
			$flags .= ' -D ' . $node->debuglevel->__toString();
		}
		return $flags;
	}
}
