/*
 * Copyright (C) 2025 Neil Merchant
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

export default class DMIDecode extends BaseTableWidget {
  constructor() {
    super();
    this.title = 'DMIDecode Data';
  }

  getGridOptions() {
    return {
      sizeToContent: 650
    }
  }

  getMarkup() {
    let $container = $('<div></div>');
    // make table for system output, add header
    let $system_table = super.createTable('system-table', { headerPosition: 'none' });
    $container.append(`<h3>${this.translations.system}</h3>`)
    $container.append($system_table);
    // same for bios output
    let $bios_table = super.createTable('bios-table', { headerPosition: 'none' });
    $container.append(`<h3>${this.translations.bios}</h3>`)
    $container.append($bios_table);
    return $container;
  }

  async onMarkupRendered() {
    const dmiData = await this.ajaxCall('/api/dmidecode/service/get');
    if (!dmiData || dmiData?.status !== 'ok') {
      this.displayError('dmi lookup failed');
      return;
    }
    this.processDMIData(dmiData);
  }

  processDMIData(data) {
    const sysrows = [];
    for (const [key, value] of Object.entries(data.system)) {
      const row = [];
      // try to find translation for key, fallback to output value
      // have to split on spaces here because those aren't valid in xml tags
      const translationIndex = key.split(" ")[0]
      const dispKey = this.translations[translationIndex] || key
      row.push(`<div><b>${dispKey}</b></div>`, `<div>${value}</div>`);
      sysrows.push(row);
    }
    const biosrows = [];
    for (const [key, value] of Object.entries(data.bios)) {
      const row = [];
      // try to find translation for key, fallback to output value
      // have to split on spaces here because those aren't valid in xml tags
      const translationIndex = key.split(" ")[0]
      const dispKey = this.translations[translationIndex] || key
      row.push(`<div><b>${dispKey}</b></div>`, `<div>${value}</div>`);
      biosrows.push(row);
    }
    super.updateTable('system-table', sysrows);
    super.updateTable('bios-table', biosrows);
  }

  displayError(message) {
    // if something went wrong, display error message in system table
    const $error = $(`
        <div>
            ${message}
        </div>
    `);
    $('#system-table').empty().append($error);
}

}