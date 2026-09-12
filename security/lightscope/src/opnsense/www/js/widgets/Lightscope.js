/*
 * Copyright (c) 2025 USC Information Sciences Institute
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

export default class Lightscope extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            sizeToContent: 300
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $lightscopeTable = this.createTable('lightscopeStatusTable', {
            headerPosition: 'left'
        });
        $container.append($lightscopeTable);
        return $container;
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/lightscope/status/status');

        if (!data) {
            this.displayError(this.translations.noData);
            return;
        }

        if (!this.dataChanged('lightscope-data', data)) {
            return;
        }

        let rows = [];

        // Status row
        let statusText = data.status === 'running'
            ? `<span class="text-success"><i class="fa fa-check-circle"></i> ${this.translations.running}</span>`
            : `<span class="text-danger"><i class="fa fa-times-circle"></i> ${this.translations.stopped}</span>`;
        rows.push({
            column1: this.translations.status,
            column2: statusText
        });

        // Dashboard link row
        if (data.dashboard_url) {
            rows.push({
                column1: this.translations.dashboard,
                column2: `<a href="${data.dashboard_url}" target="_blank" class="btn btn-xs btn-primary">
                    <i class="fa fa-external-link"></i> ${this.translations.viewReports}
                </a>`
            });
        }

        // Database ID row
        if (data.database) {
            let shortDb = data.database.length > 30
                ? data.database.substring(0, 30) + '...'
                : data.database;
            rows.push({
                column1: this.translations.databaseId,
                column2: `<code title="${data.database}">${shortDb}</code>`
            });
        }

        // Honeypot ports row
        if (data.honeypot_ports) {
            rows.push({
                column1: this.translations.honeypotPorts,
                column2: data.honeypot_ports
            });
        }

        // Process count
        if (data.process_count !== undefined) {
            rows.push({
                column1: this.translations.processes,
                column2: data.process_count
            });
        }

        super.updateTable('lightscopeStatusTable', rows);
    }
}
