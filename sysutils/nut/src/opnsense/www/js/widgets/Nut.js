/*
 * Copyright (C) 2024 DollarSign23
 * Copyright (C) 2024 Nicola Pellegrini
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
    constructor() {
        super();
        this.timeoutPeriod = 1000; // Set a timeout period for AJAX calls or other timed operations.
    }

    getGridOptions() {
        return {
            // Trigger overflow-y:scroll after 650px height
            sizeToContent: 650
        };
    }

    // Creates and returns the HTML structure for the widget, including a table without a header.
    getMarkup() {
        let $container = $('<div></div>'); // Create a container div.
        let $nut_table = this.createTable('nut-table', {
            headerPosition: 'none', // Disable table headers.
        });
        $container.append($nut_table); // Append the table to the container.
        return $container; // Return the container with the table.
    }

    // Periodically called to update the widget's data and UI.
    async onWidgetTick() {
        // Fetch the NUT service status from the server.
        const nut_service_status = await this.ajaxCall(`/api/nut/${'service/status'}`);

        // If the service is not running, display a message and stop further processing.
        if (!nut_service_status || nut_service_status.status !== 'running') {
            $('#nut-table').html(`<a href="/ui/nut/index">${this.translations.unconfigured}</a>`);
            return;
        }

        // Fetch the NUT settings from the server.
        const nut_settings = await this.ajaxCall(`/api/nut/${'settings/get'}`);

        // // If netclient is not enabled, display a message and stop further processing.
        // if (nut_settings.nut?.netclient?.enable !== "1") {
        //     $('#nut-table').html(`<a href="/ui/nut/index#subtab_nut-ups-netclient">${this.translations.netclient_unconfigured}</a>`);
        //     return;
        // }

        // Fetch the UPS status data from the server.
        const { response: nut_ups_status_response } = await this.ajaxCall(`/api/nut/${'diagnostics/upsstatus'}`);

        // Parse the UPS status data into a key-value object.
        const nut_ups_status = nut_ups_status_response.split('\n').reduce((acc, line) => {
            const [key, value] = line.split(': ');
            if (key) acc[key] = value; // Only add non-empty keys.
            return acc;
        }, {});

        // Use the dataChanged method to check if the data has changed since the last tick
        if (!this.dataChanged('ups_status', nut_ups_status)) {
            return;
        }

        // Prepare the rows for the table based on the fetched data.
        const rows = [
            // Display the remote server address if available.
            nut_settings.nut?.netclient?.address && nut_settings.nut?.netclient?.address && nut_settings.nut?.netclient?.user && this.makeTextRow("netclient_remote_server", `${nut_settings.nut?.netclient?.user}@${nut_settings.nut?.netclient?.address}:${nut_settings.nut?.netclient?.port}`),
            // Display the manufacturer and model if available.
            nut_ups_status['device.mfr'] && nut_ups_status['device.model'] && this.makeTextRow("status_model", `${nut_ups_status['device.mfr']} - ${nut_ups_status['device.model']}`),
            // Display the UPS Status if available.
            nut_ups_status['ups.status'] && this.makeColoredTextRow('status_status', this.nutMapStatus(nut_ups_status['ups.status']), /OL/, /OB|LB|RB|DISCHRG/, nut_ups_status['ups.status']),
            // Display the UPS load with percentage and optional nominal power.
            nut_ups_status['ups.load'] && nut_ups_status['ups.realpower'] && this.makeUpsLoadRow('status_load', parseFloat(nut_ups_status['ups.load']), parseFloat(nut_ups_status['ups.realpower'])),
            // Display the battery charge as a progress bar if available.
            nut_ups_status['battery.charge'] && this.makeProgressBarRow("status_bcharge", parseFloat(nut_ups_status['battery.charge'])),
            // Display the battery status if available.
            nut_ups_status['battery.charger.status'] && this.makeTextRow('status_battery', nut_ups_status['battery.charger.status']),
            // Display the formatted battery runtime if available.
            nut_ups_status['battery.runtime'] && this.makeTextRow('status_timeleft', this.formatRuntime(parseInt(nut_ups_status['battery.runtime'], 10))),
            // Display the input voltage and frequency if available.
            nut_ups_status['input.voltage'] && nut_ups_status['input.frequency'] && this.makeTextRow('status_input_power', `${nut_ups_status['input.voltage']} V | ${nut_ups_status['input.frequency']} Hz`),
            // Display the output voltage and frequency if available.
            nut_ups_status['output.voltage'] && nut_ups_status['output.frequency'] && this.makeTextRow('status_output_power', `${nut_ups_status['output.voltage']} V | ${nut_ups_status['output.frequency']} Hz`),
            // Display the result of the UPS efficiency if available.
            nut_ups_status['ups.efficiency'] && this.makeTextRow('status_efficiency', `${nut_ups_status['ups.efficiency']}%`),
            // Display the result of the UPS self-test if available.
            nut_ups_status['ups.test.result'] && this.makeTextRow('status_selftest', nut_ups_status['ups.test.result']),
        ].filter(Boolean); // Remove any undefined or null rows.

        // Update the table with the prepared rows.
        this.updateTable('nut-table', rows);
    }

    // Formats the runtime (in seconds) into a human-readable format (hours, minutes, seconds).
    formatRuntime(seconds) {
        const hours = Math.floor(seconds / 3600); // Calculate full hours.
        const minutes = Math.floor((seconds % 3600) / 60); // Calculate remaining full minutes.
        const remainingSeconds = seconds % 60; // Calculate remaining seconds.

        let formattedTime = '';

        if (hours > 0) {
            formattedTime += `${hours}${this.translate('time_hours')} `;
        }
        if (minutes > 0 || hours > 0) { // Only show minutes if they are > 0 or hours are present.
            formattedTime += `${minutes}${this.translate('time_minutes')} `;
        }
        formattedTime += `${remainingSeconds}${this.translate('time_seconds')}`; // Always show seconds.

        return formattedTime.trim(); // Remove any trailing spaces.
    }

    // Create a mapping between UPS status codes and their corresponding translations
    nutMapStatus(statusCode) {
        const statusMapping = {
            'OL': this.translate('status_ol'),         // On line (mains is present)
            'OB': this.translate('status_ob'),         // On battery (mains is not present)
            'LB': this.translate('status_lb'),         // Low battery
            'HB': this.translate('status_hb'),         // High battery
            'RB': this.translate('status_rb'),         // Battery needs to be replaced
            'CHRG': this.translate('status_chrg'),     // Battery is charging
            'DISCHRG': this.translate('status_dischrg'), // Battery is discharging
            'BYPASS': this.translate('status_bypass'), // UPS bypass circuit is active (no battery protection available)
            'CAL': this.translate('status_cal'),       // Performing runtime calibration (on battery)
            'OFF': this.translate('status_off'),       // UPS is offline
            'OVER': this.translate('status_over'),     // UPS is overloaded
            'TRIM': this.translate('status_trim'),     // UPS is trimming incoming voltage
            'BOOST': this.translate('status_boost'),   // UPS is boosting incoming voltage
            'FSD': this.translate('status_fsd'),       // Forced Shutdown
        };

        // Return the mapped translation or the original status code if no translation is found
        return statusMapping[statusCode] || statusCode;
    }

    // Creates a row for the UPS load, including the percentage and optional nominal power.
    makeUpsLoadRow(labelKey, loadpct, nompower) {
        let text = loadpct.toFixed(1) + ' %';
        if (nompower) {
            text += ` ( ~ ${nompower} W )`;
        }
        return this.makeProgressBarRow(labelKey, loadpct, text);
    }

    // Creates a row with a progress bar, optionally including custom text.
    makeProgressBarRow(labelKey, progress, progressText = `${progress.toFixed(1)} %`) {
        const pb = this.makeProgressBar(progress, progressText); // Create the progress bar.
        return this.makeRow(labelKey, pb); // Create a row with the progress bar.
    }

    // Creates a row with text, applying color based on regular expressions.
    makeColoredTextRow(labelKey, value, okRegexp, errRegexp, check_value = value) {
        const textEl = $('<b></b>').text(value); // Create a bold text element with the value.

        // Apply CSS classes based on regex matches.
        if (okRegexp?.exec(check_value)) {
            textEl.addClass('text-success');
        } else if (errRegexp?.exec(check_value)) {
            textEl.addClass('text-danger');
        } else {
            textEl.addClass('text-warning');
        }

        return this.makeRow(labelKey, textEl.prop('outerHTML')); // Create a row with the colored text.
    }

    // Creates a progress bar with a text overlay.
    makeProgressBar(progress, text) {
        const $textEl = $('<span class="text-center"></span>').text(text).css({
            position: 'absolute',
            left: 0,
            right: 0
        });

        const $barEl = $('<div class="progress-bar"></div>').css({
            width: `${progress}%`,
            zIndex: 0
        });

        return $('<div class="progress"></div>').append($barEl, $textEl).prop("outerHTML");
    }

    // Creates a text row for the table.
    makeTextRow(labelKey, content) {
        content = typeof content === 'string' ? content : content.value; // Ensure content is a string.
        return this.makeRow(labelKey, content); // Create a row with the text content.
    }

    // Creates a row with a label and content.
    makeRow(labelKey, content) {
        return [this.translate(labelKey), content];
    }

    // Translates a key into the corresponding text.
    translate(key) {
        let value = this.translations[key];
        if (value === undefined) {
            console.error('Missing translation for ' + key);
            value = key; // Fallback to the key itself if translation is missing.
        }
        return value;
    }
}
