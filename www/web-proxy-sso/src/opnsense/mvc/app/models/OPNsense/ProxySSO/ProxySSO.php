<?php

namespace OPNsense\ProxySSO;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

class ProxySSO extends BaseModel
{
	protected function init()
	{
		if($this->KerberosHostName == "") {
			$hostname = (string)Config::getInstance()->object()->system->hostname;
			$this->KerberosHostName = substr(strtoupper($hostname), 0, 13) . '-K';
		}
	}
}
