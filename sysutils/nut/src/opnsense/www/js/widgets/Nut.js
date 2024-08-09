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
    this.statusInfo = {
      OL: { color: "text-success", fullName: "On Line" },
      OB: { color: "text-danger", fullName: "On Battery" },
      LB: { color: "text-danger", fullName: "Low Battery" },
      RB: { color: "text-warning", fullName: "Replace Battery" },
    };
  }

  getMarkup() {
    let $container = $("<div></div>");
    let $nuttable = this.createTable("nut-table", {
      headerPosition: "left",
    });
    $container.append($nuttable);
    return $container;
  }

  getBatteryChargeColor(charge) {
    if (charge == 100) return "text-success";
    if (charge > 0) return "text-warning";
    return "text-danger";
  }

  getUpsLoadColor(load) {
    return load == 0 ? "text-success" : "text-warning";
  }

  async onWidgetTick() {
    await ajaxGet("/api/nut/diagnostics/upsstatus", {}, (data, status) => {
      let rows = [];
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
      ];

      const formatKey = (key) => {
        let formattedKey = key
          .split(".")
          .map((word) => {
            if (word.toLowerCase() === "ups") {
              return "UPS";
            }
            return word.charAt(0).toUpperCase() + word.slice(1);
          })
          .join(" ");

        // Add icons for all keys
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
        };

        const icon = iconMap[key] || "";
        return icon
          ? `<i class="fa ${icon}" style="font-size: 11px;"></i>&nbsp;${formattedKey}`
          : formattedKey;
      };

      if (!data || typeof data !== "object" || !data.response) {
        console.error("Invalid data format received");
        return;
      }

      let lines = data.response.split("\n");
      let upsStatus = "";
      let batteryCharge = "";
      let upsLoad = "";

      for (let line of lines) {
        line = line.trim();
        if (line === "") continue;

        let [key, value] = line.split(":").map((item) => item.trim());

        if (!key || !value || !keysOfInterest.includes(key)) {
          continue;
        }

        if (key === "ups.status") {
          upsStatus = value;
          continue;
        }

        if (key === "battery.charge") {
          batteryCharge = value;
          continue;
        }

        if (key === "ups.load") {
          upsLoad = value;
          continue;
        }

        rows.push([formatKey(key), value]);
      }

      // Create UPS Status row with icon
      if (upsStatus) {
        let statusCodes = upsStatus.split(" ");
        let primaryStatus = statusCodes[0];
        let primaryInfo = this.statusInfo[primaryStatus] || {
          color: "text-muted",
          fullName: "Unknown",
        };

        let displayStatus = statusCodes
          .map((code) => {
            let info = this.statusInfo[code] || {
              color: "text-muted",
              fullName: code,
            };
            return `<span class="${info.color}" style="font-weight: bold;">${info.fullName}</span>`;
          })
          .join(", ");

        let $header = formatKey("ups.status");
        let $value = $(`
                    <div>
                        <i class="fa fa-circle ${primaryInfo.color} nut-status-icon" style="font-size: 11px; cursor: pointer;"
                            data-toggle="tooltip" title="${primaryInfo.fullName}">
                        </i>
                        &nbsp;${displayStatus}
                    </div>
                `);
        rows.unshift([$header, $value.prop("outerHTML")]);
      }

      // Create Battery Charge row with icon
      if (batteryCharge) {
        let chargeValue = parseInt(batteryCharge);
        let chargeColor = this.getBatteryChargeColor(chargeValue);

        let $header = formatKey("battery.charge");
        let $value = $(`
                    <div>
                        <i class="fa fa-circle ${chargeColor}" style="font-size: 11px; cursor: pointer;"
                            data-toggle="tooltip" title="${chargeValue}%">
                        </i>
                        &nbsp;${batteryCharge}
                    </div>
                `);

        rows.splice(1, 0, [$header, $value.prop("outerHTML")]);
      }

      // Create UPS Load row with icon
      if (upsLoad) {
        let loadValue = parseFloat(upsLoad);
        let loadColor = this.getUpsLoadColor(loadValue);

        let $header = formatKey("ups.load");
        let $value = $(`
                    <div>
                        <i class="fa fa-circle ${loadColor}" style="font-size: 11px; cursor: pointer;"
                            data-toggle="tooltip" title="${loadValue}%">
                        </i>
                        &nbsp;${upsLoad}
                    </div>
                `);

        rows.splice(2, 0, [$header, $value.prop("outerHTML")]);
      }

      // Update the table
      super.updateTable("nut-table", rows);

      // Initialize tooltips
      $(".nut-status-icon, .fa-circle").tooltip({ container: "body" });
    });
  }
}
