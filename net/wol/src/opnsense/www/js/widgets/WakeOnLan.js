/*
 * Copyright (C) 2024 Michał Brzeziński
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

export default class WakeOnLan extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 650
        }
    }

    getMarkup() {
        let $container = $('<div id="wol-clients-container"></div>');
        let $wol_table = this.createTable('wol-table', {
            headerPosition: 'none'
        });

        $container.append($wol_table);
        return $container;
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/wol/wol/searchHost');

        let rows = [];
        if (data.total == 0) {
          const empty_list = [`<b>${this.translations.msg_empty_wol}</b>`];
          rows.push(empty_list);
        } else {
          const header = [`<b>${this.translations.h_device}</b>`,
                          `<b>${this.translations.h_interface}</b>`,
                          `<b>${this.translations.h_status}</b>`,
                          ''];
          rows.push(header);

          //NOTE: this ARP list call is the most expensive one and can grow substantialy in big networks
          //With previous widget it had been done on the backend side with direct exec of 'arp -an | grep ...'
          const arp = await this.ajaxCall(`/api/diagnostics/interface/getArp${''}`);

          for(let it = 0; it < data.rows.length; it++){
              const item = data.rows[it];
              let is_active = this.checkActive(arp, item.mac, item.interface);
              let row = [
                  `${item.descr.length !== 0 ? item.descr + '<br/>': ''} ${item.mac}`,
                  `${item.interface}`,
                  `<i class="fa fa-${is_active == 1 ? "play" : "remove"} fa-fw text-${is_active == 1 ? "success" : "danger"}" ></i>
                   ${ is_active == 1 ? "Online" : "Offline"}`,
                  `<button class="btn btn-primary btn-xs wakeupbtn" data-uuid="${item.uuid}">
                    <i class="fa fa-bolt fa-fw" title="Wake Up"></i>
                   </button>`
                  ];
              rows.push(row);
        }
    }
    super.updateTable('wol-table', rows);

    $('.wakeupbtn').on('click', async (event) => {
      event.preventDefault();
      let btn = $(event.currentTarget).find('i');
      /* the call is quick, omit fa-spinner fa-pulse use */
      const data = {uuid: $(event.currentTarget).data('uuid')};
      const result = await this.ajaxCall('/api/wol/wol/set', JSON.stringify(data), 'POST').then(() => {
          btn.removeClass('fa-bolt').addClass('fa-check');
      });
      event.currentTarget.blur();
    });
  }

  checkActive(arp_list, mac, intf) {
    const arp = arp_list.find((obj) => obj.mac === mac.toLowerCase());
    if (arp === undefined) {
      return 0;
    } else {
      return (arp.expired === false && arp.intf_description === intf);
    }
  }
}
