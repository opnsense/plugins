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
        this.disks = null;
    }

    getMarkup() {
        const $container = $('<div></div>');
        const $smarttable = this.createTable('smart-table', {
            headerPosition: 'left',
        });
        $container.append($smarttable);
        return $container;
    }

    async onWidgetTick() {
        if (this.disks && this.disks.devices) {
            for (const device of this.disks.devices) {
                try {
                    const data = await this.ajaxCall('/api/smart/service/info', { type: "H", device });
                    const health = data.output.includes("PASSED");
                    $(`#${device}`).css({ color: health ? "green" : "red", fontSize: '150%' });
                    $(`#${device}`).text(health ? "OK" : "FAILED");
                } catch (error) {
                    super.updateTable('smart-table', [[["Error"], $(`<span>${this.translations.nosmart} ${device}: ${error}</span>`).prop('outerHTML')]]);
                }
            }
        }
    }

    async onMarkupRendered() {
        try {
            this.disks = await this.ajaxCall('/api/smart/service/list', {});
            const rows = [];
            for (const device of this.disks.devices) {
                const field = $(`<span id="${device}">`).prop('outerHTML');
                rows.push([[device], field]);
            }
            super.updateTable('smart-table', rows);
        } catch (error) {
            super.updateTable('smart-table', [[["Error"], $(`<span>${this.translations.nodisk}: ${error}</span>`).prop('outerHTML')]]);
        }
    }
}
