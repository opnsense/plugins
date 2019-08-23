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

require_once('guiconfig.inc');
require_once('widgets/include/smart_status.inc');

$devs = json_decode(configd_run('smart detailed list'));

?>

<table class="table table-striped table-condensed">
  <thead>
    <tr>
        <th><?= gettext('Drive') ?></td>
        <th><?= gettext('Ident') ?></td>
        <th><?= gettext('Status') ?></td>
    </tr>
  </thead>
  <tbody>

<?php foreach ($devs as $dev):

    $dev_state_translated = gettext('Unknown');
    $color = 'default';

    if (isset($dev->state->smart_status->passed)) {
        if ($dev->state->smart_status->passed) {
            $dev_state_translated = gettext('OK');
            $color = 'success';
        } else {
            $dev_state_translated = gettext('FAILED');
            $color = 'danger';
	}
    }

?>

    <tr>
      <td><?= html_safe($dev->device) ?></td>
      <td><?= html_safe($dev->ident) ?></td>
      <td><span class="label label-<?= $color ?>"><?= html_safe($dev_state_translated) ?></span></td>
    </tr>

<?php endforeach ?>

  </tbody>
</table>
