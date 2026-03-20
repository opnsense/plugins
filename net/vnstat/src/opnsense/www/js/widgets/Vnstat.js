/*
 * Copyright (C) 2026 Joe Roback <joe.roback@gmail.com>
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

export default class Vnstat extends BaseTableWidget {
    constructor(config) {
        super(config);
        this.currentPeriod = 'month';
        this.currentInterface = null;
        this.configurable = true;
        this.excludedInterfaces = ['enc0', 'pflog0', 'pfsync0'];
        this.showAvgRate = false;
    }

    getGridOptions() {
        return {
            sizeToContent: 1000
        };
    }

    async getWidgetOptions() {
        const data = await this.ajaxCall('/api/vnstat/service/dbiflist');
        const ifaceOptions = (data?.interfaces ?? []).map(name => ({ value: name, label: name }));

        return {
            excluded_interfaces: {
                id: 'vnstat-exclude-interfaces',
                title: this.translations.excluded_interfaces,
                type: 'select_multiple',
                options: ifaceOptions,
                default: ['enc0', 'pflog0', 'pfsync0']
            },
            refresh_interval: {
                id: 'vnstat-refresh-interval',
                title: this.translations.refresh_interval,
                type: 'select',
                options: [
                    { value: '60', label: '1' },
                    { value: '120', label: '2' },
                    { value: '300', label: '5' },
                    { value: '600', label: '10' },
                    { value: '900', label: '15' },
                    { value: '1800', label: '30' },
                ],
                default: '300'
            },
            show_avg_rate: {
                id: 'vnstat-show-avg-rate',
                title: this.translations.show_avg_rate,
                type: 'select',
                options: [
                    { value: 'no', label: 'No' },
                    { value: 'yes', label: 'Yes' },
                ],
                default: 'no'
            }
        };
    }

    async onWidgetOptionsChanged() {
        const config = await this.getWidgetConfig();
        this.excludedInterfaces = config.excluded_interfaces ?? [];
        this.tickTimeout = Number(config.refresh_interval) || 300;
        this.showAvgRate = config.show_avg_rate === 'yes';
        this._lastIfaceList = null;
        await this._populateInterfaceDropdown();
        await this._fetchAndUpdateTable();
    }

    getMarkup() {
        let $container = $('<div id="vnstat-container"></div>');
        let $selectors = $(`
            <div class="vnstat-selectors" style="padding: 5px 10px; display: flex; gap: 5px;">
                <select id="vnstat-interface-select" class="form-control">
                </select>
                <select id="vnstat-period-select" class="form-control">
                    <option value="hour">${this.translations.period_hourly}</option>
                    <option value="day">${this.translations.period_daily}</option>
                    <option value="month" selected>${this.translations.period_monthly}</option>
                    <option value="year">${this.translations.period_yearly}</option>
                </select>
            </div>
        `);
        let $table = this.createTable('vnstat-table', {
            headerPosition: 'none'
        });

        $container.append($selectors);
        $container.append($table);
        return $container;
    }

    async onMarkupRendered() {
        const config = await this.getWidgetConfig();
        this.excludedInterfaces = config.excluded_interfaces ?? [];
        this.tickTimeout = Number(config.refresh_interval) || 300;
        this.showAvgRate = config.show_avg_rate === 'yes';

        $(document).on('change.vnstat-widget', '#vnstat-period-select', async (e) => {
            this.currentPeriod = e.target.value;
            this._savePrefs();
            await this._fetchAndUpdateTable();
            this.config.callbacks.updateGrid();
        });
        $(document).on('change.vnstat-widget', '#vnstat-interface-select', async (e) => {
            this.currentInterface = e.target.value;
            this._savePrefs();
            await this._fetchAndUpdateTable();
            this.config.callbacks.updateGrid();
        });

        const prefs = this._loadPrefs();
        if (prefs) {
            if (prefs.period) {
                this.currentPeriod = prefs.period;
                $('#vnstat-period-select').val(prefs.period);
            }
            if (prefs.interface) {
                this.currentInterface = prefs.interface;
            }
        }
    }

    async onWidgetTick() {
        await this._populateInterfaceDropdown();
        await this._fetchAndUpdateTable();
    }

    async _populateInterfaceDropdown() {
        const data = await this.ajaxCall('/api/vnstat/service/dbiflist');
        if (!data || !data.interfaces) return;

        const names = data.interfaces.filter(name => !this.excludedInterfaces.includes(name));
        if (this._lastIfaceList && JSON.stringify(this._lastIfaceList) === JSON.stringify(names)) {
            return;
        }
        this._lastIfaceList = names;

        const $select = $('#vnstat-interface-select');
        $select.empty();
        for (const name of names) {
            $select.append($('<option></option>').val(name).text(name));
        }

        if (this.currentInterface && names.includes(this.currentInterface)) {
            $select.val(this.currentInterface);
        } else if (names.includes('WAN')) {
            $select.val('WAN');
            this.currentInterface = 'WAN';
        } else if (names.length > 0) {
            $select.val(names[0]);
            this.currentInterface = names[0];
        }
    }

    async _fetchAndUpdateTable() {
        if (!this.currentInterface) {
            super.updateTable('vnstat-table', [[`<b>${this.translations.msg_no_data}</b>`]]);
            return;
        }

        const data = await this.ajaxCall(`/api/vnstat/service/json?iface=${encodeURIComponent(this.currentInterface)}`);

        if (!data || data.error || !data.interfaces || data.interfaces.length === 0) {
            super.updateTable('vnstat-table', [[`<b>${this.translations.msg_no_data}</b>`]]);
            return;
        }

        const iface = data.interfaces[0];
        const traffic = iface.traffic?.[this.currentPeriod];
        if (!traffic || traffic.length === 0) {
            super.updateTable('vnstat-table', [[`<b>${this.translations.msg_no_data}</b>`]]);
            return;
        }

        let rows = [];
        let header = [
            `<b>${this.translations.h_date}</b>`,
            `<b>${this.translations.h_rx}</b>`,
            `<b>${this.translations.h_tx}</b>`,
            `<b>${this.translations.h_total}</b>`
        ];
        if (this.showAvgRate) {
            header.push(`<b>${this.translations.h_avg_rate}</b>`);
        }
        rows.push(header);

        const limits = { hour: 12, day: 14, month: 12, year: 5 };
        let entries = [...traffic];
        entries.sort((a, b) => {
            return this._dateToSortKey(b, this.currentPeriod) -
                   this._dateToSortKey(a, this.currentPeriod);
        });
        if (limits[this.currentPeriod]) {
            entries = entries.slice(0, limits[this.currentPeriod]);
        }
        entries.reverse();

        for (const entry of entries) {
            const totalBytes = entry.rx + entry.tx;
            let row = [
                this._formatDate(entry, this.currentPeriod),
                this._formatBytes(entry.rx),
                this._formatBytes(entry.tx),
                this._formatBytes(totalBytes)
            ];
            if (this.showAvgRate) {
                const seconds = this._periodSeconds(entry, this.currentPeriod);
                const bitsPerSec = seconds > 0 ? (totalBytes * 8) / seconds : 0;
                row.push(this._formatRate(bitsPerSec));
            }
            rows.push(row);
        }

        super.updateTable('vnstat-table', rows);
    }

    onWidgetClose() {
        $(document).off('change.vnstat-widget');
    }

    _savePrefs() {
        try {
            localStorage.setItem('vnstat-widget-prefs', JSON.stringify({
                interface: this.currentInterface,
                period: this.currentPeriod
            }));
        } catch (e) {
            // localStorage may be unavailable
        }
    }

    _loadPrefs() {
        try {
            const raw = localStorage.getItem('vnstat-widget-prefs');
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    _periodSeconds(entry, period) {
        const d = entry.date;
        const now = new Date();

        if (period === 'hour') {
            if (d.year === now.getFullYear() && d.month === now.getMonth() + 1 &&
                d.day === now.getDate() && entry.time.hour === now.getHours()) {
                return now.getMinutes() * 60 + now.getSeconds() || 1;
            }
            return 3600;
        } else if (period === 'day') {
            if (d.year === now.getFullYear() && d.month === now.getMonth() + 1 &&
                d.day === now.getDate()) {
                return now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds() || 1;
            }
            return 86400;
        } else if (period === 'month') {
            if (d.year === now.getFullYear() && d.month === now.getMonth() + 1) {
                const startOfMonth = new Date(d.year, d.month - 1, 1);
                return Math.floor((now - startOfMonth) / 1000) || 1;
            }
            const daysInMonth = new Date(d.year, d.month, 0).getDate();
            return daysInMonth * 86400;
        } else {
            if (d.year === now.getFullYear()) {
                const startOfYear = new Date(d.year, 0, 1);
                return Math.floor((now - startOfYear) / 1000) || 1;
            }
            const isLeap = (d.year % 4 === 0 && d.year % 100 !== 0) || (d.year % 400 === 0);
            return (isLeap ? 366 : 365) * 86400;
        }
    }

    _formatRate(bitsPerSec) {
        if (bitsPerSec === 0) return '0 bit/s';
        const units = ['bit/s', 'Kbit/s', 'Mbit/s', 'Gbit/s', 'Tbit/s'];
        const k = 1000;
        const i = Math.floor(Math.log(bitsPerSec) / Math.log(k));
        const idx = Math.min(i, units.length - 1);
        return (bitsPerSec / Math.pow(k, idx)).toFixed(2) + ' ' + units[idx];
    }

    _formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        const k = 1024;
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        const idx = Math.min(i, units.length - 1);
        return (bytes / Math.pow(k, idx)).toFixed(2) + ' ' + units[idx];
    }

    _formatDate(entry, period) {
        const d = entry.date;
        if (period === 'year') {
            return `${d.year}`;
        } else if (period === 'month') {
            return `${d.year}-${String(d.month).padStart(2, '0')}`;
        } else if (period === 'hour') {
            return `${d.year}-${String(d.month).padStart(2, '0')}-${String(d.day).padStart(2, '0')} ${String(entry.time.hour).padStart(2, '0')}:00`;
        } else {
            return `${d.year}-${String(d.month).padStart(2, '0')}-${String(d.day).padStart(2, '0')}`;
        }
    }

    _dateToSortKey(entry, period) {
        const d = entry.date;
        if (period === 'year') {
            return d.year;
        } else if (period === 'month') {
            return d.year * 100 + d.month;
        } else if (period === 'hour') {
            return d.year * 1000000 + d.month * 10000 + d.day * 100 + entry.time.hour;
        } else {
            return d.year * 10000 + d.month * 100 + d.day;
        }
    }
}
