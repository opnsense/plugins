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
        const $table = this.createTable('smart-table', {
            headers: ['Device', 'SMART Status']
        });
        $table.addClass('table table-striped table-condensed table-hover');
        return $('<div>').append($table);
    }

    getAttrRaw(output, id) {
        const attr = output?.ata_smart_attributes?.table?.find(a => a.id === id);
        return attr?.raw?.string ?? 'N/A';
    }

    async onWidgetTick() {
        try {
            const {devices = []} = await this.ajaxCall('/api/smart/service/list/detailed', {}, 'POST') || {};

            const rows = await Promise.all(devices.map(async dev => {
                const name = dev.device || 'Unknown';
                const ident = dev.ident || 'N/A';
                let status = '<span class="text-muted">Unknown</span>';
                let tip = `Device: ${name}\nSerial: ${ident}\n\nKey attributes:`;

                const health = dev.state?.smart_status?.passed;
                if (health !== undefined) {
                    const icon = health ? 'check-circle text-success' : 'exclamation-circle text-danger';
                    status = `<span style="font-size:130%;font-weight:bold"><i class="fa fa-${icon} fa-lg"></i> ${health ? 'OK' : 'FAILED'}</span>`;
                }

                const resp = await this.ajaxCall('/api/smart/service/info', JSON.stringify({device: name, type: 'a', json: '1'}), 'POST');
                const output = resp?.output;

                if (output) {
                    tip += `\nTemp: ${output.temperature?.current ?? 'N/A'} °C`;
                    tip += `\nPower-On: ${output.power_on_time?.hours ?? 'N/A'} h`;
                    tip += `\nReallocated: ${this.getAttrRaw(output, 5)}`;
                    tip += `\nPending: ${this.getAttrRaw(output, 197)}`;
                    tip += `\nUncorrectable: ${this.getAttrRaw(output, 198)}`;
                    tip += `\nCRC Errors: ${this.getAttrRaw(output, 199)}`;
                } else {
                    tip += '\n(No details available)';
                }

                const escaped = tip.replace(/"/g, '&quot;').replace(/\n/g, '<br>');
                return [`<span data-toggle="tooltip" data-html="true" title="${escaped}">${name}</span>`, status];
            }));

            super.updateTable('smart-table', rows.sort((a,b) => a[0].localeCompare(b[0])));
            $('[data-toggle="tooltip"]').tooltip({container: 'body'});
        } catch {
            super.updateTable('smart-table', [['<span class="text-danger">Error</span>', '<span class="text-danger">Widget failed</span>']]);
        }
    }
}
