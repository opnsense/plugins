<?php
/* SPDX-License-Identifier: BSD-2-Clause */
namespace OPNsense\Newt\Api;

class ServiceController extends \OPNsense\Base\ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Newt\Newt';
    protected static $internalServiceTemplate = 'OPNsense/Newt';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'newt';
}
