// endpoint:/api/nut/diagnostics/upsstatus

/*
 * Copyright (C) 2024 Deciso B.V.
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

export default class Nut extends BaseTableWidget {
    constructor() {
        super();
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $nuttable = this.createTable('nut-table', {
            headerPosition: 'left',
        });
        $container.append($nuttable);
        return $container;
    }

    async onWidgetTick() {
       await ajaxGet('/api/nut/diagnostics/upsstatus', {}, (data, status) => {
        let rows = [];
         const keysOfInterest = [
            'battery.charge', 'device.model', 'device.serial', 'device.type',
            'driver.name', 'driver.state', 'input.voltage', 'output.voltage',
            'ups.load', 'ups.status'
        ];

        const formatKey = (key) => {
            return key.split('.').map(word => {
                if (word.toLowerCase() === 'ups') {
                    return 'UPS';
                }
                return word.charAt(0).toUpperCase() + word.slice(1);
            }).join(' ');
        };

        // Ensure data is an object and has the response key
        if (!data || typeof data !== 'object' || !data.response) {
            console.error('Invalid data format received');
            return;
        }

        // Get the response string and split it into lines
        let lines = data.response.split('\n');

        for (let line of lines) {
            line = line.trim();
            if (line === '') continue; // Skip empty lines

            let [key, value] = line.split(':').map(item => item.trim());

            if (!key || !value || !keysOfInterest.includes(key)) {
                continue;
            }

            rows.push([formatKey(key), value]);
        }

        // Update the table
        super.updateTable('nut-table', rows);
    });
  }
}
