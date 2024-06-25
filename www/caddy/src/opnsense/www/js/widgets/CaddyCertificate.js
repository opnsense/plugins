// endpoint:/api/caddy/*

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
        this.resizeHandles = "e, w";
        this.currentCertificates = {};

        // Since we only update when dataHasChanged we can almost update in real time
        this.tickTimeout = 2000;

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
        try {
            // Check if Caddy is enabled
            const caddyStatus = await ajaxGet('/api/caddy/reverse_proxy/get', {});
            if (!caddyStatus.caddy.general || caddyStatus.caddy.general.enabled === "0") {
                $('#caddyCertificateTable').html(`<a href="/ui/caddy/general">${this.translations.unconfigured}</a>`);
                return;
            }

            // Fetch the certificate details
            const response = await ajaxGet('/api/caddy/diagnostics/certificate', {});
            if (response.status !== "success") {
                $('#caddyCertificateTable').html(`<a href="/ui/caddy/general">${this.translations.nocerts}</a>`);
                return;
            }

            // Process certificates if the response is successful
            this.processCertificates(response.content);
        } catch (error) {
            $('#caddyCertificateTable').html(`<a href="/ui/caddy/general">${this.translations.error}</a>`);
        }
    }

    dataHasChanged(newCertificates) {

        // Directly serialize the entire newCertificates array
        const newCertificatesString = JSON.stringify(newCertificates);
        const currentCertificatesString = JSON.stringify(this.currentCertificates);

        // Compare the serialized strings
        if (newCertificatesString !== currentCertificatesString) {
            this.currentCertificates = JSON.parse(newCertificatesString);
            return true;
        } else {
            return false;
        }
    }

    processCertificates(certificates) {
        if (!this.dataHasChanged(certificates)) {
            return;  // Early exit if no changes
        }

        let rows = certificates.map(certificate => {
            let colorClass = 'text-success';
            if (certificate.remaining_days === 0) {
                colorClass = 'text-danger';
            } else if (certificate.remaining_days < 14) {
                colorClass = 'text-warning';
            }

            let statusText = certificate.remaining_days === 0 ? this.translations.expired :
                             this.translations.valid;

            let row = `
                <div>
                    <i class="fa fa-lock ${colorClass}" style="cursor: pointer;"
                        data-toggle="tooltip" title="${statusText}">
                    </i>
                    &nbsp;
                    <span><b>${certificate.hostname}</b></span>
                    <br/>
                    <div style="margin-top: 5px; margin-bottom: 5px;"><i>${this.translations.expires}</i> ${certificate.remaining_days} <i>${this.translations.days}</i>, ${new Date(certificate.expiration_date).toLocaleString()}</div>
                </div>`;
            return { html: row, expirationDate: new Date(certificate.expiration_date) };
        });

        // Sort rows by expiration date from lowest to highest
        rows.sort((a, b) => a.expirationDate - b.expirationDate);

        // Extract sorted HTML rows and update table
        let sortedRows = rows.map(row => [row.html]);
        super.updateTable('caddyCertificateTable', sortedRows);

        // Initialize tooltips for new elements
        $('[data-toggle="tooltip"]').tooltip();
    }
}
