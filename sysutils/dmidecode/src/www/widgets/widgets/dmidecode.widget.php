<?php

/*
 * Copyright (C) 2019 Smart-Soft Ltd.
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


require_once("widgets/include/dmidecode.inc");

$dmiHW = [];
$dmiBIOS = [];

$hardwareData = parse_ini_string(configd_run("dmidecode system"));
$biosData = parse_ini_string(configd_run("dmidecode bios"));

$dmiHW[] = sprintf('%s %s %s',
    isset($hardwareData['Manufacturer']) ? $hardwareData['Manufacturer'] : '',
    isset($hardwareData['Product Name']) ? $hardwareData['Product Name'] : '',
    isset($hardwareData['Version']) ? $hardwareData['Version'] : ''
);

if(isset($hardwareData['Serial Number'])) {
    $dmiHW[] = 'SN: ' . $hardwareData['Serial Number'];
}

$dmiBios[] = sprintf('%s %s %s',
    isset($biosData['Vendor'])  ? $biosData['Vendor']  : '',
    isset($biosData['Version']) ? $biosData['Version'] : '',
    isset($biosData['BIOS Revision']) ? $biosData['BIOS Revision'] : ''
);

if(isset($biosData['Release Date'])) {
    $dmiBios[] = sprintf(gettext('Release date: %s'), $biosData['Release Date']);
}

?>

<table class="table table-striped table-condensed">
    <tbody>
        <tr>
            <td style="width: 30%;"><?=gettext("Hardware");?></td>
            <td><?=implode('<br/>', $dmiHW);?></td>
        </tr>
        <tr>
            <td><?=gettext("BIOS");?></td>
            <td><?=implode('<br/>', $dmiBios);?></td>
        </tr>
    </tbody>
</table>
