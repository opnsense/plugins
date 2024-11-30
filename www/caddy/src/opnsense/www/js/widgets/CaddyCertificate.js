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
        const $container = $('<div></div>');
        const $caddyCertificateTable = this.createTable('caddyCertificateTable', {
            headerPosition: 'none'
        });

        $container.append($caddyCertificateTable);
        return $container;
    }

    async onWidgetTick() {
        const proxyData = await this.ajaxCall('/api/caddy/reverse_proxy/get');
        if (!proxyData.caddy.general || proxyData.caddy.general.enabled === "0") {
            this.displayError(`${this.translations.unconfigured}`);
            return;
        }

        const domains = Object.values(proxyData.caddy.reverseproxy?.reverse || [])
            .map(proxy => proxy.FromDomain)
            .filter(Boolean);

        const certificates = (await this.ajaxCall('/api/caddy/diagnostics/certificate')).content || [];

        // Display certificate if hostname in config and CN of stored cert on disk match
        const matchingCertificates = certificates.filter(cert => domains.includes(cert.hostname));

        if (matchingCertificates.length === 0) {
            this.displayError(`${this.translations.nocerts}`);
            return;
        }

        this.clearError();
        this.processCertificates(matchingCertificates);
    }

    displayError(message) {
        const $error = $(`<div class="error-message"><a href="/ui/caddy/general">${message}</a></div>`);
        $('#caddyCertificateTable').empty().append($error);
    }

    clearError() {
        $('#caddyCertificateTable .error-message').remove();
    }

    processCertificates(certificates) {
        $('.caddy-certificate-tooltip').tooltip('hide');

        const rows = certificates.map(certificate => {
            const colorClass = certificate.remaining_days === 0
                ? 'text-danger'
                : certificate.remaining_days < 14
                ? 'text-warning'
                : 'text-success';

            const statusText = certificate.remaining_days === 0
                ? this.translations.expired
                : this.translations.valid;

            const row = `
                <div>
                    <i class="fa fa-lock ${colorClass} caddy-certificate-tooltip" style="cursor: pointer;"
                        data-tooltip="caddy-certificate-${certificate.hostname}" title="${statusText}">
                    </i>
                    &nbsp;
                    <span><b>${certificate.hostname}</b></span>
                    <br/>
                    <div style="margin-top: 5px; margin-bottom: 5px;">
                        <i>${this.translations.expires}</i> ${certificate.remaining_days} ${this.translations.days},
                        ${new Date(certificate.expiration_date).toLocaleString()}
                    </div>
                </div>`;
            return { html: row, expirationDate: new Date(certificate.expiration_date) };
        });

        rows.sort((a, b) => a.expirationDate - b.expirationDate);

        const sortedRows = rows.map(row => [row.html]);
        super.updateTable('caddyCertificateTable', sortedRows);

        $('.caddy-certificate-tooltip').tooltip({ container: 'body' });
    }
}
