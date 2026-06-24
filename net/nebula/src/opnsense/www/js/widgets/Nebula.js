/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

export default class Nebula extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            // Automatically triggers vertical scrolling after reaching 650px in height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $nebulaTable = this.createTable('nebulaInstanceTable', {
            headerPosition: 'left'
        });

        $container.append($nebulaTable);
        return $container;
    }

    async onWidgetTick() {
        const response = await this.ajaxCall('/api/nebula/instance/search_item');

        if (!response || !response.rows || response.rows.length === 0) {
            this.displayError(this.translations.noinstances);
            return;
        }

        if (!this.dataChanged('nebula-instances', response.rows)) {
            return; // No changes detected, do not update the UI
        }

        this.processInstances(response.rows);
    }

    displayError(message) {
        $('#nebulaInstanceTable').empty().append(
            $('<div class="error-message"></div>').append(
                $('<a href="/ui/nebula/instances"></a>').text(message)
            )
        );
    }

    processInstances(rows) {
        $('.nebula-instance-status').tooltip('hide');

        let instances = rows.map(row => {
            const enabled = String(row.enabled) === '1';
            const running = String(row.running) === '1';

            let statusIcon;
            let statusTooltip;
            if (!enabled) {
                statusIcon = 'fa-circle-o fa-fw text-muted';
                statusTooltip = this.translations.disabled;
            } else if (running) {
                statusIcon = 'fa-check-circle fa-fw text-success';
                statusTooltip = this.translations.running;
            } else {
                statusIcon = 'fa-times-circle fa-fw text-danger';
                statusTooltip = this.translations.stopped;
            }

            const listenHost = row.listen_host || '';
            const listenPort = row.listen_port || '';
            const listen = (listenHost !== '' || listenPort !== '')
                ? `${listenHost}:${listenPort}`
                : this.translations.notavailable;

            return {
                uuid: row.uuid,
                description: row.description || row.uuid,
                enabled: enabled,
                running: running,
                pid: row.pid || '',
                isLighthouse: String(row.am_lighthouse) === '1',
                listen: listen,
                certificate: row.certificate || this.translations.notavailable,
                statusIcon: statusIcon,
                statusTooltip: statusTooltip
            };
        });

        // Running instances first, then enabled-but-stopped, then disabled.
        instances.sort((a, b) => {
            const rank = i => (i.running ? 0 : (i.enabled ? 1 : 2));
            return rank(a) - rank(b);
        });

        let runningCount = instances.filter(i => i.running).length;
        let summaryRow = `
            <div>
                <span>
                    ${this.translations.running}: ${runningCount} |
                    ${this.translations.total}: ${instances.length}
                </span>
            </div>`;
        super.updateTable('nebulaInstanceTable', [[summaryRow, '']], 'nebula-summary');

        instances.forEach(instance => {
            let lighthouseLabel = instance.isLighthouse
                ? `<span class="label label-info" style="margin-left: 6px;">${this.translations.lighthouse}</span>`
                : '';

            let header = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <i class="fa ${instance.statusIcon} nebula-instance-status" style="cursor: pointer;"
                            data-toggle="tooltip" title="${instance.statusTooltip}">
                        </i>
                        &nbsp;
                        <a href="/ui/nebula/status" target="_blank" rel="noopener noreferrer">
                            ${instance.description}
                        </a>
                        ${lighthouseLabel}
                    </div>
                </div>`;

            let pidText = (instance.running && instance.pid !== '')
                ? ` <span class="text-muted">(pid ${instance.pid})</span>`
                : '';

            let row = `
                <div>
                    <div>${this.translations.listen}: ${instance.listen}${pidText}</div>
                    <div class="text-muted">${this.translations.certificate}: ${instance.certificate}</div>
                </div>`;

            super.updateTable('nebulaInstanceTable', [[header, row]], instance.uuid);
        });

        // Activate tooltips for the new dynamic elements.
        $('.nebula-instance-status').tooltip({container: 'body'});
    }
}
