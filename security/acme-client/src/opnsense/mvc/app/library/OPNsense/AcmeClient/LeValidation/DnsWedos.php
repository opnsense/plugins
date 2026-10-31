<?php

namespace OPNsense\AcmeClient\LeValidation;

use OPNsense\AcmeClient\LeValidationInterface;
use OPNsense\Core\Config;

/**
 * WEDOS DNS API
 * @package OPNsense\AcmeClient
 */
class DnsWedos extends Base implements LeValidationInterface
{
    public function prepare()
    {
        $this->acme_env['WEDOS_Username'] = (string)$this->config->dns_wedos_username;
        $this->acme_env['WEDOS_Wapipass'] = (string)$this->config->dns_wedos_wapipass;
    }
}