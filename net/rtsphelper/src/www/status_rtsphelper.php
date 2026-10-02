<?php

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/rtsphelper.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['clear'])) {
        rtsphelper_stop();
        rtsphelper_start();
        header(url_safe('Location: /status_rtsphelper.php'));
        exit;
    }
}

$rdr_entries = array();
exec("/sbin/pfctl -artsphelper -sn", $rdr_entries, $pf_ret);

$service_hook = 'rtsphelper';
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
          if (empty($config['installedpackages']['rtsphelper']['config'][0]['enable'])): ?>
          <header class="content-box-head container-fluid">
            <h3><?= gettext('RTSP Helper is currently disabled.') ?></h3>
          </header>
<?php
          else: ?>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <td><?=gettext("Internal IP");?></td>
                  <td><?=gettext("Int. Port");?></td>
                </tr>
              </thead>
              <tbody>
<?php
              foreach ($rdr_entries as $rdr_entry):
                  if (!preg_match("/on (.*) inet proto (.*) from (.*) to (.*) port = (.*) -> (.*)/", $rdr_entry, $matches)) {
                      continue;
                  }
                  $rdr_ip = $matches[6];
                  $rdr_iport = $matches[5];
              ?>
                <tr>
                  <td><?=$rdr_ip;?></td>
                  <td><?=$rdr_iport;?></td>
                </tr>
<?php
              endforeach;?>
              </tbody>
              <tfoot>
                  <tr>
                    <td colspan="5">
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
