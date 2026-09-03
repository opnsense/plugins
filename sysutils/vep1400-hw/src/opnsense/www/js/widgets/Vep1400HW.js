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

export default class Vep1400Hw extends BaseTableWidget {
    constructor() {
        super();
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $sysinfotable = this.createTable('vepinfo-table', {
            headerPosition: 'left',
        });
        $container.append($sysinfotable);
        return $container;
    }

    async onWidgetTick() {
        let data = await this.ajaxCall('/api/vep1400hw/info/boardid');
        let boardid = data['boardid'];
        if ( boardid != null) {
            let lower_nibble = boardid.substring(3);
            if (lower_nibble != '0' && lower_nibble != '1' ) {
        
                let fandata = await this.ajaxCall('/api/vep1400hw/info/fanstatus');
        
                if (fandata['status'] != 'failed') {
                    $('#fan1').text(fandata['fan1'] + ' RPM');
                    $('#fan2').text(fandata['fan2'] + ' RPM');
                }
            }
        }
        let tempdata = await this.ajaxCall('/api/vep1400hw/info/tempstatus');
        
        $('#temp').text(tempdata['temp'] + '℃');
    }

    async onMarkupRendered() {
        let rows = [];
        let data = await this.ajaxCall('/api/vep1400hw/info/boardid');
        let boardid = data['boardid'];

        if (!boardid || boardid != null) {
            rows.push([[this.translations['boardid']], boardid]);
            let lower_nibble = boardid.substring(3);
            if (lower_nibble != '0' && lower_nibble != '1' ) {
                rows.push([[this.translations['fan']] + ' 1', $('<span id="fan1">').prop('outerHTML')]);
                rows.push([[this.translations['fan']] + ' 2', $('<span id="fan2">').prop('outerHTML')]);
            }
            rows.push([[this.translations['temp']], $('<span id="temp">').prop('outerHTML')]);
    
        }
        else {
            rows.push([[this.translations['boarderror']]]);
        }
        super.updateTable('vepinfo-table', rows);
    }
}