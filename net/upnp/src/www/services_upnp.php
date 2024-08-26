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
        'download',
        'enable',
        'enable_natpmp',
        'enable_upnp',
        'ext_iface',
        'iface_array',
        'logpackets',
        'overridesubnet',
        'overridewanip',
        'permdefault',
        'stun_host',
        'stun_port',
        'sysuptime',
        'upload',
    ];

    foreach (miniupnpd_permuser_list() as $permuser) {
        $copy_fields[] = $permuser;
    }

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
    if (!empty($pconfig['download']) && ($pconfig['download'] <= 0 || !is_numeric($pconfig['download']))) {
        $input_errors[] = gettext('You must specify a value greater than 0 in the \'Maximum Download Speed\' field');
    }
    if (!empty($pconfig['upload']) && ($pconfig['upload'] <= 0 || !is_numeric($pconfig['upload']))) {
        $input_errors[] = gettext('You must specify a value greater than 0 in the \'Maximum Upload Speed\' field');
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
        foreach (['enable', 'enable_upnp', 'enable_natpmp', 'logpackets', 'sysuptime', 'permdefault'] as $fieldname) {
            $upnp[$fieldname] = !empty($pconfig[$fieldname]);
        }
        // text field types
        foreach (['ext_iface', 'download', 'upload', 'overridewanip', 'overridesubnet', 'stun_host', 'stun_port'] as $fieldname) {
            $upnp[$fieldname] = $pconfig[$fieldname];
        }
        foreach (miniupnpd_permuser_list() as $fieldname) {
            $upnp[$fieldname] = $pconfig[$fieldname];
        }
        // array types
        $upnp['iface_array'] = implode(',', $pconfig['iface_array']);
        // sync to config
        $config['installedpackages']['miniupnpd']['config'] = $upnp;

        write_config('Modified Universal Plug and Play settings');
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
                      <td style="width:22%">
                        <strong><?=gettext("UPnP IGD & PCP/NAT-PMP Settings");?></strong>
                      </td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        &nbsp;&nbsp;
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_enable" href="#" class="showhelp"><i class="fa fa-info-circle text-muted"></i></a> <?=gettext("Enable");?></td>
                      <td>
                       <input name="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_enable">
                         <?=gettext("Enable autonomous port mapping service.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_enable_upnp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Allow UPnP IGD Port Mapping");?></td>
                      <td>
                       <input name="enable_upnp" type="checkbox" value="yes" <?=!empty($pconfig['enable_upnp']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_enable_upnp">
                         <?=gettext("This protocol is often used by Microsoft-compatible systems.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_enable_natpmp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Allow PCP/NAT-PMP Port Mapping");?></td>
                      <td>
                       <input name="enable_natpmp" type="checkbox" value="yes" <?=!empty($pconfig['enable_natpmp']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_enable_natpmp">
                         <?=gettext("This protocol is often used by Apple-compatible systems.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ext_iface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("External Interface");?></td>
                      <td>
                       <select class="selectpicker" name="ext_iface">
<?php
                        foreach (get_configured_interface_with_descr() as $iface => $ifacename):?>
                          <option value="<?=$iface;?>" <?=$pconfig['ext_iface'] == $iface ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($ifacename);?>
                          </option>
<?php
                        endforeach;?>
                       </select>
                       <div class="hidden" data-for="help_for_ext_iface">
                         <?=gettext("Select only your primary WAN interface (interface with your default route). Only one interface is allowed here, not multiple.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_iface_array" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interfaces (generally LAN)");?></td>
                      <td>
                       <select class="selectpicker" name="iface_array[]" multiple="multiple">
                         <option value="lo0" <?=!empty($pconfig['iface_array']) && in_array('lo0', $pconfig['iface_array']) ? "selected=\"selected\"" : "";?>>
                           <?=gettext("Localhost");?>
                         </option>
<?php
                        foreach (get_configured_interface_with_descr() as $iface => $ifacename):?>
                          <option value="<?=$iface;?>" <?=!empty($pconfig['iface_array']) && in_array($iface, $pconfig['iface_array']) ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($ifacename);?>
                          </option>
<?php
                        endforeach;?>
                       </select>
                       <div class="hidden" data-for="help_for_ext_iface">
                         <?=gettext("You can select multiple interfaces here.");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_overridesubnet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface subnet override");?></td>
                      <td>
                        <select name="overridesubnet" class="selectpicker" id="overridesubnet">
                          <option value="" <?= empty($pconfig['overridesubnet']) ? 'selected="selected"' : '' ?>><?= gettext('default') ?></option>
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
                      <td><a id="help_for_stun_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('STUN server') ?></td>
                      <td>
                        <input name="stun_host" type="text" value="<?= !empty($pconfig['stun_host']) ? $pconfig['stun_host'] : '' ?>" />
                        <div class="hidden" data-for="help_for_stun_host">
                          <?= gettext('STUN server used to predict external WAN IP.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_stun_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('STUN port') ?></td>
                      <td>
                        <input name="stun_port" type="text" placeholder="3478" value="<?= !empty($pconfig['stun_port']) ? $pconfig['stun_port'] : ''  ?>" />
                        <div class="hidden" data-for="help_for_stun_port">
                          <?= gettext('STUN port used to predict external WAN IP.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_download" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Maximum Download Speed");?></td>
                      <td>
                        <input name="download" type="text" value="<?=$pconfig['download'];?>" />
                        <div class="hidden" data-for="help_for_download">
                          <?=gettext("(Kbits/second)");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_upload" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Maximum Upload Speed");?></td>
                      <td>
                        <input name="upload" type="text" value="<?=$pconfig['upload'];?>" />
                        <div class="hidden" data-for="help_for_upload">
                          <?=gettext("(Kbits/second)");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Override WAN address");?></td>
                      <td>
                        <input name="overridewanip" type="text" value="<?=$pconfig['overridewanip'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_logpackets" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Log packets");?></td>
                      <td>
                       <input name="logpackets" type="checkbox" value="yes" <?=!empty($pconfig['logpackets']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_logpackets">
                         <?=gettext("Log packets handled by service rules?");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_sysuptime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use system time");?></td>
                      <td>
                       <input name="sysuptime" type="checkbox" value="yes" <?=!empty($pconfig['sysuptime']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_sysuptime">
                         <?=gettext("Use system uptime instead of service uptime?");?>
                       </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_permdefault" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Default deny");?></td>
                      <td>
                       <input name="permdefault" type="checkbox" value="yes" <?=!empty($pconfig['permdefault']) ? "checked=\"checked\"" : ""; ?> />
                       <div class="hidden" data-for="help_for_permdefault">
                         <?=gettext("By default deny access to service?");?>
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
                      <th colspan="2"><?=gettext("User specified permissions");?></th>
                    </tr>
                  </thead>
                  <tbody>
<?php foreach (miniupnpd_permuser_list() as $i => $permuser): ?>
                    <tr>
<?php if ($i == 1): ?>
                      <td style="width:22%"><a id="help_for_permuser" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Entry') . ' ' . $i ?></td>
<?php else: ?>
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('Entry') . ' ' . $i ?></td>
<?php endif ?>
                      <td style="width:78%">
                        <input name="<?= html_safe($permuser) ?>" type="text" value="<?= $pconfig[$permuser] ?>" />
<?php if ($i == 1): ?>
                        <div class="hidden" data-for="help_for_permuser">
                          <?=gettext("Format: [allow or deny] [ext port or range] [int ipaddr or ipaddr/cidr] [int port or range]");?><br/>
                          <?=gettext("Example: allow 1024-65535 192.168.0.0/24 1024-65535");?>
                        </div>
<?php endif ?>
                      </td>
                    </tr>
<?php endforeach ?>
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
                       <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
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
