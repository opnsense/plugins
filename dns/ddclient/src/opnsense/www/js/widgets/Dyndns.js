/*
 * Copyright (C) 2024 Deciso B.V.
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

export default class Dyndns extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            // Trigger overflow-y:scroll after 650px height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $dyndnsTable = this.createTable('dyndnsTable', {
            headerPosition: 'top',
            headers: [
                this.translations.service,
                this.translations.domains
            ]
        });

        $container.append($dyndnsTable);
        return $container;
    }

    async onWidgetTick() {
        // Check if DynDNS is enabled
        const statusData = await this.ajaxCall('/api/dyndns/service/status');
        if (!statusData || statusData.status !== "running") {
            this.displayError(this.translations.servicedisabled);
            return;
        }

        // Fetch DynDNS account information
        const accountData = await this.ajaxCall('/api/dyndns/accounts/search_item');
        if (!accountData || !accountData.rows || accountData.rows.length === 0) {
            this.displayError(this.translations.noaccount);
            return;
        }

        this.processAccounts(accountData.rows);
    }

    // Utility function to display errors within the widget
    displayError(message) {
        const $error = $(`
            <div class="error-message">
                <a href="/ui/dyndns/" target="_blank">${message}</a>
            </div>
        `);
        $('#dyndnsTable').empty().append($error);
    }

    processAccounts(accounts) {
        if (!this.dataChanged('accounts', accounts)) {
            return;
        }

        $('.dyndns-tooltip').tooltip('hide');

        let rows = [];
        accounts.forEach(account => {
            let colorClass = account.enabled === "1" ? 'text-success' : 'text-danger';
            let tooltipText = account.enabled === "1" ? this.translations.enabled : this.translations.disabled;

            let domainNames = account.hostnames.split(',')
                  .map(domain => `<div>${domain}</div>`)
                  .join('');

            // Convert time to a localized format
            let localizedTime = account.current_mtime ? new Date(account.current_mtime).toLocaleString() : this.translations.undefined;
            let currentIp = account.current_ip || this.translations.undefined;

            let row = [
                `
                    <div class="dyndns-service">
                        <i class="fa fa-circle ${colorClass} dyndns-tooltip" style="cursor: pointer;" title="${tooltipText}"></i>
                        &nbsp;<a href="/ui/dyndns/" target="_blank">${account.service}</a>
                    </div>
                    <div class="current-ip"><em>${this.translations.currentip}:</em> ${currentIp}</div>
                    <div class="current-mtime"><em>${this.translations.currentmtime}:</em> ${localizedTime}</div>
                `,
                `
                    <div class="domain-names">${domainNames}</div>
                `
            ];

            rows.push(row);
        });

        // Update table with rows
        super.updateTable('dyndnsTable', rows);

        // Initialize tooltips
        $('.dyndns-tooltip').tooltip({container: 'body'});
    }

    onWidgetResize(elem, width, height) {
        if (width < 320) {
            $('#header_dyndnsTable').hide();
            $('.domain-names').parent().hide();
        } else {
            $('#header_dyndnsTable').show();
            $('.domain-names').parent().show();
        }
        return true; // Return true to force the grid to update its layout
    }
}
