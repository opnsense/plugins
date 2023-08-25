<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2010 Yehuda Katz
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("widgets/include/wake_on_lan.inc");
require_once("interfaces.inc");

use OPNsense\Wol\Wol;
$wol = new Wol();
?>
<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th><?=gettext("Computer / Device");?></th>
      <th><?=gettext("Interface");?></th>
      <th><?=gettext("Status");?></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
<?php
    foreach ($wol->wolentry->iterateItems() as $wolent):
      $is_active = exec("/usr/sbin/arp -an |/usr/bin/grep -i {$wolent->mac}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");?>
    <tr>
        <td><?= !empty((string)$wolent->descr) ? $wolent->descr : gettext('Unnamed entry') ?><br/><?= $wolent->mac ?></td>
        <td><?=htmlspecialchars(convert_friendly_interface_to_friendly_descr((string)$wolent->interface));?></td>
        <td>
          <i class="fa fa-<?=$is_active == 1 ? "play" : "remove";?> fa-fw text-<?=$is_active == 1 ? "success" : "danger";?>" ></i>
          <?=$is_active == 1 ? gettext("Online") : gettext("Offline");?>
        </td>
        <td>
          <button class="btn btn-primary btn-xs wakeupbtn" data-mac="<?= $wolent->mac ?>" data-interface="<?= $wolent->interface ?>" data-uuid="<?= $wolent->getAttributes()['uuid'] ?>">
            <i class="fa fa-bolt fa-fw" title="<?=gettext("Wake Up");?>"></i>
          </button>
        </td>
    </tr>
<?php
    endforeach;
    if (count(iterator_to_array($wol->wolentry->iterateItems())) == 0):?>
    <tr>
      <td colspan="4" ><?=gettext("No saved WoL addresses");?></td>
    </tr>
<?php
    endif;?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4"><a href="ui/dhcpv4/leases" class="navlink"><?= gettext('DHCP Leases Status') ?></a></td>
    </tr>
  </tfoot>
</table>
<script>
    $("#dashboard_container").on("WidgetsReady", function() {
        $('.wakeupbtn').click(function(event) {
            event.preventDefault();
            var data = this.dataset;
            $.post('/api/wol/wol/set', {'uuid': data['uuid']}, function(result) {
            });
        })
    });
</script>
