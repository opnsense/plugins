/*
 * Copyright (C) 2024 James Turnbull <james@lovedthanlost.net>

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

export default class Nut extends BaseTableWidget {
  constructor() {
    super();
    this.statusInfo = {
      OL: { color: "text-success", fullNameKey: "status_online" },
      OB: { color: "text-danger", fullNameKey: "status_onBattery" },
      LB: { color: "text-danger", fullNameKey: "status_lowBattery" },
      RB: { color: "text-warning", fullNameKey: "status_replaceBattery" },
    };

    this.previousData = null;
  }

  getMarkup() {
    let $container = $("<div></div>");
    let $nutTable = this.createTable("nut-table", {
      headerPosition: "left",
    });
    $container.append($nutTable);
    return $container;
  }

  async onWidgetTick() {
    try {
      const data = await this.ajaxCall("/api/nut/diagnostics/upsstatus");

      if (data.error) {
        this.displayError(data.error);
        return;
      }

      if (!data.response) {
        this.displayError(this.translations.unconfigured);
        return;
      }

      if (!this.dataChanged("nut", data)) {
        return;
      }

      let rows = this.makeRows(data.response);

      super.updateTable("nut-table", rows);

      $('[data-toggle="tooltip"]').tooltip({ container: "body" });
    } catch (error) {
      console.error("AJAX call failed:", error);
      this.displayError(this.translations.unconfigured);
    }
  }

  displayError(message) {
    const $row = $(
      '<div class="flextable-row text-danger" style="justify-content: center"></div>'
    ).text(message);
    $(`#nut-table`).html($row);
  }

  makeRows(response) {
    const rows = [];
    const keysOfInterest = [
      "ups.status",
      "battery.charge",
      "ups.load",
      "device.model",
      "device.serial",
      "device.type",
      "driver.name",
      "driver.state",
      "input.voltage",
      "output.voltage",
      "ups.realpower.nominal",
    ];

    const dataMap = {};

    let lines = response.split("\n");
    for (let line of lines) {
      line = line.trim();
      if (line === "") continue;

      let [key, value] = line.split(":").map((item) => item.trim());

      if (key && value && keysOfInterest.includes(key)) {
        dataMap[key] = value;
      }
    }

    for (let key of keysOfInterest) {
      if (dataMap[key]) {
        switch (key) {
          case "ups.status":
            rows.push(this.makeUpsStatusRow(key, dataMap[key]));
            break;
          case "battery.charge":
            rows.push(this.makeBatteryChargeRow(key, dataMap[key]));
            break;
          case "ups.load":
            rows.push(
              this.makeUpsLoadRow(
                key,
                dataMap[key],
                dataMap["ups.realpower.nominal"]
              )
            );
            break;
          default:
            rows.push(this.makeTextRow(key, dataMap[key]));
            break;
        }
      }
    }

    return rows;
  }

  makeUpsStatusRow(key, value) {
    let statusCodes = value.split(" ");
    let primaryStatus = statusCodes[0];

    let primaryInfo = this.statusInfo[primaryStatus] || {
      color: "text-muted",
      fullNameKey: "status_unknown",
    };
    primaryInfo.fullName =
      this.translate(primaryInfo.fullNameKey) || primaryInfo.fullNameKey;

    let displayStatus = statusCodes
      .map((code) => {
        let info = this.statusInfo[code] || {
          color: "text-muted",
          fullNameKey: "status_" + code,
        };
        info.fullName = this.translate(info.fullNameKey) || code;
        return `<span class="${info.color}" style="font-weight: bold;">${info.fullName}</span>`;
      })
      .join(", ");

    let $icon = $("<i>", {
      class: `fa fa-circle ${primaryInfo.color} nut-status-icon`,
      css: { fontSize: "11px", cursor: "pointer" },
      attr: { "data-toggle": "tooltip", title: primaryInfo.fullName },
    });

    let $value = $("<div>").append($icon, ` ${displayStatus}`);

    return [this.formatKey(key), $value.prop("outerHTML")];
  }

  makeBatteryChargeRow(key, value) {
    let chargeValue = parseFloat(value);
    return this.makeProgressBarRow(this.formatKey(key), chargeValue);
  }

  makeUpsLoadRow(key, value, nominalPower) {
    let loadPercentage = parseFloat(value);
    let nominalPowerValue = parseFloat(nominalPower);

    let estimatedLoadWatts = (loadPercentage / 100) * nominalPowerValue;

    let progressText = `${loadPercentage.toFixed(
      1
    )} % (${estimatedLoadWatts.toFixed(2)} Watts)`;
    const $textEl = $('<span class="text-center"></span>')
      .text(progressText)
      .css({
        position: "absolute",
        left: 0,
        right: 0,
      });

    const $barEl = $('<div class="progress-bar"></div>').css({
      width: `${loadPercentage}%`,
      zIndex: 0,
    });

    const $progressBar = $('<div class="progress"></div>').append(
      $barEl,
      $textEl
    );

    return [this.formatKey(key), $progressBar.prop("outerHTML")];
  }

  makeProgressBarRow(label, progress) {
    let progressText = `${progress.toFixed(1)} %`;
    const $textEl = $('<span class="text-center"></span>')
      .text(progressText)
      .css({
        position: "absolute",
        left: 0,
        right: 0,
      });

    const $barEl = $('<div class="progress-bar"></div>').css({
      width: `${progress}%`,
      zIndex: 0,
    });

    const $progressBar = $('<div class="progress"></div>').append(
      $barEl,
      $textEl
    );

    return [label, $progressBar.prop("outerHTML")];
  }

  makeTextRow(key, value) {
    const nonTranslatableKeys = [
      "device.model",
      "device.serial",
      "device.type",
      "driver.name",
      "driver.state",
      "input.voltage",
      "output.voltage",
      "ups.realpower.nominal",
    ];

    let displayValue;
    if (nonTranslatableKeys.includes(key)) {
      displayValue = value;
    } else {
      displayValue = this.translate(value) || value;
    }

    return [this.formatKey(key), displayValue];
  }

  formatKey(key) {
    const keyMap = {
      "ups.status": "status_ups_status",
      "battery.charge": "status_battery_charge",
      "ups.load": "status_ups_load",
      "device.model": "status_device_model",
      "device.serial": "status_device_serial",
      "device.type": "status_device_type",
      "driver.name": "status_driver_name",
      "driver.state": "status_driver_state",
      "input.voltage": "status_input_voltage",
      "output.voltage": "status_output_voltage",
      "ups.realpower.nominal": "status_realpower_nominal",
    };

    const translationKey = keyMap[key] || key;
    let formattedKey = this.translate(translationKey) || key;

    const iconMap = {
      "ups.status": "fa-power-off",
      "battery.charge": "fa-battery-full",
      "ups.load": "fa-bolt",
      "device.serial": "fa-barcode",
      "device.model": "fa-server",
      "device.type": "fa-plug",
      "driver.name": "fa-microchip",
      "driver.state": "fa-info-circle",
      "input.voltage": "fa-arrow-right",
      "output.voltage": "fa-arrow-left",
      "ups.realpower.nominal": "fa-lightbulb-o",
    };

    const icon = iconMap[key] || "";
    if (icon) {
      const $icon = $("<i>", {
        class: `fa ${icon}`,
        css: { fontSize: "11px" },
      });
      return $icon.prop("outerHTML") + `&nbsp;${formattedKey}`;
    } else {
      return formattedKey;
    }
  }

  translate(value) {
    let translatedValue = this.translations[value];
    if (translatedValue === undefined) {
      console.error("Missing translation for " + value);
      translatedValue = value;
    }
    return translatedValue;
  }
}
