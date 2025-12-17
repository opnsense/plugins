/*
 * Copyright (C) 2025 Deciso B.V.
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

export default class QFeeds extends BaseTableWidget {
    constructor() {
        super();
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $sysinfotable = this.createTable('qfeeds-table', {
            headerPosition: 'left',
        });
        $container.append($sysinfotable);
        return $container;
    }

    async onWidgetTick() {
        return;
    }

    async onMarkupRendered() {
        let header = $("div.widget.widget-qfeeds").find('.widget-header');
        let title = $('#qfeeds-title');
        let divider = $("div.widget.widget-qfeeds").find('.panel-divider');
        header.css({
            'background-image': 'URL("/ui/img/QFeeds.png")',
            'background-size': 'auto 50px',
            'background-position': 'center left',
            'margin-top': '0px',
            'mix-blend-mode': 'difference',
            'background-repeat': 'no-repeat'
        });
        title.empty();
        title.css({
            'height': '70px'
        })
        divider.hide();
        $("#qfeeds-table").css({
            'margin-top': '0px',
            'margin-bottom': '5px',
        });

        const data = await this.ajaxCall(`/api/q_feeds/settings/${'stats'}`);
        if (!data.feeds.length) {
            $('#qfeeds-table').html(`${this.translations.no_feed}`);
            return;
        }
        let rows = [];
        let feeds = [];
        let licenseInfoShown = false;

        for (let feed of data.feeds) {
            feeds.push(
                `<b><i class="fa fa-fw fa-angle-right" aria-hidden="true"></i> ${feed.name}</b>`,
                `<div><i class="fa fa-fw fa-circle-o" aria-hidden="true"></i> &nbsp;&nbsp;${this.translations.last_update}: ${feed.updated_at}</div>`,
                `<div><i class="fa fa-fw fa-circle-o" aria-hidden="true"></i> &nbsp;&nbsp;${this.translations.next_update}: ${feed.next_update}</div>`
            );
            if (feed.licensed) {
                let licenseText = this.translations.licensed;
                if (data.license && data.license.name) {
                    licenseText += ` (${data.license.name})`;
                }
                feeds.push(`<div><i class="fa fa-fw fa-check" aria-hidden="true"></i> &nbsp;&nbsp;${licenseText}</div>`);
                if (!licenseInfoShown && data.license && data.license.expiry_date) {
                    const expiryDate = new Date(data.license.expiry_date);
                    if (!isNaN(expiryDate.getTime())) {
                        const formattedDate = expiryDate.toLocaleDateString();
                        feeds.push(`<div><i class="fa fa-fw fa-calendar" aria-hidden="true"></i> &nbsp;&nbsp;Expires: ${formattedDate}</div>`);
                    }
                    licenseInfoShown = true;
                }
            } else {
                feeds.push(`<div><i class="fa fa-fw fa-close" aria-hidden="true"></i> &nbsp;&nbsp;${this.translations.unlicensed}</div>`);
            }
        }
        rows.push([[this.translations.installed_feeds], feeds]);
        let db = [
            `<div><b>${this.translations.size}</b>: ${data.totals.entries.toLocaleString()}</div>`,
            `<div><b>${this.translations.blocked}</b>: ${data.totals.addresses_blocked.toLocaleString()}</div>`

        ];
        rows.push([[this.translations.database], db]);

        super.updateTable('qfeeds-table', rows);
    }
}
