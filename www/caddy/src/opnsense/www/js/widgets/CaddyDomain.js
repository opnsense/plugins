// endpoint:/api/caddy/reverse_proxy/*

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

import BaseTableWidget from "./BaseTableWidget.js";

export default class CaddyDomain extends BaseTableWidget {
    constructor() {
        super();
        // this.resizeHandles = "e, w";
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
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
        await ajaxGet('/api/caddy/reverse_proxy/get', {}, (data, status) => {
            if (!data.caddy.general || data.caddy.general.enabled === "0") {
                $('#caddyDomainTable').html(`<a href="/ui/caddy/general">${this.translations.unconfigured}</a>`);
                return;
            }

            let rows = [];
            const reverseProxies = data.caddy.reverseproxy.reverse;
            const subdomains = data.caddy.reverseproxy.subdomain;

            const collectRows = (domains) => {
                for (const id in domains) {
                    if (domains.hasOwnProperty(id)) {
                        const domain = domains[id];
                        let colorClass = domain.enabled === "1" ? 'text-success' : 'text-danger';
                        let tooltipText = domain.enabled === "1" ? this.translations.enabled : this.translations.disabled;
                        let domainPort = domain.FromDomain;

                        if (domain.FromPort) {
                            domainPort += `:${domain.FromPort}`;
                        }

                        let row = $(`
                            <div class="caddy-info">
                                <div class="caddy-enabled">
                                    <i class="fa fa-circle ${colorClass}" style="cursor: pointer;"
                                        data-toggle="tooltip" title="${tooltipText}">
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
                }
            };

            // Collect rows from both reverse proxies and subdomains
            collectRows(reverseProxies);
            collectRows(subdomains);

            // Sort rows: disabled first, then enabled
            rows.sort((a, b) => a.enabled - b.enabled);

            // Extract sorted HTML rows
            let sortedRows = rows.map(row => [row.html]);

            // Update table
            super.updateTable('caddyDomainTable', sortedRows);

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });
    }
}
