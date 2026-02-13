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

export default class AvahiReflector extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 30;
    }

    getGridOptions() {
        return {
            sizeToContent: 650,
            minW: 2
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

        const statusColor = response.running ? 'text-success' : 'text-danger';
        const statusText = response.running ? this.translations['running'] : this.translations['stopped'];
        rows.push([this.translations['status'], `<i class="fa fa-circle ${statusColor}"></i> ${statusText}`]);

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

        const reflectorColor = response.reflector_enabled ? 'text-success' : 'text-danger';
        const reflectorText = response.reflector_enabled ? this.translations['enabled'] : this.translations['disabled'];
        rows.push([this.translations['reflector'], `<i class="fa fa-circle ${reflectorColor}"></i> ${reflectorText}`]);

        if (response.reflect_filters) {
            rows.push([this.translations['reflect_filters'], response.reflect_filters]);
        }

        if (response.port_conflict) {
            rows.push([
                `<i class="fa fa-exclamation-triangle text-warning"></i> ${this.translations['conflict']}`,
                `${response.port_conflict} ${this.translations['conflict_detail']}`
            ]);
        }

        super.updateTable('avahiReflectorTable', rows);
    }

    displayError(message) {
        super.updateTable('avahiReflectorTable', [[message, '']]);
    }
}
