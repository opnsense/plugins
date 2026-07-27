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

export default class VepHW extends BaseWidget {
    constructor() {
        super();
    }

    getMarkup() {
        const styles = `
            #status {
            margin: 10px;
            }
            .fan {
            padding: 10px;
            margin: 5px;
            width: 50%;
            display: inline-block
            }
            .fan-container {
            margin: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            }
            .data-item {
            padding: 10px;
            border: 1px solid #ddd;
            margin: 5px;
            width: 50%;
            display: inline-block;
            }
        `;

        const styleSheet = document.createElement("style");
        styleSheet.innerText = styles;
        document.head.appendChild(styleSheet);

        return $(`
            <div id="status"></div>
            <div class="fan-container">
                <div id="fan1" class="data-item">
                    <strong>${this.translations.fan} 1: </strong>
                </div>
                <div id="fan2" class="data-item">
                    <strong>${this.translations.fan} 2: </strong>
                </div>
                <div id="temp" class="data-item">
                    <strong>${this.translation.temp} </strong>
                </div>
            </div>
        `);
    }

    async onWidgetTick() {
        $('.fan').tooltip('hide');
        let data = await this.ajaxCall('/api/vephw/info/fanstatus');

        if (!data || data.status === 'failed') {
            $('#status').html(`<div class="error-message" style="margin: 10px;">${this.translations.nofan}</div>`);
            $('.fan-container').hide();
            return;
        }

        $('.fan').remove();
        ['fan1', 'fan2'].forEach((key) => {
            let status = data[key];

            let $fan = $(`<span class="fan" data-toggle="" title="">${status}</span>`);
            //$fan.css('color', 'green');
            $(`#${key}`).append($fan);
        });
        let tempdata = await this.ajaxCall('/api/vephw/info/tempstatus');
        let $temp = $(`<span class="fan" data-toggle="" title="">${tempdata['temp']}</span>`);
        $(`#temp`).append($temp);
        $('.fan').tooltip({container: 'body'});
    }
}
