/*
 * Copyright (C) 2024 Nicola Pellegrini
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

/**
 * @typedef ApcUpsdStatusValue
 *
 * @property {string|number} norm - normalized value
 * @property {string|number} value - raw value
 */

/**
 * @typedef {Object.<string, ApcUpsdStatusValue>} ApcUpsdStatus
 */

export default class ApcUpsd extends BaseTableWidget {
    constructor() {
        super();
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $apcupsdTable = this.createTable('apcupsd-table', {
            headerPosition: 'left'
        });
        $container.append($apcupsdTable);
        return $container;
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/apcupsd/service/getUpsStatus');

        if (data.error) {
            this.displayError(data.error);
            return;
        }

        let rows = this.makeRows(data.status);
        super.updateTable('apcupsd-table', rows);
    }

    /**
     * Displays an error text row
     *
     * @param {string} text
     */
    displayError(text) {
        // A row with `flextable-row` class will be auto removed on next tick by
        // `this.updateTable(...)`, if the error is gone
        const $row = $('<div class="flextable-row text-danger" style="justify-content: center"></div>').text(text);
        // Replace all the content in the table
        $(`#apcupsd-table`).html($row);
    }

    /**
     * @param {ApcUpsdStatus} status
     * @returns {Array<Array>>}
     */
    makeRows(status) {
        const rows = [];

        if (status.MODEL) {
            rows.push(this.makeTextRow("status_model", status.MODEL));
        }

        if (status.STATUS) {
            rows.push(
                // Note: text value is not translated because it comes directly from apcupsd output
                this.makeColoredTextRow(
                    "status_status",
                    status.STATUS.value,
                    /ONLINE/, // success
                    /ONBATT|LOWBATT|REPLACEBATT|NOBATT|SLAVEDOWN|COMMLOST/ // error
                )
            );
        }

        if (status.SELFTEST) {
            const raw_value = status.SELFTEST.value;

            // self test values
            const value_keys = {
                OK: 'status_selftest_ok',
                BT: 'status_selftest_bt',
                NG: 'status_selftest_ng',
                NO: 'status_selftest_no',
            };

            // Try to translate it, otherwise use the raw value
            const text_value = value_keys[raw_value] ? this.translate(value_keys[raw_value]) : raw_value;

            if (raw_value === 'NO') {
                // Special case for no results, no color
                rows.push(this.makeTextRow('status_selftest', text_value));
            } else {
                rows.push(this.makeColoredTextRow(
                    'status_selftest',
                    text_value,
                    /OK/,    // success
                    /BT|NG/, // error, all other unknown states are "warnings"
                    raw_value
                ));
            }
        }

        if (status.LINEV) {
            rows.push(this.makeTextRow('status_linev', status.LINEV));
        }

        // NOMPOWER not checked because it's optional and it might not be available
        if (status.LOADPCT) {
            rows.push(this.makeUpsLoadRow('status_load', status.LOADPCT, status.NOMPOWER))
        }

        if (status.BCHARGE) {
            rows.push(this.makeProgressBarRow('status_bcharge', status.BCHARGE.norm));
        }

        if (status.TIMELEFT) {
            rows.push(this.makeTextRow('status_timeleft', status.TIMELEFT));
        }

        if (status.BATTV) {
            rows.push(this.makeTextRow('status_battv', status.BATTV));
        }

        if (status.BATTDATE) {
            let date = status.BATTDATE;
            date = date.norm || date.value;
            rows.push(this.makeDateRow('status_battdate', date, 'll', 'YYYY-MM-DD'));
        }

        if (status.ITEMP) {
            rows.push(this.makeTextRow("status_itemp", status.ITEMP));
        }

        if (status.DATE) {
            let date = status.DATE;
            date = date.norm || date.value;
            rows.push(this.makeDateRow('status_date', date, 'll LTS'));
        }

        return rows;
    }

    /**
     * Makes a date row, parsing the given date with moment js (if available and the date is valid)
     * and shows it with the new given format
     *
     * @param {string} labelKey - label translation key
     * @param {string} dateString - date string
     * @param {string} newFormat - new format to use for the date string
     * @param {string} [parseFormat] - optional format used to parse the date string with moment js
     *
     * @returns {Array}
     */
    makeDateRow(labelKey, dateString, newFormat, parseFormat) {
        const withMomentJs = moment && moment.version && typeof moment === 'function';
        let text = dateString;
        if (withMomentJs) {
            const m = moment(dateString, parseFormat);
            if (m.isValid()) {
                m.locale(window.navigator.language);
                // override text
                text = m.format(newFormat);
            }
        }

        return this.makeTextRow(labelKey, text);
    }

    /**
     * Makes a row that shows the load value % and estimated watts usage if NOMPOWER is also given
     *
     * @param {string} labelKey - label translation key
     * @param {ApcUpsdStatusValue} loadpct - LOADPCT status value
     * @param {ApcUpsdStatusValue} [nompower] - NOMPOWER status value
     *
     * @returns {Array}
     */
    makeUpsLoadRow(labelKey, loadpct, nompower) {
        let text = loadpct.norm.toFixed(1) + ' %';
        if (nompower) {
            const watts = Math.round(loadpct.norm * nompower.norm / 100);
            text += ` ( ~ ${watts} W )`;
        }
        return this.makeProgressBarRow(labelKey, loadpct.norm, text);
    }

    /**
     * Makes a progress bar row
     *
     * @param {string} labelKey - label translation key
     * @param {number} progress - current progress (0-100)
     * @param {string} [progressText] - text shown on top of the progress bar, defaults to "<progress> %"
     *
     * @returns {Array}
     */
    makeProgressBarRow(labelKey, progress, progressText) {
        progressText = progressText || `${progress.toFixed(1)} %`;
        const pb = this.makeProgressBar(progress, progressText);
        return this.makeRow(labelKey, pb);
    }

    /**
     * Makes a row with a progress bar value cell
     *
     * @param {number} progress - current progress (0-100)
     * @param {string} text - text shown on top of the progress bar
     *
     * @returns {Array}
     */
    makeProgressBar(progress, text) {
        const $textEl = $('<span class="text-center"></span>').text(text).css({
            position: 'absolute',
            left: 0,
            right: 0
        });

        const $barEl = $('<div class="progress-bar"></div>').css({
            width: `${progress}%`,
            zIndex: 0
        });

        return $('<div class="progress"></div>').append($barEl, $textEl).prop("outerHTML");
    }

    /**
     * Makes a text row, coloring the value cell text depending on the regex matches
     *
     * - No match: warning color
     * - Ok regex match: success color
     * - Err regex match: danger color
     *
     * @param {string} labelKey - label translation key
     * @param {string} value - a string value that is matched with the regexes for highlighting.
     *                         If the value needs to be translated use `check_value` for matching
     * @param {RegExp} okRegexp - regex to apply success state CSS class
     * @param {RegExp} errRegexp - regex to apply danger/error state CSS class
     * @param {string} [check_value] - value to match against, if not set it defaults to the `value` argument
     *
     * @returns {Array}
     */
    makeColoredTextRow(labelKey, value, okRegexp, errRegexp, check_value) {
        check_value = check_value ?? value;

        const textEl = $('<b></b>').text(value);
        if (okRegexp && okRegexp.exec(check_value)) {
            textEl.addClass('text-success');
        } else if (errRegexp && errRegexp.exec(check_value)) {
            textEl.addClass('text-danger');
        } else {
            textEl.addClass('text-warning');
        }

        let html = textEl.prop('outerHTML');

        return this.makeRow(labelKey, html);
    }

    /**
     * Makes a standard text row template
     *
     * @param {string} labelKey - label translation key
     * @param {string|ApcUpsdStatusValue} content - string value or an object with a value field
     *
     * @returns {Array}
     */
    makeTextRow(labelKey, content) {
        content = typeof content === 'string' ? content : content.value;

        return this.makeRow(labelKey, content);
    }

    /**
     * Make a row object to use with {@link updateTable}
     *
     * Auto translates the given label key if possible
     *
     * @param {string} labelKey - label translation key
     * @param {*} content - html content
     *
     * @returns {Array}
     */
    makeRow(labelKey, content) {
        return [this.translate(labelKey), content];
    }

    /**
     * Tries to translates the given key.
     *
     * If not found it logs an error and just returns the key as is.
     *
     * @param {string} key - key to translate
     *
     * @returns {string}
     */
    translate(key) {
        let value = this.translations[key];
        if (value === undefined) {
            console.error('Missing translation for ' + key);
            // Use the key as fallback
            value = key;
        }
        return value;
    }
}
