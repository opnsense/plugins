<?php

/**
 *    Copyright (C) 2023 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

?>

<script>
    $(document).ready(function() {
        ajaxGet('/api/dechw/info/powerStatus', {}, function(data, status) {
            if (data['status'] === 'failed') {
                $('#status').html(data['status_translated']);
                $('.pwr-container').hide();
                return;
            }

            ['pwr1', 'pwr2'].forEach(function(key) {
                let status = data[key];
                let status_translated = data[key + '_translated'];
                let $power = $('<span data-toggle="tooltip" title=""></span>').addClass('power fa fa-power-off fa-lg');
                $power.css('color', status === '1' ? 'blue' : 'red');
                $power.attr('title', status_translated);
                $('#'+key).append($power);
            })

            $(".circle").tooltip({container: 'body', trigger: 'hover'});
        });
    });
</script>

<style>
    #status {
        margin: 10px;
    }

    .power {
      margin: 5px;
      float: right;
    }

    .power:hover {
      opacity: 0.5;
    }

    .pwr-container {
      margin: 5px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .data-item {
      padding: 10px;
      border: 1px solid #ddd;
      margin: 5px;
      width: 50%;
      display: inline-block;
    }
</style>

<div id="status"></div>
<div class="pwr-container">
    <div id="pwr1" class="data-item">
        <strong><?=gettext("Power Supply 1");?></strong>
    </div>
    <div id="pwr2" class="data-item">
        <strong><?=gettext("Power Supply 2");?></strong>
    </div>
</div>
