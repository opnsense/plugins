<?php

/**
 *    Copyright (C) 2024 Frank Wall
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

namespace OPNsense\AcmeClient\Migrations;

use OPNsense\Base\BaseModelMigration;

class M4_1_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Migrate validations from "lexicon" to native acme.sh DNS API
        foreach ($model->getNodeByReference('validations.validation')->iterateItems() as $validation) {
            $dns_service = (string)$validation->dns_service;
            $dns_lexicon_provider = (string)$validation->dns_lexicon_provider;

            if (!empty($dns_lexicon_provider) && ($dns_service === 'dns_lexicon')) {
                // Migrate old lexicon values to DNS API values.
                switch ($dns_lexicon_provider) {
                    case 'aliyun':
                        $validation->dns_service = 'dns_ali';
                        $validation->dns_ali_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_ali_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'aurora':
                        $validation->dns_service = 'dns_aurora';
                        $validation->dns_aurora_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_aurora_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'cloudflare':
                        $validation->dns_service = 'dns_cf';
                        $validation->dns_cf_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_cf_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'cloudns':
                        $validation->dns_service = 'dns_cloudns';
                        $validation->dns_cloudns_auth_id = (string)$validation->dns_lexicon_user;
                        $validation->dns_cloudns_auth_password = (string)$validation->dns_lexicon_token;
                        break;
                    case 'cloudxns':
                        $validation->dns_service = 'dns_cx';
                        $validation->dns_cx_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_cx_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'conoha':
                        $validation->dns_service = 'dns_conoha';
                        $validation->dns_conoha_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_conoha_password = (string)$validation->dns_lexicon_token;
                        break;
                    case 'constellix':
                        $validation->dns_service = 'dns_constellix';
                        $validation->dns_constellix_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_constellix_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'digitalocean':
                        $validation->dns_service = 'dns_dgon';
                        $validation->dns_dgon_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'directadmin':
                        $validation->dns_service = 'dns_da';
                        $validation->dns_da_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'dnsimple':
                        $validation->dns_service = 'dns_dnsimple';
                        $validation->dns_dnsimple_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'dnsmadeeasy':
                        $validation->dns_service = 'dns_me';
                        $validation->dns_me_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_me_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'dnspod':
                        $validation->dns_service = 'dns_dp';
                        $validation->dns_dp_id = (string)$validation->dns_lexicon_user;
                        $validation->dns_dp_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'dreamhost':
                        $validation->dns_service = 'dns_dreamhost';
                        // FIXME: parameter prefix should match $dns_service
                        $validation->dns_dh_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'easydns':
                        $validation->dns_service = 'dns_easydns';
                        $validation->dns_easydns_apikey = (string)$validation->dns_lexicon_user;
                        $validation->dns_easydns_apitoken = (string)$validation->dns_lexicon_token;
                        break;
                    case 'exoscale':
                        $validation->dns_service = 'dns_exoscale';
                        $validation->dns_exoscale_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_exoscale_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'gandi':
                        $validation->dns_service = 'dns_gandi_livedns';
                        $validation->dns_gandi_livedns_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_gandi_livedns_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'godaddy':
                        $validation->dns_service = 'dns_gd';
                        $validation->dns_gd_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_gd_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'googleclouddns':
                        $validation->dns_service = 'dns_gcloud';
                        $validation->dns_gcloud_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'gratisdns':
                        $validation->dns_service = 'dns_gdnsdk';
                        $validation->dns_gdnsdk_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_gdnsdk_password = (string)$validation->dns_lexicon_token;
                        break;
                    case 'henet':
                        $validation->dns_service = 'dns_he';
                        $validation->dns_he_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_he_password = (string)$validation->dns_lexicon_token;
                        break;
                    case 'hetzner':
                        $validation->dns_service = 'dns_hetzner';
                        $validation->dns_hetzner_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'infoblox':
                        $validation->dns_service = 'dns_infoblox';
                        $validation->dns_infoblox_credentials = (string)$validation->dns_lexicon_token;
                        break;
                    case 'internetbs':
                        $validation->dns_service = 'dns_internetbs';
                        $validation->dns_internetbs_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_internetbs_password = (string)$validation->dns_lexicon_token;
                        break;
                    case 'inwx':
                        $validation->dns_service = 'dns_inwx';
                        $validation->dns_inwx_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_inwx_password = (string)$validation->dns_lexicon_token;
                        break;
                    case 'linode':
                        $validation->dns_service = 'dns_linode';
                        $validation->dns_linode_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'linode4':
                        $validation->dns_service = 'dns_linode_v4';
                        $validation->dns_linode_v4_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'luadns':
                        $validation->dns_service = 'dns_lua';
                        $validation->dns_lua_email = (string)$validation->dns_lexicon_user;
                        $validation->dns_lua_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'namecheap':
                        $validation->dns_service = 'dns_namecheap';
                        $validation->dns_namecheap_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_namecheap_api = (string)$validation->dns_lexicon_token;
                        break;
                    case 'namesilo':
                        $validation->dns_service = 'dns_namesilo';
                        $validation->dns_namesilo_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'netcup':
                        $validation->dns_service = 'dns_netcup';
                        $validation->dns_netcup_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_netcup_pw = (string)$validation->dns_lexicon_token;
                        break;
                    case 'nfsn':
                        $validation->dns_service = 'dns_njalla';
                        $validation->dns_njalla_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'nsone':
                        $validation->dns_service = 'dns_nsone';
                        $validation->dns_nsone_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'online':
                        $validation->dns_service = 'dns_online';
                        $validation->dns_online_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'ovh':
                        $validation->dns_service = 'dns_ovh';
                        $validation->dns_ovh_app_key = (string)$validation->dns_lexicon_user;
                        $validation->dns_ovh_app_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'plesk':
                        $validation->dns_service = 'dns_pleskxml';
                        $validation->dns_pleskxml_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_pleskxml_pass = (string)$validation->dns_lexicon_token;
                        break;
                    case 'pointhq':
                        $validation->dns_service = 'dns_pointhq';
                        $validation->dns_pointhq_email = (string)$validation->dns_lexicon_user;
                        $validation->dns_pointhq_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'powerdns':
                        $validation->dns_service = 'dns_pdns';
                        $validation->dns_pdns_serverid = (string)$validation->dns_lexicon_user;
                        $validation->dns_pdns_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'rackspace':
                        $validation->dns_service = 'dns_rackspace';
                        $validation->dns_rackspace_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_rackspace_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'rage4':
                        $validation->dns_service = 'dns_rage4';
                        $validation->dns_rage4_user = (string)$validation->dns_lexicon_user;
                        $validation->dns_rage4_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'route53':
                        $validation->dns_service = 'dns_aws';
                        $validation->dns_aws_id = (string)$validation->dns_lexicon_user;
                        $validation->dns_aws_secret = (string)$validation->dns_lexicon_token;
                        break;
                    case 'transip':
                        $validation->dns_service = 'dns_transip';
                        $validation->dns_transip_username = (string)$validation->dns_lexicon_user;
                        $validation->dns_transip_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'vultr':
                        $validation->dns_service = 'dns_vultr';
                        $validation->dns_vultr_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'yandex':
                        $validation->dns_service = 'dns_yandex';
                        $validation->dns_yandex_token = (string)$validation->dns_lexicon_token;
                        break;
                    case 'zeit':
                        // URL points to zilore, so we'll use this DNS API as a replacement.
                        $validation->dns_service = 'dns_zilore';
                        $validation->dns_zilore_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'zilore':
                        $validation->dns_service = 'dns_zilore';
                        $validation->dns_zilore_key = (string)$validation->dns_lexicon_token;
                        break;
                    case 'zonomi':
                        $validation->dns_service = 'dns_zonomi';
                        // FIXME: parameter prefix should match $dns_service
                        $validation->dns_zm_key = (string)$validation->dns_lexicon_token;
                        break;
                }
            }
        }
    }
}
