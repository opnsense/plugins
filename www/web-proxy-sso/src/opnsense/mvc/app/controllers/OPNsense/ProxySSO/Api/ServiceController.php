<?php

/*
 * Copyright (C) 2017 Smart-Soft
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

namespace OPNsense\ProxySSO\Api;

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\ProxySSO\ProxySSO;

class ServiceController extends \OPNsense\Proxy\Api\ServiceController
{
    /**
     * show Kerberos keytab for Proxy
     * @return array
     */
    public function showkeytabAction()
    {
        $backend = new Backend();

        $response = $backend->configdRun("proxysso showkeytab");
        return array("response" => $response,"status" => "ok");
    }

    /**
     * delete Kerberos keytab for Proxy
     * @return array
     */
    public function deletekeytabAction()
    {
        $backend = new Backend();

        $response = $backend->configdRun("proxysso deletekeytab");
        return array("response" => $response,"status" => "ok");
    }

    /**
     * create Kerberos keytab for Proxy
     * @return array
     */
    public function createkeytabAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $mdl = new ProxySSO();
            $cnf = Config::getInstance()->object();
            $hostname = 'HTTP/' . $cnf->system->hostname;
            $domain = $cnf->system->domain;
            $kerbname = strtoupper((string)$mdl->KerberosHostName);
            $winver = (string)$mdl->ADKerberosImplementation == 'W2008' ? '2008' : '2003';
            $username = escapeshellarg($this->request->getPost("admin_login"));
            $pass = escapeshellarg($this->request->getPost("admin_password"));

            $response = $backend->configdRun("proxysso createkeytab {$hostname} {$domain} {$kerbname} {$winver} {$username} {$pass}");
            parent::reconfigureAction();
            return array("response" => $response,"status" => "ok");
        }

        return array("response" => array());
    }

    /**
     * test Kerberos login
     * @return array
     */
    public function testkerbloginAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $cnf = Config::getInstance()->object();
            $fqdn = $cnf->system->hostname . '.' . $cnf->system->domain;
            $username = escapeshellarg($this->request->getPost("login"));
            $pass = escapeshellarg($this->request->getPost("password"));

            $response = $backend->configdRun("proxysso testkerblogin {$username} {$pass} {$fqdn}");
            return array("response" => $response,"status" => "ok");
        }

        return array("response" => array());
    }

    /**
     * get checklist data
     * @return array
     */
    public function getCheckListAction()
    {
        $backend = new Backend();
        $cnf = Config::getInstance()->object();
        $hostname = $cnf->system->hostname . '.' . $cnf->system->domain;

        // LDAP
        $methods = explode(',', $cnf->OPNsense->proxy->forward->authentication->method);
        foreach ($methods as $method) {
            $xpath = $cnf->xpath("//system/authserver[name=\"$method\" and type=\"ldap\"]");
            if (count($xpath)) {
                $ldap_server = $xpath[0];
                break;
            }
        }
        $ldap_ip = null;
        $ldap_fqdn = null;
        $ldap_server_ping = [ "status" => "failure"];
        if (isset($ldap_server) && !empty($ldap_server->host)) {
            if (filter_var($ldap_server->host, FILTER_VALIDATE_IP)) {
                $ldap_ip = $ldap_server->host;
            } else {
                $ldap_fqdn = $ldap_server->host;
            }

            $host_esc = escapeshellarg("{$ldap_server->host}");
            $output = array("# ping -c 1 -W 1 {$host_esc}");
            $retval = 0;
            exec("ping -c 1 -W 1 {$host_esc}", $output, $retval);
            $ldap_server_ping = [ "status" => $retval == 0 ? "ok" : "failure"];
            $ldap_server_ping["dump"] = implode("\n", $output);
        }

        // DNS
        $dns_server = array();
        $nameservers = preg_grep('/^nameserver/', file('/etc/resolv.conf'));
        $dns_servers = array();
        foreach ($nameservers as $key => $record) {
            $parts = explode(' ', $record);
            $dns_servers[] = trim($parts[1]);
        }
        $dns_server = [ "status" => count($dns_servers) ? "ok" : "failure"];
        if (!count($dns_servers)) {
            $dns_server["message"] = gettext("DNS server not found");
        }
        $output = "# cat /etc/resolv.conf\n";
        $output .= file_get_contents('/etc/resolv.conf');
        $dns_server["dump"] = $output;

        // DNS: hostname
        $resolv_direct = chop(shell_exec("drill {$hostname} | grep -A 1 'ANSWER SECTION' | tail -n 1 | awk '{print \$5}'"));
        $dns_hostname_resolution = [ "status" => !empty($resolv_direct) && filter_var($resolv_direct, FILTER_VALIDATE_IP) ? "ok" : "failure"];
        $output = array("# drill {$hostname}");
        exec("drill {$hostname}", $output);
        $dns_hostname_resolution["dump"] = implode("\n", $output);

        $resolv_reverse = null;
        $dns_hostname_reverse_resolution = array();
        $output = array();
        if (!empty($resolv_direct) && filter_var($resolv_direct, FILTER_VALIDATE_IP)) {
            $output[] = "# drill -x {$resolv_direct}";
            exec("drill -x {$resolv_direct}", $output);
            $resolv_reverse = chop(shell_exec("drill -x {$resolv_direct} | grep -A 1 'ANSWER SECTION' | tail -n 1 | awk '{print \$5}'"));
            if (strtolower($resolv_reverse) != strtolower("{$hostname}.")) {
                $dns_hostname_reverse_resolution["message"] = gettext("Hostname doesn't resolved to host IP.");
            }
        } else {
            $dns_hostname_reverse_resolution["message"] = gettext("Hostname doesn't resolved to IP.");
        }
        $dns_hostname_reverse_resolution["status"] = strtolower($resolv_reverse) == strtolower("{$hostname}.") ? "ok" : "failure";
        $dns_hostname_reverse_resolution["dump"] = implode("\n", $output);


        // DNS: LDAP server
        ldap_dns:
        $dns_ldap_reverse_resolution = array( "status" => "failure" );
        if (empty($ldap_ip)) {
            $dns_ldap_reverse_resolution["message"] = gettext("Unknown LDAP server IP.");
        } else {
            $ldap_ip_esc = escapeshellarg($ldap_ip);
            $resolv_reverse = chop(shell_exec("drill -x {$ldap_ip_esc} | grep -A 1 'ANSWER SECTION' | tail -n 1 | awk '{print \$5}'"));
            if (empty($resolv_reverse)) {
                $dns_ldap_reverse_resolution["message"] = gettext('LDAP server IP reverse lookup error.');
            } elseif (!empty($ldap_fqdn) && $resolv_reverse != "{$ldap_fqdn}.") {
                $dns_ldap_reverse_resolution["message"] = gettext('LDAP server reverse DNS lookup is not equal to LDAP server FQDN.');
            } else {
                $dns_ldap_reverse_resolution["status"] = "ok";
                $ldap_fqdn = substr($resolv_reverse, 0, strlen($resolv_reverse) - 1);
            }
            $output = array("# drill -x {$ldap_ip_esc}");
            exec("drill -x {$ldap_ip_esc}", $output);
            $dns_ldap_reverse_resolution["dump"] = implode("\n", $output);
        }

        $dns_ldap_resolution = array( "status" => "failure" );
        if (empty($ldap_fqdn)) {
            $dns_ldap_resolution["message"] = gettext('Unknown LDAP server FQDN.');
        } else {
            $ldap_fqdn_esc = escapeshellarg($ldap_fqdn);
            $resolv = chop(shell_exec("drill {$ldap_fqdn_esc} | grep -A 1 'ANSWER SECTION' | tail -n 1 | awk '{print \$5}'"));
            if (empty($resolv)) {
                $dns_ldap_resolution["message"] = gettext('LDAP server DNS lookup error.');
            } elseif (!empty($ldap_ip) && $resolv != $ldap_ip) {
                $dns_ldap_resolution["message"] = gettext('LDAP server DNS lookup is not equal to LDAP IP.');
            } else {
                $dns_ldap_resolution["status"] = "ok";
                if (empty($ldap_ip)) {
                    $ldap_ip = $resolv;
                    goto ldap_dns;
                }
            }
            $output = array("# drill {$ldap_fqdn_esc}");
            exec("drill {$ldap_fqdn_esc}", $output);
            $dns_ldap_resolution["dump"] = implode("\n", $output);
        }


        // KERBEROS
        $krb5_conf = '/etc/krb5.conf';
        $kerberos_config = array();
        $kerberos_config["status"] = "failure";
        if (!file_exists($krb5_conf)) {
            $kerberos_config["message"] = sprintf(gettext('File %s does not exists.'), $krb5_conf);
        } else {
            $domainstr = preg_quote($cnf->system->domain);
            $config_valid = preg_grep("/$domainstr/", file($krb5_conf));
            $kerberos_config["status"] = file_exists($krb5_conf) && !empty($config_valid) ? "ok" : "failure";
            if (empty($config_valid)) {
                $kerberos_config["message"] = gettext('SSO is not enabled or kerberos configuration file has invalid content');
            }
            $output = "# cat $krb5_conf\n";
            $output .= file_get_contents($krb5_conf);
            $kerberos_config["dump"] = $output;
        }

        $keytab_file = '/usr/local/etc/squid/squid.keytab';
        $keytab = array();
        $keytab["status"] = file_exists($keytab_file) ? "ok" : "failure";
        if (!file_exists($keytab_file)) {
            $keytab["message"] = sprintf(gettext('File %s does not exists.'), $keytab_file);
        }
        $keytab["dump"] = $backend->configdRun("proxysso showkeytab");


        // and two more DNS check
        if (!empty($ldap_ip) && !in_array($ldap_ip, $dns_servers)) {
            $dns_server["status"] = "failure";
            $dns_server["message"] = gettext("LDAP server is not in DNS servers list.");
        } elseif (in_array("127.0.0.1", $dns_servers) || in_array("::1", $dns_servers)) {
            $dns_server["status"] = "failure";
            $dns_server["message"] = gettext("Do not set localhost as DNS server.");
        }


        return  [
                    "hostname" => $hostname,
                    "ldap_server_config" => isset($ldap_server) ? $ldap_server->name->__toString() : array("status" => "failure", "message" => gettext("LDAP server is not set in Web Proxy - Authentication Settings")),
                    "ldap_server" => isset($ldap_server) ? $ldap_server->host->__toString() : "",
                    "ldap_server_ping" => $ldap_server_ping,
                    "dns_server" => $dns_server,
                    "dns_hostname_resolution" => $dns_hostname_resolution,
                    "dns_hostname_reverse_resolution" => $dns_hostname_reverse_resolution,
                    "dns_ldap_resolution" => $dns_ldap_resolution,
                    "dns_ldap_reverse_resolution" => $dns_ldap_reverse_resolution,
                    "kerberos_config" => $kerberos_config,
                    "keytab" => $keytab,
                ];
    }
}
