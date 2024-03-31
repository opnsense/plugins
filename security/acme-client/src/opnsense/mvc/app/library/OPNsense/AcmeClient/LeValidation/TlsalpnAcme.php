<?php

/*
 * Copyright (C) 2021 Frank Wall
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

namespace OPNsense\AcmeClient\LeValidation;

use OPNsense\AcmeClient\LeValidationInterface;
use OPNsense\AcmeClient\LeUtils;
use OPNsense\Core\Config;

/**
 * Use acme.sh TLS web server for TLS-ALPN-01 validation
 * @package OPNsense\AcmeClient
 */
class TlsalpnAcme extends Base implements LeValidationInterface
{
    public function prepare()
    {
        $configdir = (string)sprintf(self::ACME_CONFIG_DIR, $this->cert_id);

        // Get configured TLS port for acme.sh web server.
        $configObj = Config::getInstance()->object();
        $local_tls_port = $configObj->OPNsense->AcmeClient->settings->TLSchallengePort;
        $this->acme_args[] = LeUtils::execSafe('--tlsport %s', (string)$local_tls_port);

        // Collect all IP addresses here, automatic port forward will be applied for each IP
        $iplist = array();

        // Add IP addresses from auto-discovery feature
        if ($this->config->tlsalpn_acme_autodiscovery == '1') {
            $dnslist = explode(',', $this->cert_altnames);
            $dnslist[] = $this->cert_name;
            foreach ($dnslist as $fqdn) {
                // NOTE: This may take some time.
                $ip_found = gethostbyname("${fqdn}.");
                if (!empty($ip_found)) {
                    $iplist[] = (string)$ip_found;
                }
            }
        }

        // Add IP addresses from user input
        $additional_ip = (string)$this->config->tlsalpn_acme_ipaddresses;
        if (!empty($additional_ip)) {
            foreach (explode(',', $additional_ip) as $ip) {
                $iplist[] = $ip;
            }
        }

        // Add IP address from chosen interface
        if (!empty((string)$this->config->tlsalpn_acme_interface)) {
            $backend = new \OPNsense\Core\Backend();
            $response = json_decode($backend->configdpRun('interface address', [(string)$this->config->tlsalpn_acme_interface]));
            if (!empty($response->address)) {
                $iplist[] = $response->address;
            }
        }

        // Check if IPv6 support is enabled
        if (isset($configObj->system->ipv6allow) && ($configObj->system->ipv6allow == '1')) {
            $_ipv6_enabled = true;
        } else {
            $_ipv6_enabled = false;
        }

        // Generate rules for all IP addresses
        $anchor_rules = "";
        if (!empty($iplist)) {
            $dedup_iplist = array_unique($iplist);
            // Add one rule for every IP
            foreach ($dedup_iplist as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    // IPv4
                    $_dst = '127.0.0.1';
                    $_family = 'inet';
                    LeUtils::log("using IPv4 address: ${ip}");
                } elseif (($_ipv6_enabled == true) && (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) {
                    // IPv6
                    $_dst = '::1';
                    $_family = 'inet6';
                    LeUtils::log("using IPv6 address: ${ip}");
                } else {
                    continue; // skip broken entries
                }
                $anchor_rules .= "rdr pass ${_family} proto tcp from any to ${ip} port 443 -> ${_dst} port ${local_tls_port}\n";
            }
        } else {
            LeUtils::log_error("no IP addresses found to setup port forward");
            return false;
        }

        // Abort if no rules were generated
        if (empty($anchor_rules)) {
            LeUtils::log_error("unable to setup a port forward (empty ruleset)");
            return false;
        }

        // Create temporary port forward to allow acme challenges to get through
        $anchor_setup = "rdr-anchor \"acme-client\"\n";
        file_put_contents("${configdir}/acme_anchor_setup", $anchor_setup);
        chmod("${configdir}/acme_anchor_setup", 0600);
        mwexec("/sbin/pfctl -f ${configdir}/acme_anchor_setup");
        file_put_contents("${configdir}/acme_anchor_rules", $anchor_rules);
        chmod("${configdir}/acme_anchor_rules", 0600);
        mwexec("/sbin/pfctl -a acme-client -f ${configdir}/acme_anchor_rules");
    }

    public function cleanup()
    {
        // Flush OPNsense port forward rules.
        mwexec('/sbin/pfctl -a acme-client -F all');

        // Workaround to solve disconnection issues reported by some users.
        $backend = new \OPNsense\Core\Backend();
        $response = $backend->configdRun('filter reload');
        return true;
    }
}
