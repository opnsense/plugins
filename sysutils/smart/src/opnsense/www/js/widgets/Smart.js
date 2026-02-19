/*
 * Copyright (C) 2024 Francisco Dimattia <info@tecnoservicio.com.ar>
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

export default class Smart extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 300;
    }

    getMarkup() {
        const $container = $('<div></div>');

        const headers = [
            this.translations?.device || 'Device',
            this.translations?.status || 'SMART Status'
        ];

        const $smarttable = this.createTable('smart-table', {
            headers: headers
        });

        $smarttable.addClass('table table-striped table-condensed table-hover');

        $container.append($smarttable);
        return $container;
    }

    parseSmartAttributes(output) {
        const attrs = {};
        if (!output) return attrs;

        const lines = output.split('\n');
        lines.forEach(line => {
            const match = line.match(/^\s*(\d{1,3})\s+([A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*)\s+0x[0-9a-f]+\s+\d+\s+\d+\s+\d+\s+.*\s+([-]?\d+)\s*$/);
            if (match) {
                const [, id, name, raw] = match;
                attrs[id.trim()] = { name: name.trim(), raw: raw.trim() };
            }
        });
        return attrs;
    }

    async onWidgetTick() {
        try {
            const listResp = await this.ajaxCall(`/api/smart/service/list/detailed`, {}, 'POST');
            const devices = listResp?.devices || [];

            const rows = [];
            for (const dev of devices) {
                const deviceName = dev.device || 'Unknown';
                const ident = dev.ident || 'N/A';
                let statusHtml = '<span class="text-muted">Unknown</span>';
                let tooltip = `Device: ${deviceName}\nSerial/Ident: ${ident}`;

                try {
                    const state = dev.state || {};
                    const health = state.smart_status?.passed ?? null;

                    if (health !== null) {
                        const icon = health 
                            ? '<i class="fa fa-check-circle text-success fa-lg"></i>' 
                            : '<i class="fa fa-exclamation-circle text-danger fa-lg"></i>';
                        statusHtml = `<span style="font-size:130%; font-weight:bold;">${icon} ${health ? 'OK' : 'FAILED'}</span>`;
                    }

                    const infoBody = JSON.stringify({
                        type: 'A',
                        device: deviceName
                    });

                    const infoResp = await this.ajaxCall(`/api/smart/service/info`, infoBody, 'POST');

                    if (infoResp?.output && typeof infoResp.output === 'string' && infoResp.output.trim()) {
                        const attrs = this.parseSmartAttributes(infoResp.output);

                        tooltip += '\n\nKey Attributes:';
                        if (attrs['194']) tooltip += `\nTemperature: ${attrs['194'].raw} °C`;
                        if (attrs['9']) tooltip += `\nPower-On Hours: ${attrs['9'].raw} h`;
                        if (attrs['5']) tooltip += `\nReallocated Sectors: ${attrs['5'].raw}`;
                        if (attrs['197']) tooltip += `\nCurrent Pending: ${attrs['197'].raw}`;
                        if (attrs['198']) tooltip += `\nOffline Uncorrectable: ${attrs['198'].raw}`;
                        if (attrs['199']) tooltip += `\nUDMA CRC Errors: ${attrs['199'].raw}`;
                    } else {
                        tooltip += '\n\n(No detailed attributes available)';
                    }

                } catch (e) {
                    tooltip += `\n\nError fetching details: ${e.message || 'Unknown'}`;
                }

                const escapedTooltip = tooltip.replace(/"/g, '&quot;').replace(/\n/g, '<br>');
                const deviceHtml = `<span data-toggle="tooltip" data-placement="right" data-html="true" title="${escapedTooltip}">${deviceName}</span>`;

                rows.push([deviceHtml, statusHtml]);
            }

            rows.sort((a, b) => String(a[0]).localeCompare(String(b[0])));
            super.updateTable('smart-table', rows);

            $('[data-toggle="tooltip"]').tooltip({container: 'body', html: true});

        } catch (err) {
            super.updateTable('smart-table', [[
                '<span class="text-danger">Widget error</span>',
                `<span class="text-danger">${err.message || 'Unknown'}</span>`
            ]]);
        }
    }
}
