<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
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

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("plugins.inc.d/miniupnpd.inc");

function miniupnpd_validate_ip($ip)
{
    /* validate cidr */
    $ip_array = [];
    $ip_array = explode('/', $ip);
    if (count($ip_array) == 2) {
        if ($ip_array[1] < 0 || $ip_array[1] > 32) {
            return false;
        }
    } elseif (count($ip_array) != 1) {
        return false;
    }

    /* validate ip */
    if (!is_ipaddr($ip_array[0])) {
        return false;
    }

    return true;
}

function miniupnpd_validate_port($port)
{
    foreach (explode('-', $port) as $sub) {
        if ($sub < 0 || $sub > 65535 || !is_numeric($sub)) {
            return false;
        }
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = [];

    $copy_fields = [
        'allow_third_party_mapping',
        'download',
        'enable',
        'enable_natpmp',
        'enable_upnp',
        'ext_iface',
        'friendly_name',
        'iface_array',
        'ipv6_disable',
        'log_level',
        'logpackets',
        'overridesubnet',
        'overridewanip',
        'permdefault',
        'stun_host',
        'stun_port',
        'num_permuser',
        'sysuptime',
        'upload',
        'upnp_igd_compat',
    ];

    foreach (miniupnpd_permuser_list() as $permuser) {
        $copy_fields[] = $permuser;
    }

    $pconfig['num_permuser'] = null;

    foreach ($copy_fields as $fieldname) {
        if (isset($config['installedpackages']['miniupnpd']['config'][0][$fieldname])) {
            $pconfig[$fieldname] = $config['installedpackages']['miniupnpd']['config'][0][$fieldname];
        }
    }
    // parse array
    $pconfig['iface_array'] = explode(',', $pconfig['iface_array']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = [];
    $pconfig = $_POST;

    // validate form data
    if (!empty($pconfig['enable']) && (empty($pconfig['enable_upnp']) && empty($pconfig['enable_natpmp']))) {
        $input_errors[] = gettext('At least one of \'UPnP IGD\' or \'PCP/NAT-PMP\' must be allowed');
    }
    if (!empty($pconfig['iface_array'])) {
        foreach($pconfig['iface_array'] as $iface) {
            if ($iface == 'wan') {
                $input_errors[] = gettext('It is a security risk to specify WAN as an internal interface.');
            } elseif ($iface == $pconfig['ext_iface']) {
                $input_errors[] = gettext('You cannot select the external interface as an internal interface.');
            }
        }
    } else {
        $input_errors[] = gettext('You must specify at least one internal interface.');
    }
    if (!empty($pconfig['overridewanip']) && !is_ipaddr($pconfig['overridewanip'])) {
        $input_errors[] = gettext('You must specify a valid ip address in the \'Override WAN address\' field');
    }
    if (!empty($pconfig['overridewanip']) && !empty($pconfig['stun_host'])) {
        $input_errors[] = gettext('You cannot override the WAN IP if you have a STUN host set.');
    }
    if (!empty($pconfig['stun_host']) && !is_ipaddr($pconfig['stun_host']) && !is_hostname($pconfig['stun_host'])) {
        $input_errors[] = gettext('The STUN host must be a valid IP address or hostname.');
    }
    if (!empty($pconfig['stun_port']) && !is_port($pconfig['stun_port'])) {
        $input_errors[] = gettext('STUN port must contain a valid port number.');
    }
    if (!empty($pconfig['overridesubnet']) && count($pconfig['iface_array']) > 1) {
        $input_errors[] = gettext('You can only override the interface subnet when one LAN interface is selected');
    }
    if ((!empty($pconfig['download']) && empty($pconfig['upload'])) || (!empty($pconfig['upload']) && empty($pconfig['download']))) {
        $input_errors[] = gettext('You must fill in both \'Maximum Download Speed\' and \'Maximum Upload Speed\' fields');
    }
    if (!empty($pconfig['download']) && (!is_numeric($pconfig['download']) || $pconfig['download'] <= 0)) {
        $input_errors[] = gettext('You must specify a value greater than 0 in the \'Maximum Download Speed\' field');
    }
    if (!empty($pconfig['upload']) && (!is_numeric($pconfig['upload']) || $pconfig['upload'] <= 0)) {
        $input_errors[] = gettext('You must specify a value greater than 0 in the \'Maximum Upload Speed\' field');
    }
    if (!empty($pconfig['num_permuser'] && (!is_numeric($pconfig['num_permuser']) || $pconfig['num_permuser'] < 1))) {
        $input_errors[] = gettext('Number of permissions must be an integer greater than 0');
    }

    /* user permissions validation */
    foreach (miniupnpd_permuser_list() as $i => $permuser) {
        if (!empty($pconfig[$permuser])) {
            $perm = explode(' ', $pconfig[$permuser]);
            /* should explode to 4 args */
            if (count($perm) != 4) {
                $input_errors[] = sprintf(gettext("You must follow the specified format in the 'User specified permissions %s' field"), $i);
            } else {
              /* must with allow or deny */
              if (!($perm[0] == 'allow' || $perm[0] == 'deny')) {
                $input_errors[] = sprintf(gettext("You must begin with allow or deny in the 'User specified permissions %s' field"), $i);
              }
              /* verify port or port range */
              if (!miniupnpd_validate_port($perm[1]) || !miniupnpd_validate_port($perm[3])) {
                  $input_errors[] = sprintf(gettext("You must specify a port or port range between 0 and 65535 in the 'User specified permissions %s' field"), $i);
              }
              /* verify ip address */
              if (!miniupnpd_validate_ip($perm[2])) {
                  $input_errors[] = sprintf(gettext("You must specify a valid ip address in the 'User specified permissions %s' field"), $i);
              }
            }
        }
    }

    if (count($input_errors) == 0) {
        // save form data
        $upnp = [];
        // boolean types
        foreach (['enable', 'enable_upnp', 'enable_natpmp', 'logpackets', 'sysuptime', 'permdefault', 'allow_third_party_mapping', 'ipv6_disable'] as $fieldname) {
            $upnp[$fieldname] = !empty($pconfig[$fieldname]);
        }
        // numeric types
        if (!empty($pconfig['num_permuser'])) {
            $upnp['num_permuser'] = $pconfig['num_permuser'];
        }
        // text field types
        foreach (['download', 'ext_iface', 'friendly_name', 'log_level', 'overridesubnet', 'overridewanip', 'stun_host', 'stun_port', 'upload', 'upnp_igd_compat'] as $fieldname) {
            $upnp[$fieldname] = $pconfig[$fieldname];
        }
        foreach (miniupnpd_permuser_list() as $fieldname) {
            $upnp[$fieldname] = $pconfig[$fieldname];
        }
        // array types
        $upnp['iface_array'] = implode(',', $pconfig['iface_array']);
        // sync to config
        $config['installedpackages']['miniupnpd']['config'] = $upnp;

        write_config('Modified UPnP IGD & PCP settings');
        miniupnpd_configure_do();
        filter_configure();
        header(url_safe('Location: /services_upnp.php'));
        exit;
    }
}


$service_hook = 'miniupnpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <form method="post" name="iform" id="iform">
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th style="width:22%"><?=gettext("Service Setup");?></th>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        &nbsp;&nbsp;
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable service");?></td>
                      <td>
                       <input name="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_enable">
                         <?=gettext("Enable the autonomous port mapping service.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_enable_upnp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable UPnP IGD protocol");?></td>
                      <td>
                       <input name="enable_upnp" type="checkbox" value="yes" <?=!empty($pconfig['enable_upnp']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_enable_upnp">
                         <?=gettext("This protocol is often used by Microsoft-compatible systems.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_enable_natpmp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable PCP/NAT-PMP protocols");?></td>
                      <td>
                       <input name="enable_natpmp" type="checkbox" value="yes" <?=!empty($pconfig['enable_natpmp']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_enable_natpmp">
                         <?=gettext("These protocols are often used by Apple-compatible systems.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ext_iface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("External interface");?></td>
                      <td>
                       <select class="selectpicker" name="ext_iface">
<?php foreach (get_configured_interface_with_descr() as $iface => $ifacename): ?>
                          <option value="<?= html_safe($iface) ?>" <?= $pconfig['ext_iface'] == $iface ? 'selected="selected"' : '' ?>>
                            <?= html_safe($ifacename) ?>
                          </option>
<?php endforeach ?>
                       </select>
                       <div class="hidden" data-for="help_for_ext_iface">
                         <?=gettext("The WAN network interface containing the default gateway.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_iface_array" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Internal interfaces");?></td>
                      <td>
                       <select class="selectpicker" name="iface_array[]" multiple="multiple">
                         <option value="lo0" <?=!empty($pconfig['iface_array']) && in_array('lo0', $pconfig['iface_array']) ? 'selected="selected"' : '' ?>>
                           <?= html_safe(gettext('Localhost')) ?>
                         </option>
<?php foreach (get_configured_interface_with_descr() as $iface => $ifacename): ?>
                          <option value="<?= html_safe($iface) ?>" <?= in_array($iface, $pconfig['iface_array'] ?? []) ? 'selected="selected"' : '' ?>>
                            <?= html_safe($ifacename) ?>
                          </option>
<?php endforeach ?>
                       </select>
                       <div class="hidden" data-for="help_for_iface_array">
                         <?=gettext("Select one or more internal network interfaces, such as LAN, where clients reside.");?>
                       </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th style="width:22%"><?=gettext("Advanced Settings")?></th>
                      <th style="width:78%"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_stun_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('STUN server') ?></td>
                      <td>
                        <input name="stun_host" type="text" value="<?= $pconfig['stun_host'] ?? '' ?>" />
                        <div class="hidden" data-for="help_for_stun_host">
                          <?= gettext('Allow use of unrestricted endpoint-independent (1:1) CGNATs and detect the public IPv4 using e.g. "stun.3cx.com" or "stun.counterpath.com".') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('STUN port') ?></td>
                      <td>
                        <input name="stun_port" type="text" placeholder="3478" value="<?= $pconfig['stun_port'] ?? ''  ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_overridewanip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Override external IPv4");?></td>
                      <td>
                        <input name="overridewanip" type="text" value="<?=$pconfig['overridewanip'];?>" />
                        <div class="hidden" data-for="help_for_overridewanip">
                          <?=gettext('Report custom public/external (WAN) IPv4 address.');?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_overridesubnet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Internal interface IPv4 subnet override");?></td>
                      <td>
                        <select name="overridesubnet" class="selectpicker" id="overridesubnet">
                          <option value="" <?= empty($pconfig['overridesubnet']) ? 'selected="selected"' : '' ?>><?= gettext('Default') ?></option>
<?php for ($i = 32; $i >= 1; $i--): ?>
                          <option value="<?= $i ?>" <?=!empty($pconfig['overridesubnet']) && $pconfig['overridesubnet'] == $i ? 'selected="selected"' : '' ?>><?= $i ?></option>
<?php endfor ?>
                        </select>
                        <div class="hidden" data-for="help_for_overridesubnet">
                          <?=gettext("You can override a single LAN interface subnet here. Useful if you are rebroadcasting service traffic across networks.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_allow_third_party_mapping" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Allow third-party mapping");?></td>
                      <td>
                        <input name="allow_third_party_mapping" type="checkbox" value="yes" <?=!empty($pconfig['allow_third_party_mapping']) ? "checked=\"checked\"" : ""; ?> />
                        <div class="hidden" data-for="help_for_allow_third_party_mapping">
                          <?=gettext("Allow adding port maps for non-requesting IP addresses.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Disable IPv6 mapping') ?></td>
                      <td>
                        <input name="ipv6_disable" type="checkbox" value="yes" <?= !empty($pconfig['ipv6_disable']) ? "checked=\"checked\"" : ""; ?> />
                      </td>
                    </tr>
                    <!-- <tr>
                      <td><a id="help_for_sysuptime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Report system uptime");?></td>
                      <td>
                       <input name="sysuptime" type="checkbox" value="yes" <?=!empty($pconfig['sysuptime']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_sysuptime">
                         <?=gettext("Report system instead of service uptime.");?>
                       </div>
                      </td>
                    </tr> -->
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Log level') ?></td>
                      <td>
                        <select name="log_level">
                          <option value="default" <?= ($pconfig['log_level'] ?? '') == 'default' ? 'selected="selected"' : '' ?> ><?= gettext('Default') ?></option>
                          <option value="info" <?= ($pconfig['log_level'] ?? '') == 'info' ? 'selected="selected"' : '' ?> ><?= gettext('Info') ?></option>
                          <option value="debug" <?= ($pconfig['log_level'] ?? '') == 'debug' ? 'selected="selected"' : '' ?> ><?= gettext('Debug') ?></option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_logpackets" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall logs");?></td>
                      <td>
                       <input name="logpackets" type="checkbox" value="yes" <?=!empty($pconfig['logpackets']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_logpackets">
                         <?=gettext("Log mapped connections.");?>
                       </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th style="width:22%"><?= gettext("UPnP IGD Adjustments") ?></th>
                      <th style="width:78%"></th>
                    </tr>
                  </thead>
                  <tbody>
                  <tr>
                    <td><a id="help_for_upnp_igd_compat" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('UPnP IGD compatibility') ?></td>
                    <td>
                      <select name="upnp_igd_compat">
                        <option value="igdv1" <?= ($pconfig['upnp_igd_compat'] ?? '') == 'igdv1' ? 'selected="selected"' : '' ?> ><?= gettext('IGDv1 (IPv4 only)') ?></option>
                        <option value="igdv2" <?= ($pconfig['upnp_igd_compat'] ?? '') == 'igdv2' ? 'selected="selected"' : '' ?> ><?= gettext('IGDv2 (with workarounds)') ?></option>
                      </select>
                      <div class="hidden" data-for="help_for_upnp_igd_compat">
                        <?=sprintf(gettext('Set compatibility mode (act as device) to workaround IGDv2-incompatible clients; %s are known to only work with %s.'), 'Sony PS, Activision CoD…', 'IGDv1');?>
                      </div>
                    </td>
                  </tr>
                    <tr>
                      <td><a id="help_for_download" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Download speed");?></td>
                      <td>
                        <input name="download" type="text" placeholder="<?=gettext('Default interface link speed');?>" value="<?=$pconfig['download'];?>" />
                        <div class="hidden" data-for="help_for_download">
                          <?=gettext("Report maximum connection speed in kbit/s.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_upload" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Upload speed");?></td>
                      <td>
                        <input name="upload" type="text" placeholder="<?=gettext('Default interface link speed');?>" value="<?=$pconfig['upload'];?>" />
                        <div class="hidden" data-for="help_for_upload">
                          <?=gettext("Report maximum connection speed in kbit/s.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Router/friendly name') ?></td>
                      <td>
                        <input name="friendly_name" type="text" placeholder="OPNsense UPnP IGD &amp; PCP" value="<?= $pconfig['friendly_name'] ?? '' ?>" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th colspan="2"><?=gettext("Access Control List");?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_permdefault" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Default deny");?></td>
                      <td>
                       <input name="permdefault" type="checkbox" value="yes" <?=!empty($pconfig['permdefault']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_permdefault">
                         <?=gettext("Deny access to service by default.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_num_permuser" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Number of entries");?></td>
                      <td>
                        <input name="num_permuser" type="text" placeholder="8" value="<?= $pconfig['num_permuser'] ?>" />
                        <div class="hidden" data-for="help_for_num_permuser">
                          <?=gettext("Number of ACL entries to configure.");?>
                        </div>
                      </td>
                    </tr>
<?php foreach (miniupnpd_permuser_list() as $i => $permuser): ?>
                    <tr>
<?php if ($i == 1): ?>
                      <td style="width:22%"><a id="help_for_permuser" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('ACL entry') . ' ' . $i ?></td>
<?php else: ?>
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('ACL entry') . ' ' . $i ?></td>
<?php endif ?>
                      <td style="width:78%">
                        <input name="<?= html_safe($permuser) ?>" type="text" value="<?= $pconfig[$permuser] ?? '' ?>" />
<?php if ($i == 1): ?>
                        <div class="hidden" data-for="help_for_permuser">
                          <?=gettext("Syntax: (allow or deny) (ext port or range) (int IP or IP/netmask) (int port or range)");?><br/>
                          <?=gettext("Example: allow 1024-65535 192.168.1.0/24 1024-65535");?>
                        </div>
<?php endif ?>
                      </td>
                    </tr>
<?php endforeach ?>
                    <tr><td colspan="2"><?=gettext("The access control list (ACL) specifies which IP addresses and ports can be mapped. IPv6 is currently always accepted unless disabled.");?></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tbody>
                    <tr>
                     <td style="width:22%; vertical-align:top">&nbsp;</td>
                     <td style="width:78%">
                       <input name="Submit" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" />
                     </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </form>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
