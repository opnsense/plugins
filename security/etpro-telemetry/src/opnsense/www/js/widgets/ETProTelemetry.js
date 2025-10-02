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

export default class ETProTelemetry extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 3600;
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $ETProTelemetrytable = this.createTable('ETProTelemetry-table', {
            headerPosition: 'left',
        });
        $container.append($ETProTelemetrytable);
        return $container;
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/diagnostics/proofpoint_et/status');
        if (data['sensor_status'].toLowerCase() == 'active') {
            $('#etpro_sensor_status').text(data['sensor_status']);
            $('#etpro_event_received').text(data['event_received']);
            $('#etpro_last_rule_download').text(data['last_rule_download']);
            $('#etpro_last_heartbeat').text(data['last_heartbeat']);
        } else {
            $('#etpro_sensor_status').text('-');
            $('#etpro_event_received').text('-');
            $('#etpro_last_rule_download').text('-');
            $('#etpro_last_heartbeat').text('-');
        }
    }

    async onMarkupRendered() {
        let rows = [];
        rows.push([['<img src="/ui/img/proofpoint.svg" style="height:30px;" class="image_invertible">'], '']);
        rows.push([[this.translations['sensor_status']], $('<span id="etpro_sensor_status">').prop('outerHTML')]);
        rows.push([[this.translations['event_received']], $('<span id="etpro_event_received">').prop('outerHTML')]);
        rows.push([[this.translations['last_rule_download']], $('<span id="etpro_last_rule_download">').prop('outerHTML')]);
        rows.push([[this.translations['last_heartbeat']], $('<span id="etpro_last_heartbeat">').prop('outerHTML')]);

        super.updateTable('ETProTelemetry-table', rows);
    }
}
