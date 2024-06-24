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

export default class CaddyCertificate extends BaseTableWidget {
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
        let $caddyCertificateTable = this.createTable('caddyCertificateTable', {
            headerPosition: 'none'
        });

        $container.append($caddyCertificateTable);
        return $container;
    }

    async onWidgetTick() {
        await ajaxGet('/api/caddy/diagnostics/certificate', {}, (data, status) => {
            if (data.status !== "success") {
                $('#caddyCertificateTable').html(`<a href="/ui/caddy/general">${this.translations.unconfigured}</a>`);
                return;
            }

            let rows = [];
            const certificates = data.content;

            // Collect rows
            for (const certificate of certificates) {
                let colorClass = 'text-success';
                if (certificate.remaining_days === 0) {
                    colorClass = 'text-danger';
                } else if (certificate.remaining_days < 14) {
                    colorClass = 'text-warning';
                }

                let statusText = certificate.remaining_days === 0 ? this.translations.expired :
                    this.translations.valid;

                let hostname = certificate.hostname;
                let expirationDate = new Date(certificate.expiration_date);
                let remainingDays = certificate.remaining_days;

                let row = `
                    <div>
                        <i class="fa fa-lock ${colorClass}" style="cursor: pointer;"
                            data-toggle="tooltip" title="${statusText}">
                        </i>
                        &nbsp;
                        <span><b>${hostname}</b></span>
                        <br/>
                        <div style="margin-top: 5px; margin-bottom: 5px;"><i>${this.translations.expires}</i> ${remainingDays} <i>${this.translations.days}</i>, ${expirationDate.toLocaleString()}</div>
                    </div>`;

                rows.push({ html: row, expirationDate });
            }

            // Sort rows by expiration date from lowest to highest
            rows.sort((a, b) => a.expirationDate - b.expirationDate);

            // Extract sorted HTML rows
            let sortedRows = rows.map(row => [row.html]);

            // Update table
            super.updateTable('caddyCertificateTable', sortedRows);

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
        }).catch(error => {
            console.error('Error fetching certificate data:', error);
        });
    }
}
