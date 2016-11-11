<?php

/*
    Copyright (C) 2016 Deciso B.V.
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

function if_tinc_syslog()
{
    $logfacilities = array();

    $logfacilities['tinc'] = array(
        'facility' => array('tinc'),
        'remote' => 'vpn',
    );

    return $logfacilities;
}

function if_tinc_services()
{
    global $config;

    $services = array();
    $is_enabled = false;
    if (isset($config['OPNsense']['proxy']['general']['enabled']) && $config['OPNsense']['proxy']['general']['enabled'] == 1) {
      if (isset($config['OPNsense']['Tinc']['networks']['network'])) {
          if (!isset($config['OPNsense']['Tinc']['networks']['network'][0])) {
              $networks = array($config['OPNsense']['Tinc']['networks']['network']);
          } else {
              $networks = $config['OPNsense']['Tinc']['networks']['network'];
          }
          foreach ($networks as $network) {
              if (!empty($network['enabled'])) {
                  $is_enabled = true;
                  break;
              }
          }
      }
    }

    if ($is_enabled) {
        $services[] = array(
            'description' => gettext('Tinc server'),
            'configd' => array(
                'restart' => array('tinc restart'),
                'start' => array('tinc start'),
                'stop' => array('tinc stop'),
            ),
            'name' => 'tincd',
        );
    }

    return $services;
}

function if_tinc_interfaces()
{
    global $config;

    $interfaces = array();

    if (isset($config['OPNsense']['Tinc']['networks']['network'])) {
        if (!isset($config['OPNsense']['Tinc']['networks']['network'][0])) {
            $networks = array($config['OPNsense']['Tinc']['networks']['network']);
        } else {
            $networks = $config['OPNsense']['Tinc']['networks']['network'];
        }
        foreach ($networks as $network) {
            if (!empty($network['enabled'])) {
                $oic = array('enable' => true);
                $oic['if'] = 'tinc';
                $oic['descr'] = 'TincVPN';
                $oic['type'] = 'none';
                $oic['virtual'] = true;
                $oic['networks'] = array();
                $interfaces['tinc'] = $oic;
                break;
            }
        }
    }

    return $interfaces;
}
