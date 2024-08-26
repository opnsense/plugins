/*
 * Copyright (C) 2024 Cedrik Pischem
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

export default class CaddyDomain extends BaseTableWidget {
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
        let $caddyDomainTable = this.createTable('caddyDomainTable', {
            headerPosition: 'none'
        });

        $container.append($caddyDomainTable);
        return $container;
    }

    async onWidgetTick() {
        // Check if caddy is enabled
        const data = await this.ajaxCall('/api/caddy/reverse_proxy/get');
        if (!data.caddy.general || data.caddy.general.enabled === "0") {
            this.displayError(`${this.translations.unconfigured}`);
            return;
        }

        // Process domains if caddy is enabled
        let domains = { ...data.caddy.reverseproxy.reverse, ...data.caddy.reverseproxy.subdomain };
        this.processDomains(domains);
    }

    // Utility function to display errors within the widget
    displayError(message) {
        const $error = $(`<div class="error-message"><a href="/ui/caddy/general">${message}</a></div>`);
        $('#caddyDomainTable').empty().append($error);
    }

    processDomains(domains) {
        if (!this.dataChanged('domains', domains)) {
            return;
        }

        $('.caddy-domain-tooltip').tooltip('hide');

        let rows = [];
        // Assuming domains is a combination of both reverse and subdomains
        for (const key in domains) {
            const domain = domains[key];
            let colorClass = domain.enabled === "1" ? 'text-success' : 'text-danger';
            let tooltipText = domain.enabled === "1" ? this.translations.enabled : this.translations.disabled;
            let domainPort = domain.FromDomain;

            if (domain.FromPort) {
                domainPort += `:${domain.FromPort}`;
            }

            let row = $(`
                <div class="caddy-info">
                    <div class="caddy-enabled">
                        <i class="fa fa-globe ${colorClass} caddy-domain-tooltip" style="cursor: pointer;"
                            data-tooltip="caddy-domain-${domainPort}" title="${tooltipText}">
                        </i>
                        &nbsp;
                        <a class="caddy-domainport" href="/ui/caddy/reverse_proxy">
                            ${domainPort}
                        </a>
                    </div>
                </div>
            `).prop('outerHTML');

            rows.push({ html: row, enabled: domain.enabled });
        }

        // Sort rows by their enabled status
        rows.sort((a, b) => a.enabled - b.enabled);

        // Update table with sorted rows
        super.updateTable('caddyDomainTable', rows.map(row => [row.html]));

        // Initialize tooltips for interactivity
        $('.caddy-domain-tooltip').tooltip({container: 'body'});
    }
}
