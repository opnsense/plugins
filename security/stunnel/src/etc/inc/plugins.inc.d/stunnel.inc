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

function stunnel_configure()
{
    return array(
       'crl' => array('stunnel_refresh_crls')
    );
}

function stunnel_services()
{
    $services = array();
    $mdl = new \OPNsense\Stunnel\Stunnel();
    if ($mdl->general->enabled == '1') {
        $services[] = array(
            'description' => gettext('Stunnel'),
            'stunnel' => array(
                'restart' => array('stunnel restart'),
                'start' => array('stunnel start'),
                'stop' => array('stunnel stop'),
            ),
            'name' => 'stunnel',
            'pidfile' => '/var/run/stunnel/stunnel.pid',
        );
        if ($mdl->general->enable_ident_server == '1') {
            // only report status from identd seperately, control is combined with stunnel
            $services[] = array(
                'description' => gettext('Identd (stunnel)'),
                'identd_stunnel' => array(
                    'restart' => array('stunnel restart'),
                    'start' => array('stunnel start'),
                    'stop' => array('stunnel stop'),
                ),
                'name' => 'identd_stunnel',
                'pidfile' => '/var/run/identd_stunnel.pid',
            );
        }
    }
    return $services;
}


function stunnel_refresh_crls()
{
    $stunnel = new OPNsense\Stunnel\Stunnel();
    $configObj = OPNsense\Core\Config::getInstance()->object();
    foreach ($stunnel->services->service->iterateItems() as $service) {
        if (!empty((string)$service->enabled) && !empty((string)$service->enableCRL)) {
            foreach (explode(",", (string)$service->cacert) as $cacert) {
                $this_ca = null;
                if (!empty($configObj->ca)) {
                    foreach ($configObj->ca as $ca) {
                        if ((string)$ca->refid == $cacert && !empty((string)$ca->prv)) {
                            $this_ca = $ca;
                        }
                    }
                }
                if ($this_ca) {
                    $ca_hash = null;
                    $ca_crt = base64_decode((string)$this_ca->crt);
                    $ca_key = base64_decode((string)$this_ca->prv);
                    $process = proc_open("openssl x509 -hash -noout", [["pipe", "r"], ["pipe", "w"]], $pipes);
                    if (is_resource($process)) {
                        fwrite($pipes[0], $ca_crt);
                        fclose($pipes[0]);
                        $ca_hash = trim(stream_get_contents($pipes[1]));
                        fclose($pipes[1]);
                        proc_close($process);
                    }
                    if ($ca_hash) {
                        $crlres = openssl_crl_new($ca_crt, 0, 9999);
                        if (!empty($configObj->crl)) {
                            foreach ($configObj->crl as $crl) {
                                if ($crl->caref == $cacert && !empty((string)$crl->cert)) {
                                    foreach ($crl->cert as $cert) {
                                        openssl_crl_revoke_cert(
                                            $crlres,
                                            base64_decode((string)$cert->crt),
                                            (string)$cert->revoke_time,
                                            (string)$cert->reason
                                        );
                                    }
                                }
                            }
                        }
                        $crl_text = "";
                        openssl_crl_export($crlres, $crl_text, $ca_key);
                        file_put_contents("/var/run/stunnel/certs/{$ca_hash}.r0", $crl_text);
                    }
                }
            }
        }
    }
}

function stunnel_syslog()
{
    $logfacilities = array();
    $logfacilities['stunnel'] = array(
        'facility' => array('stunnel', 'identd_stunnel')
    );
    return $logfacilities;
}

function stunnel_xmlrpc_sync()
{
    $result = array();
    $result[] = array(
        'description' => gettext('Stunnel'),
        'section' => 'OPNsense.Stunnel',
        'id' => 'stunnel',
        'services' => ['stunnel', 'identd_stunnel'],
    );
    return $result;
}
