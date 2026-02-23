<?php

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("plugins.inc.d/rtsphelper.inc");

function rtsphelper_validate_ip($ip)
{
    /* validate cidr */
    $ip_array = array();
    $ip_array = explode('/', $ip);
    if (count($ip_array) == 2) {
        if ($ip_array[1] < 1 || $ip_array[1] > 32) {
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

function rtsphelper_validate_forward($forward)
{
    $fw_array = array();
    $fw_array = explode(':', $forward);

    if (!is_ipaddr($fw_array[0])) {
        return false;
    }

    $sub = $fw_array[1];
    if ($sub < 0 || $sub > 65535 || !is_numeric($sub)) {
        return false;
    }

    return true;
}

function rtsphelper_validate_port($port)
{
    foreach (explode('-', $port) as $sub) {
        if ($sub < 0 || $sub > 65535 || !is_numeric($sub)) {
            return false;
        }
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    $copy_fields = array('enable', 'ext_iface');

    foreach (rtsphelper_permuser_list() as $permuser) {
        $copy_fields[] = $permuser;
    }

    foreach (rtsphelper_forward_list() as $forward) {
        $copy_fields[] = $forward;
    }

    foreach ($copy_fields as $fieldname) {
        if (isset($config['installedpackages']['rtsphelper']['config'][0][$fieldname])) {
            $pconfig[$fieldname] = $config['installedpackages']['rtsphelper']['config'][0][$fieldname];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    /* user permissions validation */
    foreach (rtsphelper_permuser_list() as $i => $permuser) {
        if (!empty($pconfig[$permuser])) {
            $perm = explode(' ', $pconfig[$permuser]);
            /* should explode to 2 args */
            if (count($perm) != 2) {
                $input_errors[] = sprintf(gettext("You must follow the specified format in the 'User specified permissions %s' field"), $i);
            } else {
              /* verify port or port range */
              if (!rtsphelper_validate_port($perm[1]) ) {
                  $input_errors[] = sprintf(gettext("You must specify a port or port range between 0 and 65535 in the 'User specified permissions %s' field"), $i);
              }
              /* verify ip address */
              if (!rtsphelper_validate_ip($perm[0])) {
                  $input_errors[] = sprintf(gettext("You must specify a valid ip address in the 'User specified permissions %s' field"), $i);
              }
            }
        }
    }

    foreach (rtsphelper_forward_list() as $i => $forward) {
        if (!empty($pconfig[$forward])) {
            if (!rtsphelper_validate_forward($pconfig[$forward])) {
                $input_errors[] = sprintf(gettext("You must specify a valid ip and port in the 'Hosts to enable %s' field"), $i);
            }
        }
    }

    if (count($input_errors) == 0) {
        // save form data
        $rtsp = array();
        // boolean types
        foreach (array('enable') as $fieldname) {
            $rtsp[$fieldname] = !empty($pconfig[$fieldname]);
        }
        // text field types
        foreach (array('ext_iface') as $fieldname) {
            $rtsp[$fieldname] = $pconfig[$fieldname];
        }
        foreach (rtsphelper_permuser_list() as $fieldname) {
            $rtsp[$fieldname] = $pconfig[$fieldname];
        }
        foreach (rtsphelper_forward_list() as $forward) {
            $rtsp[$forward] = $pconfig[$forward];
        }
        // sync to config
        $config['installedpackages']['rtsphelper']['config'] = $rtsp;

        write_config('Modified RTSP Helper settings');
        rtsphelper_configure_do();
        filter_configure();
        header(url_safe('Location: /services_rtsphelper.php'));
        exit;
    }
}


$service_hook = 'rtsphelper';
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
                        <strong><?=gettext("RTSP Helper Settings");?></strong>
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
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable");?></td>
                      <td>
                       <input name="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?> />
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
                      <th colspan="2"><?=gettext("Hosts to enable");?></th>
                    </tr>
                  </thead>
                  <tbody>
<?php foreach (rtsphelper_forward_list() as $i => $forward): ?>
                    <tr>
<?php if ($i == 1): ?>
                      <td style="width:22%"><a id="help_for_forward" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Entry') . ' ' . $i ?></td>
<?php else: ?>
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('Entry') . ' ' . $i ?></td>
<?php endif ?>
                      <td style="width:78%">
                        <input name="<?= html_safe($forward) ?>" type="text" value="<?= $pconfig[$forward] ?>" />
<?php if ($i == 1): ?>
                        <div class="hidden" data-for="help_for_forward">
                          <?=gettext("Format: [ip:port]");?><br/>
                          <?=gettext("Example: 1.2.3.4:554");?>
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
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th colspan="2"><?=gettext("User specified permissions");?></th>
                    </tr>
                  </thead>
                  <tbody>
<?php foreach (rtsphelper_permuser_list() as $i => $permuser): ?>
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
                          <?=gettext("Format: [int ipaddr or ipaddr/cdir] [int port or range]");?><br/>
                          <?=gettext("Example: 192.168.0.0/24 1024-65535");?>
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
