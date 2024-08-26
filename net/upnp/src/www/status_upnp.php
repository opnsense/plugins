<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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
require_once("plugins.inc.d/miniupnpd.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['clear'])) {
        miniupnpd_stop();
        miniupnpd_start();
        header(url_safe('Location: /status_upnp.php'));
        exit;
    }
}

$rdr_entries = array();
exec("/sbin/pfctl -aminiupnpd -sn", $rdr_entries, $pf_ret);

$service_hook = 'miniupnpd';
include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box">
<?php
          if (empty($config['installedpackages']['miniupnpd']['config'][0]['iface_array']) || empty($config['installedpackages']['miniupnpd']['config'][0]['enable'])): ?>
          <header class="content-box-head container-fluid">
            <h3><?= gettext('UPnP is currently disabled.') ?></h3>
          </header>
<?php
          else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-condensed table-hover">
              <thead>
                <tr>
                  <td><?=gettext("Ext. Port")?></td>
                  <td><?=gettext("Internal IP")?></td>
                  <td><?=gettext("Int. Port")?></td>
                  <td><?=gettext("Protocol")?></td>
                  <td><?=gettext("Source IP")?></td>
                  <td><?=gettext("Source Port")?></td>
                  <td><?=gettext("Description")?></td>
                </tr>
              </thead>
              <tbody>
<?php
              foreach ($rdr_entries as $rdr_entry):
                  if (!preg_match("/on (?P<iface>.*) inet proto (?P<proto>.*) from (?P<srcaddr>.*) (port (?P<srcport>.*) )?to (?P<extaddr>.*) port = (?P<extport>.*) keep state (label \"(?P<descr>.*)\" )?rtable [0-9] -> (?P<intaddr>.*) port (?P<intport>.*)/", $rdr_entry, $matches)) {
                      continue;
                  }
              ?>
                <tr>
                  <td><?= html_safe($matches['extport']) ?></td>
                  <td><?= html_safe($matches['intaddr']) ?></td>
                  <td><?= html_safe($matches['intport']) ?></td>
                  <td><?= html_safe(strtoupper($matches['proto'])) ?></td>
                  <td><?= html_safe($matches['srcaddr']) ?></td>
                  <td><?= html_safe($matches['srcport'] ?: "any") ?></td>
                  <td><?= html_safe($matches['descr']) ?></td>
                </tr>
<?php
              endforeach;?>
              </tbody>
              <tfoot>
                  <tr>
                    <td colspan="7">
                      <form method="post">
                        <button type="submit" name="clear" id="clear" class="btn btn-primary" value="Clear"><?=gettext("Clear");?></button>
                        <?=gettext("all currently connected sessions");?>.
                      </form>
                    </td>
                  </tr>
              </tfoot>
            </table>
          </div>
<?php
          endif; ?>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
