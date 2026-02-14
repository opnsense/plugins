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

        const statusBadge = response.running
            ? `<span class="label label-opnsense label-opnsense-xs label-success">${this.translations['running']}</span>`
            : `<span class="label label-opnsense label-opnsense-xs label-danger">${this.translations['stopped']}</span>`;
        rows.push([this.translations['status'], statusBadge]);

        if (response.health) {
            const h = response.health;
            let healthBadge;
            if (h.status === 'healthy') {
                healthBadge = `<span class="label label-opnsense label-opnsense-xs label-success">${this.translations['healthy']}</span>`;
            } else if (h.status === 'degraded') {
                healthBadge = `<span class="label label-opnsense label-opnsense-xs label-danger">${this.translations['degraded']}</span>`;
            } else {
                healthBadge = `<span class="label label-opnsense label-opnsense-xs label-warning">${this.translations['warning']}</span>`;
            }
            rows.push([this.translations['health'], healthBadge]);

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

        if (response.running && response.uptime !== null) {
            rows.push([this.translations['uptime'], response.uptime]);
        }

        rows.push([this.translations['domain'], response.domain || '-']);

        if (response.interfaces) {
            rows.push([this.translations['interfaces'], response.interfaces]);
        }

        const reflectorLabel = response.reflector_enabled
            ? `<span class="label label-opnsense label-opnsense-xs label-success">${this.translations['enabled']}</span>`
            : `<span class="label label-opnsense label-opnsense-xs label-danger">${this.translations['disabled']}</span>`;
        rows.push([this.translations['reflector'], reflectorLabel]);

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
