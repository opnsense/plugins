<?php

/*
 * Copyright (C) 2021 Nicola Pellegrini
 *
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
require_once("widgets/include/apcupsd.inc");

?>
<table id="apcupsd-widget" class="table table-striped table-condensed">
    <colgroup>
        <col class="apcupsd-widget-label" />
        <col />
    </colgroup>
    <tbody id="apcupsd-widget-tbody"></tbody>
</table>
<style>
    .apcupsd-widget-label {
        max-width: 20em;
        width: 30%;
        min-width: 10em;
    }

    .apcupsd-widget-highlight {
        font-weight: bold
    }
</style>
<script>
$(document).ready(() => {
    const withMomentJs = moment && moment.version && typeof moment === 'function';

    const $newCell = (content) => $('<td></td>').html(content);

    const $newLabel = (text) => {
        return $('<td></td>').text(text);
    };

    const $newRow = (content, label) => {
        return $('<tr></tr>').append(
            typeof label === 'string' ? $newLabel(label) : label,
            content
        );
    };

    const $newProgressBar = (width, text) => {
        const $text = $('<span class="text-center"></span>').text(text).css({
                position: 'absolute',
                left: 0,
                right: 0
            });
        const $bar = $('<div class="progress-bar"></div>').css({
            width: width,
            zIndex: 0
        });
        return $('<div class="progress"></div>').append($bar, $text);
    };

    const renderText = (data, label) => {
        const text = typeof data === 'string' ? data : data.value;
        return $newRow($('<td></td>').text(text), label);
    };

    const renderDateString = (data, label, format, parseFormat) => {
        let value = typeof data === 'string' ? data : (data.norm || data.value);
        let text;
        if (withMomentJs) {
            const m = moment(value, parseFormat);
            if (m.isValid()) {
                m.locale(window.navigator.language);
                text = m.format(format);
            }
        }
        return renderText(text || value, label);
    };

    const renderProgress = (data, label, progressText) => {
        const width = data.norm + '%';
        progressText = progressText || data.norm.toFixed(1) + ' %';
        const pb = $newProgressBar(width, progressText);
        return $newRow($newCell(pb), label);
    };

    const renderLoad = (loadpct, nompower) => {
        let text = loadpct.norm.toFixed(1) + ' %';
        if (nompower) {
            text += ' ( ~ ' + Math.round(loadpct.norm * nompower.norm / 100) + ' W )';
        }
        return renderProgress(loadpct, 'Load', text);
    };

    const renderHighlight = (text, label, okRegexp, errRegexp) => {
        const textEl = $('<span class="apcupsd-widget-highlight"></span>').text(text);
        if (okRegexp && okRegexp.exec(text)) {
            textEl.addClass('text-success');
        } else if (errRegexp && errRegexp.exec(text)) {
            textEl.addClass('text-danger');
        } else {
            textEl.addClass('text-warning');
        }
        return $newRow($('<td></td>').append(textEl), label);
    };

    const selfTestText = {
        BT: 'Failed due to battery capacity (BT)',
        NG: 'Failed due to overload (NG)',
        NO: 'No results',
    };

    const updateUi = (status) => {
        const rows = [];

        if (status.MODEL) {
            rows.push(renderText(status.MODEL, 'Model'));
        }

        if (status.STATUS) {
            rows.push(
                renderHighlight(
                    status.STATUS.value,
                    'Status',
                    /ONLINE/,
                    /ONBATT|LOWBATT|REPLACEBATT|NOBATT|SLAVEDOWN|COMMLOST/
                )
            );
        }

        if (status.SELFTEST) {
            const value = status.SELFTEST.value;
            const text = selfTestText[value] || value;
            if (value === 'NO') {
                rows.push(renderText(text, 'Self test'));
            } else {
                rows.push(renderHighlight(
                    text,
                    'Self test',
                    /OK/,
                    /BT|NG/
                ));
            }
        }

        if (status.LINEV) {
            rows.push(renderText(status.LINEV, 'Line voltage'));
        }

        if (status.LOADPCT) {
            rows.push(renderLoad(status.LOADPCT, status.NOMPOWER))
        }

        if (status.BCHARGE) {
            rows.push(renderProgress(status.BCHARGE, 'Battery level'));
        }

        if (status.TIMELEFT) {
            rows.push(renderText(status.TIMELEFT, 'Battery runtime'));
        }

        if (status.BATTV) {
            rows.push(renderText(status.BATTV, 'Battery voltage'));
        }

        if (status.BATTDATE) {
            rows.push(renderDateString(status.BATTDATE, 'Battery date', 'll', 'YYYY-MM-DD'));
        }

        if (status.ITEMP) {
            rows.push(renderText(status.ITEMP, 'Temperature'));
        }

        if (status.DATE) {
            rows.push(renderDateString(status.DATE, 'Status update', 'll LTS'));
        }

        $('#apcupsd-widget-tbody').html(rows);
    };

    const displayError = (message) => {
        const el = $('<tr></tr>').append($('<td class="text-center text-danger" colspan="2"></td>').text(message));
        $('#apcupsd-widget-tbody').html(el);
    };

    const refreshStatus = () => {
        ajaxCall('/api/apcupsd/service/getUpsStatus', {}, function(data, status) {
            if (status === 'success' && !data.error) {
                updateUi(data.status);
            } else {
                displayError(data ? (data.message || data.error) : 'request error');
            }
            setTimeout(refreshStatus, 5000);
        });
    };

    refreshStatus();
});
</script>
