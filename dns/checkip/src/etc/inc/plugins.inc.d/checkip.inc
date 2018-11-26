<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

function checkip_enabled()
{
    // The class exists?
    if (class_exists('OPNsense\DynDNS\CheckIP')) {
        return true;
    }

    return false;
}

function checkip_services()
{
    $mdlCheckIPService = new OPNsense\DynDNS\CheckIP();

    // Reveal the enabled default service name.
    foreach (checkip_services_array() as $service => $node) {
        if ($node['default']) {
            $defaultsrvcname = $node['name'];
            $defaultsrvcname .= ($service == 'FDS') ? ' (FDS)' : '';
            break;
        }
    }

    $defaultsrvcname = ($defaultsrvcname) ?: sprintf('<span style="color:red; background-color:yellow">%s</span>', gettext('no default service set'));

    // Enabled and at least one service exists or the factory default service is enabled.
    $srvc_exists = (
        checkip_enabled() &&
        (
         is_array($mdlCheckIPService->service) ||
         $mdlCheckIPService->factory_default_service->default
        )
    ) ? true : false;

    $services[] = array(
        'description' => sprintf(gettext('Check IP Services (%s)'), $defaultsrvcname),
        'nocheck' => $srvc_exists,
        'name' => 'checkip',
    );

    return $services;
}

function checkip_services_array()
{
    $mdlCheckIPService = new OPNsense\DynDNS\CheckIP();

    // Get the check IP services.
    $checkipservices = $mdlCheckIPService->service->getNodes();

    // If this was initiated from either the DynDNS or RFC2136 plugin UI there may have been a write_config() call.
    // Reload config and try again.
    if (
        empty($checkipservices)  ||
        !is_array($checkipservices)
       ) {
        OPNsense\Core\Config::getInstance()->forceReload();
        $mdlCheckIPService = new OPNsense\DynDNS\CheckIP();
        $checkipservices = $mdlCheckIPService->service->getNodes();
    }

    // Include the factory default check IP service.
    $checkipservices['FDS'] = $mdlCheckIPService->factory_default_service->getNodes();

    return $checkipservices;
}

function checkip_service_use($checkipservices, $servicename = '', $int = 'wan', $ipver = 4)
{
    // Use the service specified by name.
    // Else use the first service found with a matching interface and IP version assignment.
    // Else use the enabled default service.
    $family = ($ipver == 4 ? 'all' : ($ipver == 6 ? 'inet6' : 'all'));
    foreach ($checkipservices as $i => $checkipservice) {

        // Look for a check IP service interface that matches up with the "real" interface.
        foreach (array_keys($checkipservice['interfaces']) as $checkip_service_interface) {
            if (
                $checkipservice['interfaces'][$checkip_service_interface]['selected'] &&
                (
                 $int == get_real_interface($checkip_service_interface, $family) ||
                 $int == $checkip_service_interface    // For RFC2136 plugin; not using the "real" interface.
                )
               ) {
                break;
            }
            $checkip_service_interface = '';
        }

        if ($checkipservice['name'] == $servicename) {
            return $checkipservice;
        }

        if (
            empty($servicename) &&
            $checkipservice['interfaces'][$checkip_service_interface]['selected'] &&
            $checkipservice['ipver']['ipver'.$ipver]['selected']
           ) {
            return $checkipservice;
        }

        if ($checkipservice['default']) {
            $default_checkipservice = $checkipservice;
        }
    }

    return $default_checkipservice;
}

function checkip_service($servicename = '', $int = 'wan', $ipver = 4)
{
    if (!checkip_enabled()) {
        $status = gettext('Check IP services not available.');
    } elseif (
        !is_array($checkipservices = checkip_services_array()) ||
        empty($checkipservices)
       ) {
        $status = gettext('No Check IP services defined.');
    } elseif (
        !is_array($checkipservice = checkip_service_use($checkipservices, $servicename, $int, $ipver)) ||
        empty($checkipservice)
       ) {
        $status = sprintf(gettext('Check IP service %s not found and no default service is enabled.'), $servicename);
    }

    if ($status) {
        log_error('CheckIP Plugin: Service Name: ' . $servicename . ', Interface: ' . $int . ', IP Version: ' . $ipver . ', Status: ' . $status);
        return $status;
    }

    // Get the service parameters.
       $hosttocheck = $checkipservice['url'];
     $verifysslpeer = $checkipservice['verifysslpeer'];
     $capture_regex = $checkipservice['capture_regex'];
          $username = $checkipservice['username'];
          $password = $checkipservice['password'];

    foreach ($checkipservice['authtype'] as $authtype => $property) {
        if ($property['selected']) {
            break;
        }
        $authtype = '';
    }

    // Set and return the service parameters.
    $curl_options[CURLOPT_URL] = $hosttocheck;
    $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_NONE;
    $curl_options[CURLOPT_SSL_VERIFYPEER] = (bool) $verifysslpeer;

    // Set auth type and enforce secure connection when sending authentication credentials.
    if (
        !empty($username) ||
        !empty($password)
    ) {
        switch ($authtype) {
            case 'basic':
                $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                break;
            case 'digest':
                $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;
                break;
            case 'gssnegotiate':
                $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_GSSNEGOTIATE;
                break;
            case 'ntlm':
                $curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_NTLM;
                break;
        }
        $curl_options[CURLOPT_SSL_VERIFYPEER] = true;
        $curl_options[CURLOPT_USERPWD] = "{$username}:{$password}";
    }

    return array('curl_options' => $curl_options, 'capture_regex' => $capture_regex);
}

function checkip_service_test($servicename = '', $interface = 'wan', $ipver = 4)
{
    global $config;

    $servicename = ($servicename == 'auto_select') ? '' : $servicename;

    $family = ($ipver == 4 ? 'all' : ($ipver == 6 ? 'inet6' : 'all'));
    $real_if = get_real_interface($interface, $family);

    $if_descr = $config['interfaces'][$interface]['descr'];

    $result = array(
      'ServiceName' => ($servicename ?: gettext('selected by interface and IP version, or the enabled default service')), 
        'Interface' => $if_descr . ' (' . $interface . ', ' . $real_if . ')', 
        'IPVersion' => $ipver, 
        'IPAddress' => checkip_service_get_dyndns_ip($real_if, $ipver, $servicename)
    );

    return $result;
}

function checkip_service_get_dyndns_ip($int, $ipver = 4, $servicename = '')
{
    $checkip = checkip_service($servicename, $int, $ipver);
    if (!is_array($checkip['curl_options'])) {
        return $checkip;
    }

    return get_dyndns_ip($int, $ipver, $checkip);
}
