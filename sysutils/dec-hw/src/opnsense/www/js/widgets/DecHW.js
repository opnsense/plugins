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

export default class DecHW extends BaseWidget {
    constructor() {
        super();
    }

    getMarkup() {
        const styles = `
            #status {
            margin: 10px;
            }
            .power {
            margin: 5px;
            float: right;
            }
            .power:hover {
            opacity: 0.5;
            }
            .pwr-container {
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
            <div class="pwr-container">
                <div id="pwr1" class="data-item">
                    <strong>${this.translations.powersupply} 1</strong>
                </div>
                <div id="pwr2" class="data-item">
                    <strong>${this.translations.powersupply} 2</strong>
                </div>
            </div>
        `);
    }

    async onWidgetTick() {
        $('.power').tooltip('hide');
        let data = await this.ajaxCall('/api/dechw/info/powerStatus');

        if (!data || data.status === 'failed') {
            $('#status').html(`<div class="error-message" style="margin: 10px;">${this.translations.nopower}</div>`);
            $('.pwr-container').hide();
            return;
        }

        $('.power').remove();
        ['pwr1', 'pwr2'].forEach((key) => {
            let status = data[key];

            let $power = $(`<span class="power fa fa-power-off fa-lg" data-toggle="tooltip" title=""></span>`);
            $power.css('color', status === '1' ? 'blue' : 'red');
            $power.attr('title', status === '1' ? this.translations.poweron : this.translations.poweroff);
            $(`#${key}`).append($power);
        });

        $('.power').tooltip({container: 'body'});
    }
}
