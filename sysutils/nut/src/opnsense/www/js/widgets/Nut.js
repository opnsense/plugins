/*
 * Copyright (C) 2024 DollarSign23
 * Copyright (C) 2024 Nicola Pellegrini
 * Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
 *
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

export default class NutNetclient extends BaseTableWidget {
    constructor(config) {
        super(config);

        // Set a timeout period for AJAX calls or other timed operations.
        this.timeoutPeriod = 1000;

        this.configurable = true;
    }

    getGridOptions() {
        return {
            // Trigger overflow-y:scroll after 650px height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $nut_table = this.createTable('nut-table', {
            headerPosition: 'none', // Disable table headers.
        });
        $container.append($nut_table);
        return $container;
    }

    async getWidgetOptions() {
        const [localResponse, remoteResponse] = await Promise.all([
            this.ajaxCall('/api/nut/monitors/search_local_monitor'),
            this.ajaxCall('/api/nut/monitors/search_remote_monitor')
        ]);

        const options = [];
        if (localResponse.rows) {
            for (const monitor of localResponse.rows) {
                if (monitor.enabled == '1') {
                    const name = monitor['%ups'];
                    const user = monitor['%user'];
                    options.push({
                        value: `local_${monitor['uuid']}`,
                        label: `${name} (${user}@localhost)`
                    })
                }
            }
        }
        if (remoteResponse.rows) {
            for (const monitor of remoteResponse.rows) {
                if (monitor.enabled == '1') {
                    const name = monitor['ups_name'];
                    const user = monitor['username'];
                    const hostname = monitor['hostname'];
                    const port = monitor['port'];
                    options.push({
                        value: `remote_${monitor['uuid']}`,
                        label: `${name} (${user}@${hostname}:${port})`
                    })
                }
            }
        }

        return {
            monitor: {
                title: this.translations.title,
                type: 'select',
                options: options,
                default: options.length > 0 ? options[0] : '',
            },
        };
    }

    async onWidgetOptionsChanged(options) {
        this._updateMonitor()
    }

    async onWidgetTick() {
        await this._updateMonitor()
    }

    async _updateMonitor() {
        // If the service is not running, display a message and stop further processing.
        const service_status = await this.ajaxCall('/api/nut/service/status');
        if (!service_status || service_status.status !== 'running') {
            $('#nut-table').html(
                `<a href="/ui/nut/general">${this.translations.unconfigured}</a>`
            );
            return;
        }

        // Get which monitor to display based on the widget configuration.
        const config = await this.getWidgetConfig();
        if (!config.monitor || !config.monitor.value.includes('_')) {
            $('#nut-table').html(this.translations.widget_unconfigured);
            return;
        }
        const [kind, uuid] = config.monitor.value.split('_');
        if (kind !== 'local' && kind !== 'remote') {
            $('#nut-table').html(this.translations.widget_unconfigured);
            return;
        }

        // Fetch the UPS information and status from the server.
        const { response: response } = await this.ajaxCall(
            `/api/nut/monitors/status_${kind}_monitor/${uuid}`
        );
        if (!response) {
            $('#nut-table').html(
                `<a href="/ui/nut/general">${this.translations.misconfigured}</a>`
            );
            return;
        }

        // Parse the UPS status data into a key-value object.
        const status = response.split('\n').reduce((acc, line) => {
            const [key, value] = line.split(': ');
            if (key) acc[key] = value; // Only add non-empty keys.
            return acc;
        }, {});
        if (!this.dataChanged('ups_status', status)) {
            return;
        }

        const rows = [];

        // Display the UPS's name, server address, and user.
        rows.push(this._makeTextRow(`netclient_${kind}_server`, config.monitor.label));

        // Display the manufacturer and model if available.
        if (status['device.mfr'] || status['device.model']) {
            const manufacturer = status['device.mfr'] || 'unknown';
            const model = status['device.model'] || 'unknown';
            rows.push(this._makeTextRow(
                'status_model',
                `${manufacturer} - ${model}`
            ));
        }
        // Display the UPS Status if available.
        if (status['ups.status']) {
            rows.push(this._makeColoredTextRow(
                'status_status',
                this._nutMapStatus(status['ups.status']),
                /OL/,
                /OB|LB|RB|DISCHRG/,
                status['ups.status']
            ));
        }
        // Display the UPS load with percentage and optional nominal power.
        if (status['ups.load'] && status['ups.realpower']) {
            rows.push(this._makeUpsLoadRow(
                'status_load',
                parseFloat(status['ups.load']),
                parseFloat(status['ups.realpower'])
            ));
        }
        // Display the battery charge as a progress bar if available.
        if (status['battery.charge']) {
            rows.push(this._makeProgressBarRow(
                'status_bcharge',
                parseFloat(status['battery.charge'])
            ));
        }
        // Display the battery status if available.
        if (status['battery.charger.status']) {
            rows.push(this._makeTextRow(
                'status_battery',
                status['battery.charger.status']
            ));
        }
        // Display the formatted battery runtime if available.
        if (status['battery.runtime']) {
            rows.push(this._makeTextRow(
                'status_timeleft',
                this._formatRuntime(parseInt(status['battery.runtime'], 10))
            ));
        }
        // Display the input voltage and frequency if available.
        if (status['input.voltage'] && status['input.frequency']) {
            rows.push(this._makeTextRow(
                'status_input_power',
                `${status['input.voltage']} V | ${status['input.frequency']} Hz`
            ));
        }
        // Display the output voltage and frequency if available.
        if (status['output.voltage'] && status['output.frequency']) {
            rows.push(this._makeTextRow(
                'status_output_power',
                `${status['output.voltage']} V | ${status['output.frequency']} Hz`
            ));
        }
        // Display the result of the UPS efficiency if available.
        if (status['ups.efficiency']) {
            rows.push(this._makeTextRow(
                'status_efficiency',
                `${status['ups.efficiency']}%`
            ));
        }
        // Display the result of the UPS self-test if available.
        if (status['ups.test.result']) {
            rows.push(this._makeTextRow('status_selftest', status['ups.test.result']));
        }

        // Update the table with the prepared rows.
        this.updateTable('nut-table', rows.filter(Boolean));
    }

    // Formats the runtime (in seconds) into a human-readable format (hours, minutes, seconds).
    _formatRuntime(seconds) {
        const hours = Math.floor(seconds / 3600); // Calculate full hours.
        const minutes = Math.floor((seconds % 3600) / 60); // Calculate remaining full minutes.
        const remainingSeconds = seconds % 60; // Calculate remaining seconds.

        let formattedTime = '';

        if (hours > 0) {
            formattedTime += `${hours}${this._translate('time_hours')} `;
        }
        if (minutes > 0 || hours > 0) { // Only show minutes if they are > 0 or hours are present.
            formattedTime += `${minutes}${this._translate('time_minutes')} `;
        }
        formattedTime += `${remainingSeconds}${this._translate('time_seconds')}`; // Always show seconds.

        return formattedTime.trim(); // Remove any trailing spaces.
    }

    // Create a mapping between UPS status codes and their corresponding translations
    _nutMapStatus(statusCode) {
        const statusMapping = {
            'OL': this._translate('status_ol'),         // On line (mains is present)
            'OB': this._translate('status_ob'),         // On battery (mains is not present)
            'LB': this._translate('status_lb'),         // Low battery
            'HB': this._translate('status_hb'),         // High battery
            'RB': this._translate('status_rb'),         // Battery needs to be replaced
            'CHRG': this._translate('status_chrg'),     // Battery is charging
            'DISCHRG': this._translate('status_dischrg'), // Battery is discharging
            'BYPASS': this._translate('status_bypass'), // UPS bypass circuit is active (no battery protection available)
            'CAL': this._translate('status_cal'),       // Performing runtime calibration (on battery)
            'OFF': this._translate('status_off'),       // UPS is offline
            'OVER': this._translate('status_over'),     // UPS is overloaded
            'TRIM': this._translate('status_trim'),     // UPS is trimming incoming voltage
            'BOOST': this._translate('status_boost'),   // UPS is boosting incoming voltage
            'FSD': this._translate('status_fsd'),       // Forced Shutdown
        };

        // Return the mapped translation or the original status code if no translation is found
        return statusMapping[statusCode] || statusCode;
    }

    // Creates a row for the UPS load, including the percentage and optional nominal power.
    _makeUpsLoadRow(labelKey, loadpct, nompower) {
        let text = loadpct.toFixed(1) + ' %';
        if (nompower) {
            text += ` ( ~ ${nompower} W )`;
        }
        return this._makeProgressBarRow(labelKey, loadpct, text);
    }

    // Creates a row with a progress bar, optionally including custom text.
    _makeProgressBarRow(labelKey, progress, progressText = `${progress.toFixed(1)} %`) {
        const pb = this._makeProgressBar(progress, progressText); // Create the progress bar.
        return this._makeRow(labelKey, pb); // Create a row with the progress bar.
    }

    // Creates a row with text, applying color based on regular expressions.
    _makeColoredTextRow(labelKey, value, okRegexp, errRegexp, check_value = value) {
        const textEl = $('<b></b>').text(value); // Create a bold text element with the value.

        // Apply CSS classes based on regex matches.
        if (okRegexp?.exec(check_value)) {
            textEl.addClass('text-success');
        } else if (errRegexp?.exec(check_value)) {
            textEl.addClass('text-danger');
        } else {
            textEl.addClass('text-warning');
        }

        return this._makeRow(labelKey, textEl.prop('outerHTML')); // Create a row with the colored text.
    }

    // Creates a progress bar with a text overlay.
    _makeProgressBar(progress, text) {
        const $textEl = $('<span class="text-center"></span>').text(text).css({
            position: 'absolute',
            left: 0,
            right: 0
        });

        const $barEl = $('<div class="progress-bar"></div>').css({
            width: `${progress}%`,
            zIndex: 0
        });

        return $('<div class="progress"></div>').append($barEl, $textEl).prop('outerHTML');
    }

    // Creates a text row for the table.
    _makeTextRow(labelKey, content) {
        content = typeof content === 'string' ? content : content.value; // Ensure content is a string.
        return this._makeRow(labelKey, content); // Create a row with the text content.
    }

    // Creates a row with a label and content.
    _makeRow(labelKey, content) {
        return [this._translate(labelKey), content];
    }

    // Translates a key into the corresponding text.
    _translate(key) {
        let value = this.translations[key];
        if (value === undefined) {
            console.error('Missing translation for ' + key);
            // Fallback to the key itself if translation is missing.
            value = key;
        }
        return value;
    }
}
