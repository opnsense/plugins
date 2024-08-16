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

export default class CaddyCertificate extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 30;
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
        // Check if Caddy is enabled
        const caddyStatus = await this.ajaxCall('/api/caddy/reverse_proxy/get');
        if (!caddyStatus.caddy.general || caddyStatus.caddy.general.enabled === "0") {
            this.displayError(`${this.translations.unconfigured}`);
            return;
        }

        // Fetch the certificate details
        const response = await this.ajaxCall('/api/caddy/diagnostics/certificate');
        if (response.status !== "success") {
            this.displayError(`${this.translations.nocerts}`);
            return;
        }

        // Process certificates if the response is successful
        this.processCertificates(response.content);
    }

    // Utility function to display errors within the widget
    displayError(message) {
        const $error = $(`<div class="error-message"><a href="/ui/caddy/general">${message}</a></div>`);
        $('#caddyCertificateTable').empty().append($error);
    }

    processCertificates(certificates) {
        if (!this.dataChanged('certificates', certificates)) {
            return;
        }

        $('.caddy-certificate-tooltip').tooltip('hide');

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
                    <i class="fa fa-lock ${colorClass} caddy-certificate-tooltip" style="cursor: pointer;"
                        data-tooltip="caddy-certificate-${certificate.hostname}" title="${statusText}">
                    </i>
                    &nbsp;
                    <span><b>${certificate.hostname}</b></span>
                    <br/>
                    <div style="margin-top: 5px; margin-bottom: 5px;"><i>${this.translations.expires}</i> ${certificate.remaining_days} ${this.translations.days}, ${new Date(certificate.expiration_date).toLocaleString()}</div>
                </div>`;
            return { html: row, expirationDate: new Date(certificate.expiration_date) };
        });

        // Sort rows by expiration date from lowest to highest
        rows.sort((a, b) => a.expirationDate - b.expirationDate);

        // Extract sorted HTML rows and update table
        let sortedRows = rows.map(row => [row.html]);
        super.updateTable('caddyCertificateTable', sortedRows);

        // Initialize tooltips for new elements
        $('.caddy-certificate-tooltip').tooltip({container: 'body'});
    }
}
