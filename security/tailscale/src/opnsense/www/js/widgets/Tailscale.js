/*
 * Copyright (C) 2024 Sheridan Computers
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

export default class Wireguard extends BaseTableWidget {
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
        let $tailscaleStatusTable = this.createTable('tailscaleStatusTable', {
            headerPosition: 'left'
        });

        $container.append($tailscaleStatusTable);
        return $container;
    }

    async onWidgetTick() {
        // check if Tailscale is enabled
        const ServiceStatusData = await this.ajaxCall('/api/tailscale/service/status');
        if (!ServiceStatusData || ServiceStatusData.status !== 'running') {
            this.displayError(this.translations.serviceDisabled);
            return;
        }

        const tsData = await this.ajaxCall('/api/tailscale/status/status');
        if (!tsData) {
            this.displayError(this.translations.noData);
            return;
        }

        let statusData = this.parseData(tsData);
        if (!this.dataChanged('tailscale-data', statusData)) {
            return;
        }

        this.updateWidgetTable(statusData);
    }

    parseData(data) {
        let result = [];

        result['version'] = data.Version;
        result['backendState'] = data.BackendState;
        result['dnsName'] = data.Self.DNSName;

        result['online'] = (data.Self.Online === true) ?
          this.translations.yes : this.translations.no;

        result['exitNode'] = (data.Self.ExitNode === true) ?
          this.translations.yes : this.translations.no;

        result['peerCount'] = Object.keys(data.Peer).length;

        let ipAddresses = [];
        data.TailscaleIPs.forEach(ip => {
            ipAddresses.push(ip);
        });
        result['ipAddresses'] = ipAddresses;

        return result;
    }

    updateWidgetTable(data) {
        let rows = [];

        let color = "text-success";
        if (data['online'] === false) {
            color = "text-danger";
        }

        let row = [
            `<div><i class="fa fa-circle ${color}"></i> ${this.translations.online}</div>`,
            `<div>${data['online']}</div>`
        ];
        rows.push(row);

        // version
        row = [
            `<div>${this.translations.version}</div>`,
            `<div>${data['version']}</div>`
        ];
        rows.push(row);

        // backend state
        row = [
            `<div>${this.translations.backendState}</div>`,
            `<div>${data['backendState']}</div>`
        ];
        rows.push(row);

        // dns name
        row = [
            `<div>${this.translations.dnsName}</div>`,
            `<div>${data['dnsName']}</div>`
        ];
        rows.push(row);

        // ip addreses
        row = [
            `<div>${this.translations.tailscaleIP}</div>`,
            `<div>${data['ipAddresses'].join('<br>')}</div>`
        ];
        rows.push(row);

        // exit node
        row = [
            `<div>${this.translations.exitNode}</div>`,
            `<div>${data['exitNode']}</div>`
        ];
        rows.push(row);

        // peers
        row = [
            `<div>${this.translations.peers}</div>`,
            `<div>${data['peerCount']}</div>`
        ];
        rows.push(row);

        super.updateTable('tailscaleStatusTable', rows);
    }

    displayError(message) {
        $('#tailscaleStatusTable').empty().append(
            $(`<div class="error-message">${message}</div>`)
        );
    }
}
