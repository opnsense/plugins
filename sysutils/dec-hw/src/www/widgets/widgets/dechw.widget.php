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
                $('#pwr-table').hide();
                return;
            }

            ['pwr1', 'pwr2'].forEach(function(key) {
                let status = data[key];
                let status_translated = data[key + '_translated'];
                let $circle = $('<div data-toggle="tooltip" title=""></div>').addClass('circle');

                $circle.css('background-color', status === '1' ? 'blue' : 'red');
                $circle.attr('title', status_translated);

                $('#'+key).append($('<td></td>').append($circle));
            })

            $(".circle").tooltip({container: 'body', trigger: 'hover'});
        });
    });
</script>

<style>
    .circle {
      background-color: red;
      width: 15px;
      height: 15px;
      border-radius: 50%;
      box-shadow: none;
      margin: 5px;
    }

    .circle:hover {
      box-shadow: inset 0 0 20px rgba(0,0,0,0.7);
    }

</style>

<div>
    <div id="status"></div>
    <table id="pwr-table" class="table table-striped table-condensed">
        <tbody>
            <tr><th colspan="2"><?=gettext("Power Status");?></th></tr>
            <tr id="pwr1"><td style="width: 30%;"><?=gettext("Power Supply 1");?></td><tr>
            <tr id="pwr2"><td style="width: 30%;"><?=gettext("Power Supply 2");?></td><tr>
        </tbody>
    </table>
</div>
