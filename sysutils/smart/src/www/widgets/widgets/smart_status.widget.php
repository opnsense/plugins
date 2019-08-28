<?php

/*
 * Copyright (C) 2018 Smart-Soft
 * Copyright (C) 2014 Deciso B.V.
 * Copyright 2012 mkirbst @ pfSense Forum
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
require_once("widgets/include/smart_status.inc");

?>

<table class="table table-striped" style="width:100%; border:0; cellpadding:0; cellspacing:0">
    <tr>
        <td class="widgetsubheader" style="text-align:center"><b><?php echo gettext("Drive") ?></b></td>
        <td class="widgetsubheader" style="text-align:center"><b><?php echo gettext("Ident") ?></b></td>
        <td class="widgetsubheader" style="text-align:center"><b><?php echo gettext("SMART Status") ?></b></td>
    </tr>

<?php
$devs = json_decode (configd_run ("smart detailed list"));

foreach ($devs as $dev) {
    $dev_state_translated = "";

    switch ($dev->state) {
    case "PASSED":
    case "OK":
        $dev_state_translated = gettext('OK');
        $color = "success";
        break;
    case "":
        $dev_state_translated = gettext('Unknown');
        $color = "warning";
        break;
    default:
        $color = "danger";
        break;
    }
?>
        <tr>
            <td><?= $dev->device ?></td>
            <td style="text-align:center"><?= $dev->ident ?></td>
            <td style="text-align:center"><span class="label label-<?= $color ?>">&nbsp;<?= $dev_state_translated ?>&nbsp;</span></td>
        </tr>
<?php
}
?>
</table>
