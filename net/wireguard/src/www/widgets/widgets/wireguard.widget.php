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

// All WG Widget Columns to display. (ID<>User-facing name)
$wg_widget_columns_all = [
    'name'          => gettext("Name"),
    'interface'     => gettext("Interface"),
    'peerName'      => gettext("Peer"),
    'peerPublicKey' => gettext("Public Key"),
    'lastHandshake' => gettext("Latest Handshake"),
];

// Retrieve the current settings and fallback to default if not set
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $wg_widget = $config['widgets']['wireguard'];
    $wg_widget_truncPublicKey = (isset($wg_widget['truncate_publickey'])) ? $wg_widget['truncate_publickey'] : 19;
    $wg_widget_columns = !empty($wg_widget['column_filter']) ? explode(',', $wg_widget['column_filter']) : [];

// Used when changes were submitted, and if POST is because changes to WG widget
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["wg_widget_save"])) {
    $wg_widget_settings = $_POST;

    // set the columns we filter/hide
    if (!empty($wg_widget_settings['column_filter'])) {
        $config['widgets']['wireguard']['column_filter'] = implode(',', $wg_widget_settings['column_filter']);
    } elseif (isset($config['widgets']['wireguard']['column_filter'])) {
        unset($config['widgets']['wireguard']['column_filter']);
    }

    // set to how many characters we truncate the key
    if ($wg_widget_settings['truncate_publickey'] !== false) {
        if (is_numeric($wg_widget_settings['truncate_publickey']) && $wg_widget_settings['truncate_publickey'] <= 44 && $wg_widget_settings['truncate_publickey'] >= 0)
            $config['widgets']['wireguard']['truncate_publickey'] = (int) $wg_widget_settings['truncate_publickey'];
    } elseif ($config['widgets']['wireguard']['truncate_publickey'] === false) {
        unset($config['widgets']['wireguard']['truncate_publickey']);
    }

    write_config("Saved WireGuard Widget Filter via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

?>

<div id="wireguard-settings" class="widgetconfigdiv" style="display:none;">
    <form action="/widgets/widgets/wireguard.widget.php" method="post" name="iformd">
        <table class="table table-condensed">
            <tr>
                <td><?= gettext("Shorten public key to characters (max 40, 0 = disabled)") ?>:</td>
                <td><input type="number" id="truncate_publickey" name="truncate_publickey" value="<?= $wg_widget_truncPublicKey ?>"></td>
            </tr>
            <tr>
                <td><?= gettext("Hide columns") ?>:</td>
                <td>
                    <select id="column_filter" name="column_filter[]" multiple="multiple" class="selectpicker_widget">
                        <?php foreach ($wg_widget_columns_all as $c_id => $c_name): ?>
                            <option value="<?= $c_id ?>" <?= in_array($c_id, $wg_widget_columns) ? 'selected="selected"' : '' ?>><?= html_safe($c_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <input type="hidden" name="wg_widget_save" value="1">
                    <button id="submitd" name="submitd" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
                </td>
            </tr>
        </table>
    </form>
</div>

<table id="wg-widget-table" class="table table-striped table-condensed">
    <thead>
        <tr>
            <?php
            foreach ($wg_widget_columns_all as $c_id => $c_name) {
                if (!in_array($c_id, $wg_widget_columns)) {
                    echo '<th data-id="'.$c_id.'">'.html_safe($c_name).'</th>';
                }
            }
            ?>
        </tr>
    </thead>
    <tbody id="wg-table-tbody">

    <?php if (!$enabled): ?>
    <tr>
        <td colspan="<?= count($wg_widget_columns_all) ?>">
            <?= gettext("No WireGuard instance defined or enabled.") ?>
        </td>
    </tr>
    <?php endif; ?>

    </tbody>
</table>

<script>
$(window).on("load", function() {
    $("#wireguard-configure").removeClass("disabled");

    function wgGenerateRow(data)
    {
        var hideColumns = '<?= implode(",", $wg_widget_columns) ?>'.split(',');
        var truncatePeerPublicKey = <?= $wg_widget_truncPublicKey ?>;

        var tr = '<tr>';
        for (var column in data) {
            // skip column if hidden
            if (hideColumns.includes(column)) {
                continue;
            }
            // set default values to display
            title = '';
            value = data[column];
            // if it's the peerPublicKey, we do special formatting
            if (column === "peerPublicKey" && truncatePeerPublicKey > 0 && truncatePeerPublicKey < 44) {
                title = data[column];
                value = data[column].slice(0, truncatePeerPublicKey) + '...';
            }
            // building column
            tr += '    <td title="' + title + '">' + value + '</td>';
        }
        tr += '</tr>';

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
            row += wgGenerateRow({
                name: obj.name,
                interface: obj.interface,
                peerName: peer.name,
                peerPublicKey: peer.publicKey,
                lastHandshake: peer.lastHandshake
            });
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
