<?php

/*
    Copyright (c) 2022 Cloudfence - Julio Camargo
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

?>
<table id="wazuh_agent-widget-table" class="table table-striped table-condensed" data-plugin="system" data-callback="wazuh_agent_widget">
  <tbody>
    <tr>
      <td><a href="/ui/firewall/alias_util/"><?=gettext("Current Blocked IPs (virusprot alias):");?></a> </td>
      <td class="text-center">
        <div class="list-group">
          <?php
            $lines = file("/tmp/wazuh_agent_ar.cache");
            foreach ($lines as $line) {
              $ip = trim($line); // Remove whitespace and newline characters
              echo '<a href="https://www.abuseipdb.com/check/' . $ip . '" target="_blank" class="list-group-item list-group-item-action"><i class="fa fa-ban"></i> ' . $ip . '</a>';
            }
          ?>
        </div>
      </td>
    </tr>
  </tbody>
</table>




