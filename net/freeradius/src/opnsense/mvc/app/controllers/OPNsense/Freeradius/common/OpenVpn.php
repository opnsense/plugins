<?php

/*
 * Copyright (C) 2018 Eugen Mayer
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

namespace OPNsense\Freeradius\common;
// yeah, why should plugins.inc.d/openvpn.inc include all the symbols it is using..
require_once("util.inc");
require_once("plugins.inc.d/openvpn.inc");

use \OPNsense\Core\Config;

/**
 * Handles all kind of OpenVPN based operations
 * Class OpenVpn
 * @package OPNsense\Freeradius\common
 */
class OpenVpn
{

    /**
     * @param CCD $dynamicCDD
     * @param null $servers
     */
    static public function resetToStaticOrDelete(CCD $dynamicCDD, $servers = null)
    {
        if ($servers == NULL) {
            $servers = self::getDynamicCCDopenVPNServers();
        }
        $staticCCDs = self::getOpenVpnCCD();
        if (isset($staticCCDs[$dynamicCDD->common_name])) {
            $ccd = $staticCCDs[$dynamicCDD->common_name];
            foreach ($servers as $server) {
                // thats a openvpn legacy tool to create CCDs for a specific server
                // lets use this to ensure compatibility
                $ccdConfigAsString = openvpn_csc_conf(self::ccdToLegacyStructure($ccd), $server);
                // this will override - reset the overlayed one with the original from static
                self::writeCCDforServer($ccd->common_name, $ccdConfigAsString, $server['vpnid']);
            }
        } else {
            // since the CCD does not exist in static, remove it. This is already more then the current
            //  core implementation does it does nothing in this case
            foreach ($servers as $server) {
                self::deleteCCDforServer($dynamicCDD->common_name, $server['vpnid']);
            }
        }

    }

    /**
     * @param CCD[] $dynamicCDDs
     * @param null $servers if null, it means all
     */
    static public function generateCCDconfigurationOnDisk($dynamicCDDs, $servers = null, $reset = false)
    {
        if ($servers == NULL) {
            $servers = self::getDynamicCCDopenVPNServers();
        }

        $staticCCDs = self::getOpenVpnCCD();
        // since this whole thing should only "override" or generate those one, we defined
        // we do not work through the openvpn staticCCD but only ours
        // and either generate new ones or overwrite existing ones

        foreach ($dynamicCDDs as $dynamicCCD) {
            $ccd = $dynamicCCD;
            /**
             * if an openVPN static_ccd exists, rather use our
             * dynamicCCD as a overlay, so use all fields from openVPN except some few
             * we offer in the dynamicCCD
             */
            if (array_key_exists($dynamicCCD->common_name, $staticCCDs)) {
                $ccd = self::overlayCcd($staticCCDs[$dynamicCCD->common_name], $dynamicCCD);
            }

            // now generate that CCD for every server
            foreach ($servers as $server) {
                // thats a openvpn legacy tool to create CCDs for a specific server
                // lets use this to ensure compatibility
                $ccdConfigAsString = openvpn_csc_conf(self::ccdToLegacyStructure($ccd), $server);

                self::writeCCDforServer($ccd->common_name, $ccdConfigAsString, $server['vpnid']);
            }
        }
    }

    /**
     * Writes the ccd configuration we created using the legacy method to the disk at the correct location for a specific server
     * @param string $common_name
     * @param string $ccdConfigAsString
     * @param string $openvpn_id
     */
    static function writeCCDforServer($common_name, $ccdConfigAsString, $openvpn_id)
    {
        openvpn_create_dirs();
        // 'stolen' from openvpn_configure_csc - we cannot reuse this function since its not designed to
        $target_filename = "/var/etc/openvpn-csc/{$openvpn_id}/{$common_name}";
        file_put_contents($target_filename, $ccdConfigAsString);
        chown($target_filename, 'nobody');
        chgrp($target_filename, 'nobody');
    }

    /**
     * This method is missing in the legacy API completely
     * @param string $common_name
     * @param string $openvpn_id
     */
    static function deleteCCDforServer($common_name, $openvpn_id)
    {
        $target_filename = "/var/etc/openvpn-csc/{$openvpn_id}/{$common_name}";
        @unlink($target_filename);
    }

    static function ccdToLegacyStructure(CCD $ccd)
    {
        return (array)$ccd;
    }

    /**
     * returns a CCD, which is based on your static(default) ccd and has been overlayed by the dynamic CCD
     * @param CCD $static
     * @param CCD $dynamic
     * @return CCD
     */
    static function overlayCcd(CCD $static, CCD $dynamic)
    {
        // lets keep the reference intact
        $ccd = clone($static);

        $overridable_attribs = ['tunnel_network', 'tunnel_network6'];
        foreach ($overridable_attribs as $attrib) {
            if (isset($dynamic->{$attrib})) {
                $ccd->{$attrib} = $dynamic->{$attrib};
            }
        }
        return $ccd;
    }

    /**
     * @return array an array of VPN-Servers ( stdClass ) which have the feature dynamic-ccd-lookup enabled
     */
    static function getDynamicCCDopenVPNServers()
    {
        $configObj = Config::getInstance()->object();
        $servers = array();

        if (isset($configObj->openvpn)) {
            /** @var \SimpleXMLElement $root */
            $root = $configObj->openvpn;
            foreach ($root->children() as $name => $vpnServer) {
                // if that VPN server has dynamic ccd enabled
                if ($vpnServer->{'dynamic-ccd-lookup'} == '1') {
                    // convert that one to an assoc array for easier usage in the toolchain
                    $servers[$name] = json_decode(json_encode($vpnServer), true);
                }
            }
        }

        return $servers;
    }

    /**
     * @return CCD[]
     */
    static function getOpenVpnCCD()
    {
        $configObj = Config::getInstance()->object();

        if (isset($configObj->openvpn) && isset($configObj->openvpn->{'openvpn-csc'})) {
            $ccds = array();
            $ccd_attributes = array_keys(get_class_vars('OPNsense\Freeradius\common\CCD'));
            // odd need of parsing them here, otherwise the result gets oddly transpiled

            foreach ($configObj->openvpn->{'openvpn-csc'} as $ccdXml) {
                $obj = json_decode(json_encode($ccdXml));
                $ccd = new CCD();

                // map all our legacy attributes on our helper class
                foreach ($ccd_attributes as $attr) {
                    if (isset($obj->{$attr})) {
                        $ccd->{$attr} = $obj->{$attr};
                    }
                }
                $ccds[$ccd->common_name] = $ccd;
            }
            return $ccds;
        }
        return NULL;
    }
}