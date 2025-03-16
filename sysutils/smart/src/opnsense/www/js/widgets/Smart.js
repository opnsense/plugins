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
        let disks = await this.ajaxCall(`/api/smart/service/${'list/detailed'}`, {}, 'POST');
        if (disks && disks.devices) {
            const rows = [];
            for (const device of disks.devices) {
                try {
                    const health = device.state.smart_status.passed;
                    const text = health ? "OK" : "FAILED";
                    const css = { color: health ? "green" : "red", fontSize: '150%' };
                    const field = $(`<span id="${device.device}">`).text(text).css(css).prop('outerHTML');
                    rows.push([[device.device], field]);
                } catch (error) {
                    super.updateTable('smart-table', [[["Error"], $(`<span>${this.translations.nosmart} ${device.device}: ${error}</span>`).prop('outerHTML')]]);
                }
            }
            super.updateTable('smart-table', rows);
        }
    }
}
