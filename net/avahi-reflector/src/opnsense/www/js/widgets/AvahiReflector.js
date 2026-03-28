/*
 * Copyright (C) 2024 OPNsense Community
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

export default class AvahiReflector extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 30;
    }

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $table = this.createTable('avahiReflectorTable', {
            headerPosition: 'left'
        });
        $container.append($table);
        return $container;
    }

    async onWidgetTick() {
        const response = await this.ajaxCall('/api/avahireflector/service/diagnostics');

        if (!response || response.status === 'error') {
            this.displayError(this.translations['unconfigured']);
            return;
        }

        const rows = [];

        // Status + Health on one line with colored circle indicator
        let statusColor = 'text-success';
        let statusText = `${this.translations['running']} / ${this.translations['healthy']}`;
        if (!response.running) {
            statusColor = 'text-danger';
            statusText = this.translations['stopped'];
        } else if (response.health && response.health.status === 'degraded') {
            statusColor = 'text-danger';
            statusText = `${this.translations['running']} / ${this.translations['degraded']}`;
        } else if (response.health && response.health.status === 'warning') {
            statusColor = 'text-warning';
            statusText = `${this.translations['running']} / ${this.translations['warning']}`;
        }
        rows.push([
            `<div><i class="fa fa-circle ${statusColor}"></i> ${this.translations['status']}</div>`,
            `<div>${statusText}</div>`
        ]);

        if (response.health) {
            const h = response.health;
            if (h.slot_errors_today > 0) {
                rows.push([this.translations['slot_errors_today'], h.slot_errors_today]);
            }
            if (h.last_slot_error) {
                rows.push([this.translations['last_slot_error'], h.last_slot_error]);
            }
            if (h.last_restart) {
                rows.push([this.translations['last_restart'], h.last_restart]);
            }
        }

        rows.push([this.translations['domain'], response.domain || '-']);

        if (response.interfaces) {
            rows.push([this.translations['interfaces'], response.interfaces]);
        }

        rows.push([this.translations['reflector'], response.reflector_enabled
            ? this.translations['enabled']
            : this.translations['disabled']]);

        if (response.reflect_filters) {
            rows.push([this.translations['reflect_filters'], response.reflect_filters]);
        }

        if (response.mdns_repeater_running) {
            rows.push([
                `<span class="label label-opnsense label-opnsense-xs label-warning">${this.translations['conflict']}</span>`,
                this.translations['conflict_detail']
            ]);
        }

        super.updateTable('avahiReflectorTable', rows);
    }

    displayError(message) {
        super.updateTable('avahiReflectorTable', [[message, '']]);
    }
}
