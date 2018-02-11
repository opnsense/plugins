<?php

/*
    NMAP Plugin by Brentt Graeb <support@pfmonitor.com>
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
    Copyright (C) 2006 Eric Friesen
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

$nmapctl = "nmap";

include("head.inc");
?>

<body>

<?php
include("fbegin.inc");

?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">

      <section class="col-xs-12">

<?

// Highlates the words "PASSED", "FAILED", and "WARNING".
function add_colors($string)
{
    // To add words keep arrayes matched by numbers
    $patterns[0] = '/open/';
    $patterns[1] = '/filtered/';
    $patterns[2] = '/closed/';
    $patterns[3] = '/Host is up/';
    $patterns[4] = '/Host seems down./';
    $replacements[0] = '<b><font color="#00cc00">' . gettext("OPEN") . '</font></b>';
    $replacements[1] = '<b><font color="#ff0000">' . gettext("FILTERED") . '</font></b>';
    $replacements[2] = '<b><font color="#ff0000">' . gettext("CLOSED") . '</font></b>';
    $replacements[3] = '<b><font color="#00cc00">' . gettext("HOST IS UP") . '</font></b>';
    $replacements[4] = '<b><font color="#00cc00">' . gettext("Host seems down.") . '</font></b>';
    ksort($patterns);
    ksort($replacements);
    return preg_replace($patterns, $replacements, $string);
}

// What page, aka. action is being wanted
// If they "get" a page but don't pass all arguments, smartctl will throw an error
$action = (isset($_POST['action']) ? $_POST['action'] : $_GET['action']);
$target = preg_replace("/[^A-Za-z0-9 \/.:-]/", '', $_POST['target']);

switch($action) {
  // Testing devices
  case 'basicping':
  {
    $output = add_colors(shell_exec($nmapctl . " -sS " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'basiclongping':
  {
    $output = add_colors(shell_exec($nmapctl . " -sS -p- " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'basic':
  {
    $output = add_colors(shell_exec($nmapctl . " -sS -Pn " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'basiclong':
  {
    $output = add_colors(shell_exec($nmapctl . " -sS -Pn -p- " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'tcp':
  {
    $output = add_colors(shell_exec($nmapctl . " -sT " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'udp':
  {
    $output = add_colors(shell_exec($nmapctl . " -sU " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'arp':
  {
    $output = add_colors(shell_exec("arp -a"));
    echo '<pre>ARP List Dump<br>' . $output . '
    </pre>';
    break;
  }

  // Testing devices
  case 'web':
  {
    $output = add_colors(shell_exec($nmapctl . " -sS -Pn -p 22,80,88,139,443,445,3389,5000,5001,8000,8080,8443,8888,10000 " . $target));
    echo '<pre>Scanning ' . $target . $output . '
    </pre>';
    break;
  }

  // Default page, prints the forms to view info, test, etc...
  default:
  {

    if (true) {
    ?>
<script>
function checktype() {
    if (document.getElementById("action").value != "arp"){
        document.getElementById("target").disabled = false;
    }
    if (document.getElementById("action").value == "arp"){
        document.getElementById("target").value = "";
        document.getElementById("target").disabled = true;
    }
}
</script>
            <div class="content-box tab-content table-responsive">
              <form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="iform" id="iform">
                <table class="table table-striped __nomb">
                   <tr>
                     <th colspan="2" style="vertical-align:top" class="listtopic"><?=gettext('Info'); ?></th>
                    </tr>
                  <tr>
                    <td><?=gettext("Scan Type:"); ?></td>
                    <td >
                      <select name="action" class="form-control" id="action" onchange="checktype()">
                        <option value="basicping">Basic SYN Scan</option>
                        <option value="basiclongping">Basic SYN Long Scan</option>
                        <option value="basic">Basic SYN Scan(skip ping)</option>
                        <option value="basiclong">Basic SYN Long Scan(skip ping)</option>
                        <option value="tcp">TCP Connect Scan</option>
                        <option value="udp">UDP Scan</option>
                        <option value="web">Scan Administrative Ports</option>
                        <option value="arp">ARP Listing</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><?=gettext("Target IP:"); ?></td>
                    <td >
                      <input type="text" name="target" id="target" class="form-control" placeholder="IP, CIDR, Range..." />
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%" style="vertical-align:top">&nbsp;</td>
                    <td style="width:78%">
                      <input type="submit" name="submit" class="btn btn-primary" value="<?=gettext("Scan"); ?>" />
                    </td>
                  </tr>
              </table>
              </form>
            </div>
      </section>

    <?php
    }
    break;
  }
}

// print back button on pages
if(isset($_POST['submit']) && $_POST['submit'] != "Save")
{
  echo '<br /><a class="btn btn-primary" href="' . $_SERVER['PHP_SELF'] . '">' . gettext("Back") . '</a>';
}
?>
<br />
<?php if ($ulmsg) echo "<p><strong>" . $ulmsg . "</strong></p>\n"; ?>

    </section>
  </div>
</div>
</section>


<?php include("foot.inc"); ?>
