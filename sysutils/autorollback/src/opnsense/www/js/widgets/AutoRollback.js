/*
 * Copyright (C) 2026 MP Lindsey
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
 * OPNsense Auto Rollback - Dashboard Widget
 *
 * Shows real-time safe mode status with countdown, one-click
 * start/confirm/cancel controls directly from the dashboard.
 */
export default class AutoRollback extends BaseWidget {
    constructor() {
        super();
        this.tickTimeout = 2;
    }

    getMarkup() {
        return $(`
            <div id="autorollback-widget" style="padding: 8px 12px; font-family: inherit;">
                <style>
                    @keyframes arw-blink {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.3; }
                    }
                </style>
                <!-- Status indicator -->
                <div id="arw-status-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span id="arw-badge" style="
                        display: inline-flex; align-items: center; gap: 5px;
                        padding: 3px 10px; border-radius: 12px;
                        font-size: 11px; font-weight: 600; text-transform: uppercase;
                        letter-spacing: 0.4px; background: #e9ecef; color: #495057;
                    ">
                        <span id="arw-dot" style="
                            width: 7px; height: 7px; border-radius: 50%;
                            background: #6c757d; display: inline-block;
                        "></span>
                        <span id="arw-badge-text">Loading</span>
                    </span>
                    <span id="arw-method" style="font-size: 11px; color: #868e96;"></span>
                </div>

                <!-- Countdown (visible in safe mode) -->
                <div id="arw-countdown-section" style="display: none; text-align: center; margin: 8px 0;">
                    <div id="arw-countdown" style="
                        font-size: 36px; font-weight: 700;
                        font-variant-numeric: tabular-nums;
                        letter-spacing: -0.5px; line-height: 1.1;
                    ">0<span style="font-size: 13px; font-weight: 400; color: #868e96;">s</span></div>
                    <div style="height: 4px; background: #e9ecef; border-radius: 2px; margin: 6px 0; overflow: hidden;">
                        <div id="arw-bar" style="
                            height: 100%; width: 100%; border-radius: 2px;
                            background: #5cb85c; transition: width 1s linear, background 0.5s ease;
                        "></div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div id="arw-actions" style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <button id="arw-btn-start" class="btn btn-primary btn-xs" style="flex: 1; min-width: 80px; display: none;">
                        <i class="fa fa-shield"></i> Safe Mode
                    </button>
                    <button id="arw-btn-confirm" class="btn btn-success btn-xs" style="flex: 1; min-width: 80px; display: none;">
                        <i class="fa fa-check"></i> Confirm
                    </button>
                    <button id="arw-btn-revert" class="btn btn-danger btn-xs" style="flex: 1; min-width: 80px; display: none;">
                        <i class="fa fa-undo"></i> Revert
                    </button>
                    <button id="arw-btn-extend" class="btn btn-default btn-xs" style="min-width: 50px; display: none;">
                        +60s
                    </button>
                </div>

                <!-- Watchdog info -->
                <div id="arw-watchdog" style="margin-top: 8px; font-size: 11px; color: #868e96; display: none;">
                    <i class="fa fa-heartbeat"></i>
                    <span id="arw-watchdog-text">Watchdog: monitoring</span>
                </div>
            </div>
        `);
    }

    async onMarkupRendered() {
        const self = this;

        $('#arw-btn-start').on('click', async function() {
            $(this).prop('disabled', true);
            try {
                await self.ajaxCall('/api/autorollback/service/start', {}, 'POST');
                self.tickTimeout = 1;
            } catch(e) { /* ignore */ }
            await self.onWidgetTick();
        });

        $('#arw-btn-confirm').on('click', async function() {
            $(this).prop('disabled', true);
            try {
                await self.ajaxCall('/api/autorollback/service/confirm', {}, 'POST');
                self.tickTimeout = 2;
            } catch(e) { /* ignore */ }
            await self.onWidgetTick();
        });

        $('#arw-btn-revert').on('click', async function() {
            if (confirm('Revert to previous configuration? The system may reboot.')) {
                $(this).prop('disabled', true);
                try {
                    await self.ajaxCall('/api/autorollback/service/cancel', {}, 'POST');
                } catch(e) { /* ignore */ }
                await self.onWidgetTick();
            }
        });

        $('#arw-btn-extend').on('click', async function() {
            try {
                await self.ajaxCall('/api/autorollback/service/extend', JSON.stringify({seconds: 60}), 'POST');
            } catch(e) { /* ignore */ }
            await self.onWidgetTick();
        });
    }

    async onWidgetTick() {
        try {
            const data = await this.ajaxCall('/api/autorollback/service/status');
            if (!data || data.status === 'error') {
                this._renderError();
                return;
            }
            this._renderStatus(data);
        } catch(e) {
            this._renderError();
        }
    }

    _renderStatus(data) {
        const state = data.system_state || 'disabled';
        const safeMode = data.safe_mode || {};
        const watchdog = data.watchdog || {};

        const badge = $('#arw-badge');
        const dot = $('#arw-dot');
        const badgeText = $('#arw-badge-text');

        badge.css({'background': '#e9ecef', 'color': '#495057'});
        dot.css({'background': '#6c757d', 'animation': 'none'});

        if (state === 'safe_mode') {
            badge.css({'background': '#fff3cd', 'color': '#856404'});
            dot.css({'background': '#f0ad4e', 'animation': 'arw-blink 1s infinite'});
            badgeText.text('Safe Mode');
            this.tickTimeout = 1;
        } else if (state === 'restoring') {
            badge.css({'background': '#f8d7da', 'color': '#721c24'});
            dot.css({'background': '#d9534f', 'animation': 'arw-blink 0.5s infinite'});
            badgeText.text('Restoring');
        } else if (state === 'armed') {
            badge.css({'background': '#d4edda', 'color': '#155724'});
            dot.css({'background': '#28a745'});
            badgeText.text('Armed');
            this.tickTimeout = 5;
        } else {
            badgeText.text('Disabled');
            this.tickTimeout = 10;
        }

        const method = data.settings?.rollback_method || '';
        $('#arw-method').text(method === 'reboot' ? 'reboot' : method === 'reload' ? 'reload' : '');

        if (state === 'safe_mode' && safeMode.remaining_seconds > 0) {
            const remaining = Math.round(safeMode.remaining_seconds);
            const total = safeMode.timeout || 120;
            const pct = total > 0 ? (remaining / total) * 100 : 0;

            let mins = Math.floor(remaining / 60);
            let secs = remaining % 60;
            let display = mins > 0
                ? `${mins}<span style="font-size:13px;font-weight:400;color:#868e96">m</span> ${String(secs).padStart(2,'0')}<span style="font-size:13px;font-weight:400;color:#868e96">s</span>`
                : `${secs}<span style="font-size:13px;font-weight:400;color:#868e96">s</span>`;
            $('#arw-countdown').html(display);

            let barColor = pct > 50 ? '#5cb85c' : (pct > 20 ? '#f0ad4e' : '#d9534f');
            $('#arw-bar').css({'width': pct + '%', 'background': barColor});

            $('#arw-countdown-section').show();
        } else {
            $('#arw-countdown-section').hide();
        }

        $('#arw-btn-start').toggle(state === 'armed').prop('disabled', false);
        $('#arw-btn-confirm').toggle(state === 'safe_mode').prop('disabled', false);
        $('#arw-btn-revert').toggle(state === 'safe_mode').prop('disabled', false);
        $('#arw-btn-extend').toggle(state === 'safe_mode').prop('disabled', false);

        if (watchdog.enabled) {
            let wdText = 'Watchdog: monitoring';
            if (watchdog.fail_count > 0) {
                wdText = `Watchdog: ${watchdog.fail_count} failure(s)`;
            }
            $('#arw-watchdog-text').text(wdText);
            $('#arw-watchdog').show();
        } else {
            $('#arw-watchdog').hide();
        }
    }

    _renderError() {
        $('#arw-badge').css({'background': '#f8d7da', 'color': '#721c24'});
        $('#arw-badge-text').text('Error');
        $('#arw-countdown-section').hide();
        $('#arw-btn-start, #arw-btn-confirm, #arw-btn-revert, #arw-btn-extend').hide();
    }
}
