<?php

/*
 * Copyright (C) 2020 Deciso B.V.
 * Copyright (C) 2020 D. Domig
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
 * THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
require_once("widgets/include/wireguard.inc");

$data = trim(configd_run('wireguard showhandshake'));

$empty = strlen($data) == 0;
?>

<table class="table table-striped table-condensed">
    <thead>
        <tr>
            <th><?= gettext("Interface") ?></th>
            <th><?= gettext("Endpoint") ?></th>
            <th><?= gettext("Latest Handshake") ?></th>
        </tr>
    </thead>
    <tbody>

<?php if (!$empty):
    $handshakes = explode("\n", $data);

    foreach ($handshakes as $handshake):
        $item = explode("\t", $handshake);

        $epoch = $item[2];
        $latest = "-";
        if ($epoch > 0):
            $dt = new DateTime("@$epoch");
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $latest = $dt->format(gettext("Y-m-d H:i:sP"));
        endif; ?>

    <tr>
        <td><?= $item[0] ?></td>
        <td><?= gettext(substr($item[1], 0, 10)) ?>...</td>
        <td><?= $latest ?></td>
    </tr>

    <?php endforeach; ?>

<?php else: ?>

    <tr>
        <td colspan="3"><?= gettext("No WireGuard instance defined or enabled.") ?></td>
    </tr>

<?php endif; ?>

    </tbody>
</table>
