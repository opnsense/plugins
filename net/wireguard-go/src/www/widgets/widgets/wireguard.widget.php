<?php

/*
 * Copyright (C) 2020 Deciso B.V.
 * Copyright (C) 2020 D. Domig
 * Copyright (C) 2022 Patrik Kernstock <patrik@kernstock.net>
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

$enabled = ($config["OPNsense"]["wireguard"]["general"]["enabled"] === "1" ? true : false);

?>

<table class="table table-striped table-condensed">
    <thead>
        <tr>
            <th><?= gettext("Name") ?></th>
            <th><?= gettext("Interface") ?></th>
            <th><?= gettext("Endpoint") ?></th>
            <th><?= gettext("Public Key") ?></th>
            <th><?= gettext("Latest Handshake") ?></th>
        </tr>
    </thead>
    <tbody id="wg-table-tbody">

    <?php if (!$enabled): ?>
    <tr>
        <td colspan="5"><?= gettext("No WireGuard instance defined or enabled.") ?></td>
    </tr>
    <?php endif; ?>

    </tbody>
</table>

<script>
$(window).on("load", function() {
    function wgGenerateRow(name, interface, peerName, publicKey, latestHandshake, status)
    {
        var tr = ''
        +'<tr>'
        +'    <td>' + name + '</td>'
        +'    <td>' + interface + '</td>'
        +'    <td>' + peerName  + '</td>'
        +'    <td style="overflow: hidden; text-overflow: ellipsis;" title="' + publicKey + '">' + publicKey  + '</td>'
        +'    <td>' + latestHandshake + '</td>'
        +'</tr>';

        return tr;
    }

    function wgUpdateStatusIf(obj)
    {
        // check if at least one peer is set. If not, ignore it.
        if (Object.keys(obj.peers).length == 0) {
            return '';
        }

        // generate row based on data
        row = '';
        for (var peerId in obj.peers) {
            var peer = obj.peers[peerId];

            // generate table row
            row += wgGenerateRow(
                obj.name,
                obj.interface,
                peer.name,
                peer.publicKey,
                peer.lastHandshake,
                status
            );
        }

        return row;
    }

    function wgUpdateStatus()
    {
        var table = '';
        ajaxGet("/api/wireguard/general/getStatus", {}, function(data, status) {
            if (status === 'success') {
                for (var interface in data.items) {
                    table += wgUpdateStatusIf(data.items[interface]);
                }
            }
            // update table accordingly
            document.getElementById("wg-table-tbody").innerHTML = table;
            setTimeout(wgUpdateStatus, 10000);
        });
    };

    <?php if ($enabled): ?>
    wgUpdateStatus();
    <?php endif; ?>
});
</script>
