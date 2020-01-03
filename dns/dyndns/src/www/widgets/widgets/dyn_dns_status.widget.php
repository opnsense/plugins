<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2008 Ermal Luçi
 * Copyright (C) 2013 Stanley P. Miller \ stan-qaz
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
require_once("widgets/include/dyn_dns_status.inc");
require_once("services.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/dyndns.inc");

$a_dyndns = &config_read_array('dyndnses', 'dyndns');

if (!empty($_REQUEST['getdyndnsstatus'])) {
    $first_entry = true;
    foreach ($a_dyndns as $dyndns) {
        if ($first_entry) {
            $first_entry = false;
        } else {
            // Put a vertical bar delimiter between the echoed HTML for each entry processed.
            echo '|';
        }

        $filename = dyndns_cache_file($dyndns, 4);
        $fdata = '';
        if (!empty($dyndns['enable']) && file_exists($filename)) {
            $ipaddr = get_dyndns_ip(dyndns_failover_interface($dyndns['interface'], 'all'), 4);
            $fdata = @file_get_contents($filename);
        }

        $filename_v6 = dyndns_cache_file($dyndns, 6);
        $fdata6 = '';
        if (!empty($dyndns['enable']) && file_exists($filename_v6)) {
            $ipv6addr = get_dyndns_ip(dyndns_failover_interface($dyndns['interface'], 'inet6'), 6);
            $fdata6 = @file_get_contents($filename_v6);
        }

        if (!empty($fdata)) {
            $cached_ip_s = explode('|', $fdata);
            $cached_ip = $cached_ip_s[0];
            echo sprintf(
                '<span class="%s">%s</span>',
                $ipaddr != $cached_ip ? 'text-danger' : '',
                htmlspecialchars($cached_ip)
            );
        } elseif (!empty($fdata6)) {
            $cached_ipv6_s = explode('|', $fdata6);
            $cached_ipv6 = $cached_ipv6_s[0];
            echo sprintf(
                '<span class="%s">%s</span>',
                $ipv6addr != $cached_ipv6 ? 'text-danger' : '',
                htmlspecialchars($cached_ipv6)
            );
        } else {
            echo gettext('N/A');
        }
    }
    exit;
}

?>

<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th style="word-break:break-word;"><?=gettext("Interface");?></th>
      <th style="word-break:break-word;"><?=gettext("Service");?></th>
      <th style="word-break:break-word;"><?=gettext("Hostname");?></th>
      <th style="word-break:break-word;"><?=gettext("Cached IP");?></th>
    </tr>
  </thead>
  <tbody>
<?php
  $iflist = get_configured_interface_with_descr();
  $types = dyndns_list();
  $groupslist = return_gateway_groups_array();
  foreach ($a_dyndns as $i => $dyndns) :?>
    <tr ondblclick="document.location='services_dyndns_edit.php?id=<?=$i;?>'">
      <td style="word-break:break-word;" <?= isset($dyndns['enable']) ? '' : 'class="text-muted"' ?>>
<?php
        foreach ($iflist as $if => $ifdesc) {
            if ($dyndns['interface'] == $if) {
                echo "{$ifdesc}";
                break;
            }
        }
        foreach ($groupslist as $if => $group) {
            if ($dyndns['interface'] == $if) {
                echo "{$if}";
                break;
            }
        }?>
      </td>
      <td style="word-break:break-word;" <?= isset($dyndns['enable']) ? '' : 'class="text-muted"' ?>>
<?php
        if (isset($types[$dyndns['type']])) {
            echo htmlspecialchars($types[$dyndns['type']]);
        } else {
            echo htmlspecialchars($dyndns['type']);
        }
?>
      </td>
      <td style="word-break:break-word;" <?= isset($dyndns['enable']) ? '' : 'class="text-muted"' ?>>
        <?= htmlspecialchars($dyndns['host']) ?>
      </td>
      <td style="word-break:break-word;" <?= isset($dyndns['enable']) ? '' : 'class="text-muted"' ?>>
        <div id='dyndnsstatus<?=$i;?>'>
          <?= gettext('Checking...') ?>
        </div>
      </td>
    </tr>
<?php
  endforeach;?>
  </tbody>
</table>
<script>
  function dyndns_getstatus()
  {
      scroll(0,0);
      var url = "/widgets/widgets/dyn_dns_status.widget.php";
      var pars = 'getdyndnsstatus=yes';
      jQuery.ajax(url, {type: 'get', data: pars, complete: dyndnscallback});
      // Refresh the status every 5 minutes
      setTimeout('dyndns_getstatus()', 5*60*1000);
  }
  function dyndnscallback(transport)
  {
      // The server returns a string of statuses separated by vertical bars
      var responseStrings = transport.responseText.split("|");
      for (var count=0; count<responseStrings.length; count++) {
          var divlabel = '#dyndnsstatus' + count;
          jQuery(divlabel).prop('innerHTML',responseStrings[count]);
      }
  }
  $(window).load(function() {
    // Do the first status check 2 seconds after the dashboard opens
    setTimeout('dyndns_getstatus()', 2000);
  });
</script>
