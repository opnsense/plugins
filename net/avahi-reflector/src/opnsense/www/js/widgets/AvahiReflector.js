/*
 * Copyright (C) 2026 cayossarian (Bill Flood)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
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

import BaseTableWidget from "./BaseTableWidget.js";

export default class AvahiReflector extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 30;
    }

    getGridOptions() {
        return {
            headerPosition: 'left'
        };
    }

    async onWidgetTick() {
        const response = await this.ajaxGet('/api/avahireflector/service/diagnostics');

        if (!response || response.status === 'error') {
            this.displayError(this.translations['unconfigured']);
            return;
        }

        const rows = [];

        const statusBadge = response.running
            ? `<span class="label label-opnsense label-opnsense--success">${this.translations['running']}</span>`
            : `<span class="label label-opnsense label-opnsense--danger">${this.translations['stopped']}</span>`;
        rows.push([this.translations['status'], statusBadge]);

        if (response.running) {
            if (response.pid !== null) {
                rows.push([this.translations['pid'], response.pid]);
            }
            if (response.uptime !== null) {
                rows.push([this.translations['uptime'], response.uptime]);
            }
            if (response.memory_mb !== null) {
                rows.push([this.translations['memory'], `${response.memory_mb} MB`]);
            }
        }

        rows.push([this.translations['domain'], response.domain || '-']);

        if (response.interfaces) {
            rows.push([this.translations['interfaces'], response.interfaces]);
        }

        const reflectorLabel = response.reflector_enabled
            ? `<span class="label label-opnsense label-opnsense--success">${this.translations['enabled']}</span>`
            : `<span class="label label-opnsense label-opnsense--danger">${this.translations['disabled']}</span>`;
        rows.push([this.translations['reflector'], reflectorLabel]);

        if (response.reflect_filters) {
            rows.push([this.translations['reflect_filters'], response.reflect_filters]);
        }

        if (response.mdns_repeater_running) {
            rows.push([
                `<span class="label label-opnsense label-opnsense--warning">${this.translations['conflict']}</span>`,
                this.translations['conflict_detail']
            ]);
        }

        super.updateTable(rows);
    }

    displayError(message) {
        super.updateTable([[message, '']]);
    }
}
