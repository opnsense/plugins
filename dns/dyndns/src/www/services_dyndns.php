<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Ermal Luçi
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

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("services.inc");
require_once("system.inc");
require_once("plugins.inc.d/dyndns.inc");

$a_dyndns = &config_read_array('dyndnses', 'dyndns');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del" && isset($_POST['id'])) {
        if (!empty($a_dyndns[$_POST['id']])) {
            $conf = $a_dyndns[$_POST['id']];
            @unlink(dyndns_cache_file($conf, 4));
            @unlink(dyndns_cache_file($conf, 6));
            unset($a_dyndns[$_POST['id']]);
            write_config();
            system_cron_configure();
        }
        exit;
    } elseif (isset($_POST['act']) && $_POST['act'] == "toggle" && isset($_POST['id'])) {
        if (!empty($a_dyndns[$_POST['id']])) {
            if (!empty($a_dyndns[$_POST['id']]['enable'])) {
                $a_dyndns[$_POST['id']]['enable'] = false;
            } else {
                $a_dyndns[$_POST['id']]['enable'] = true;
            }
            write_config();
            system_cron_configure();
            if ($a_dyndns[$_POST['id']]['enable']) {
                $a_dyndns[$_POST['id']]['force'] = true;
                dyndns_configure_client($a_dyndns[$_POST['id']]);
            }
        }
        exit;
    }
}

include("head.inc");

legacy_html_escape_form_data($a_dyndns);

$main_buttons = array(
    array('label' => gettext('Add'), 'href' => 'services_dyndns_edit.php'),
);

?>
<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete service action
    $(".act_delete_service").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Dynamic DNS");?>",
        message: "<?=gettext("Do you really want to delete this entry?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });
    // link toggle buttons
    $(".act_toggle").click(function(event){
        event.preventDefault();
        $.post(window.location, {act: 'toggle', id:$(this).data("id")}, function(data) {
            location.reload();
        });
    });
    // watch scroll position and set to last known on page load
    watchScrollPosition();
  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?=gettext("Interface");?></th>
                      <th><?=gettext("Service");?></th>
                      <th><?=gettext("Hostname");?></th>
                      <th><?=gettext("Cached IP");?></th>
                      <th><?=gettext("Description");?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                    $i = 0;
                    foreach ($a_dyndns as $dyndns): ?>
                    <tr>
                      <td>
                        <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=(!empty($dyndns['enable'])) ? gettext("disable") : gettext("enable");?>">
                          <span class="glyphicon glyphicon-play <?=(!empty($dyndns['enable'])) ? "text-success" : "text-muted";?>"></span>
                        </a>
                        <?=!empty($config['interfaces'][$dyndns['interface']]['descr']) ? $config['interfaces'][$dyndns['interface']]['descr'] : strtoupper($dyndns['interface']);?>
                      </td>
                      <td><?=dyndns_list()[$dyndns['type']];?></td>
                      <td><?=$dyndns['host'];?></td>
                      <td>
<?php
                      $filename = dyndns_cache_file($dyndns, 4);
                      $fdata = '';
                      if (file_exists($filename) && !empty($dyndns['enable'])) {
                          $ipaddr = get_dyndns_ip($dyndns['interface'], 4);
                          $fdata = @file_get_contents($filename);
                      }

                      $filename_v6 = dyndns_cache_file($dyndns, 6);
                      $fdata6 = '';
                      if (file_exists($filename_v6) && !empty($dyndns['enable'])) {
                          $ipv6addr = get_dyndns_ip($dyndns['interface'], 6);
                          $fdata6 = @file_get_contents($filename_v6);
                      }

                      if (!empty($fdata)) {
                          $cached_ip_s = explode('|', $fdata);
                          $cached_ip = $cached_ip_s[0];
                          echo sprintf(
                              '<font color="%s">%s</font>',
                              $ipaddr != $cached_ip ? 'red' : 'green',
                              htmlspecialchars($cached_ip)
                          );
                      } elseif (!empty($fdata6)) {
                          $cached_ipv6_s = explode('|', $fdata6);
                          $cached_ipv6 = $cached_ipv6_s[0];
                          echo sprintf(
                              '<font color="%s">%s</font>',
                              $ipv6addr != $cached_ipv6 ? 'red' : 'green',
                              htmlspecialchars($cached_ipv6)
                          );
                      } else {
                          echo sprintf('<span class="text-muted">%s</span>', gettext('N/A'));
                      }?>
                      </td>
                      <td><?=$dyndns['descr'];?></td>
                      <td>
                        <a href="services_dyndns_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                        <a href="#" data-id="<?=$i;?>" class="act_delete_service"><button type="button" class="btn btn-xs btn-default"><span class="fa fa-trash text-muted"></span></button></a>
                      </td>
                    </tr>
<?php
                      $i++;
                    endforeach; ?>
                  </tbody>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
