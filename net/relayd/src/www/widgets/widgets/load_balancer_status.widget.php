<?php

/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2010 Jim Pingle
  Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
  Copyright (C) 2005-2008 Bill Marquette
  Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
  and Jonathan Watt <jwatt@jwatt.org>.
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
require_once("filter.inc");
require_once("plugins.inc.d/relayd.inc");

$now = time();
$year = date("Y");

$a_vs = &config_read_array('load_balancer', 'virtual_server');
$a_pool = &config_read_array('load_balancer', 'lbpool');
$rdr_a = relayd_get_lb_redirects();
$relay_hosts = relayd_get_lb_summary();

$lb_logfile = '/var/log/relayd.log';

$nentries = isset($config['syslog']['nentries']) ? $config['syslog']['nentries'] : 50;

?>
<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th width="10%" class="listhdrr"><?= gettext('Server') ?></th>
      <th width="10%" class="listhdrr"><?= gettext('Pool') ?></th>
      <th width="30%" class="listhdr"><?= gettext('Description') ?></th>
    </tr>
  </thead>
  <?php $i = 0; foreach ($a_vs as $vsent) :
?>
  <tr>
    <?php
        switch (trim($rdr_a[$vsent['name']]['status'])) {
            case 'active':
                $bgcolor = "#90EE90";  // lightgreen
                $rdr_a[$vsent['name']]['status'] = gettext("Active");
                break;
            case 'down':
                $bgcolor = "#F08080";  // lightcoral
                $rdr_a[$vsent['name']]['status'] = gettext("Down");
                break;
            default:
                $bgcolor = "#D3D3D3";  // lightgray
                 $rdr_a[$vsent['name']]['status'] = gettext('Unknown - relayd not running?');
        }
        ?>
    <td class="listlr">
      <?=$vsent['name'];?><br />
      <span style="background-color: <?=$bgcolor?>; display: block"><i><?= $rdr_a[$vsent['name']]['status'] ?></i></span>
      <?=$vsent['ipaddr'].":".$vsent['port'];?><br />
    </td>
    <td class="listr" align="center" >
    <table>
    <?php
        foreach ($a_pool as $pool) {
            if ($pool['name'] == $vsent['poolname']) {
                $pool_hosts=array();
                foreach ((array) $pool['servers'] as $server) {
                    $svr['ip']['addr']=$server;
                    $svr['ip']['state']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['state'];
                    $svr['ip']['avail']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['avail'];
                    $pool_hosts[]=$svr;
                }
                foreach ((array) $pool['serversdisabled'] as $server) {
                    $svr['ip']['addr']="$server";
                    $svr['ip']['state']='disabled';
                    $svr['ip']['avail']='disabled';
                    $pool_hosts[]=$svr;
                }
                asort($pool_hosts);
                foreach ((array) $pool_hosts as $server) {
                    if ($server['ip']['addr']!="") {
                        switch ($server['ip']['state']) {
                            case 'up':
                                $bgcolor = "#90EE90";  // lightgreen
                                $checked = "checked";
                                break;
                            case 'disabled':
                                $bgcolor = "#FFFFFF";  // white
                                $checked = "";
                                break;
                            default:
                                $bgcolor = "#F08080";  // lightcoral
                                $checked = "checked";
                        }
                        echo "<tr>";
                        echo "<td bgcolor=\"{$bgcolor}\">&nbsp;{$server['ip']['addr']}:{$pool['port']}&nbsp;</td><td bgcolor=\"{$bgcolor}\">&nbsp;";
                        if ($server['ip']['avail']) {
                            echo " ({$server['ip']['avail']}) ";
                        }
                        echo "&nbsp;</td></tr>";
                    }
                }
            }
        }
        ?>
    </table>
    </td>
    <td class="listbg" >
      <?=$vsent['descr'];?>
    </td>
  </tr>
  <?php $i++;

endforeach; ?>
</table>
