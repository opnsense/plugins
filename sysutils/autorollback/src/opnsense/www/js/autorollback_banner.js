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
 * OPNsense Auto Rollback - Persistent Global Banner
 *
 * This script injects a countdown banner at the top of EVERY page when
 * safe mode is active. It polls the status API and shows/hides the banner
 * dynamically. Includes confirm/revert buttons for immediate action
 * without navigating to the plugin settings page.
 *
 * This file should be included in the base layout template or via a
 * system hook that adds JavaScript to every page.
 *
 * Design: Non-intrusive but unmissable. Fixed position below the navbar,
 * full-width, with a pulsing amber background during safe mode.
 */
(function() {
    'use strict';

    // Don't double-initialize
    if (window._autorollbackBannerInit) return;
    window._autorollbackBannerInit = true;

    const POLL_INTERVAL_IDLE = 10000;    // 10s when not in safe mode
    const POLL_INTERVAL_ACTIVE = 1000;   // 1s during safe mode
    const BANNER_ID = 'autorollback-global-banner';

    let pollTimer = null;
    let currentPollInterval = POLL_INTERVAL_IDLE;
    let bannerElement = null;

    function createBanner() {
        if (document.getElementById(BANNER_ID)) return;

        const banner = document.createElement('div');
        banner.id = BANNER_ID;
        banner.innerHTML = `
            <style>
                #${BANNER_ID} {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 99999;
                    display: none;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    transition: transform 0.3s ease;
                    transform: translateY(-100%);
                }
                #${BANNER_ID}.visible {
                    display: block;
                    transform: translateY(0);
                }
                #${BANNER_ID} .arb-inner {
                    background: linear-gradient(135deg, #f0ad4e 0%, #ec971f 100%);
                    color: #fff;
                    padding: 10px 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 16px;
                    flex-wrap: wrap;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.2);
                    min-height: 44px;
                }
                #${BANNER_ID}.danger .arb-inner {
                    background: linear-gradient(135deg, #d9534f 0%, #c9302c 100%);
                }
                #${BANNER_ID} .arb-icon {
                    font-size: 18px;
                    animation: arb-pulse 1.5s ease-in-out infinite;
                }
                @keyframes arb-pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
                #${BANNER_ID} .arb-text {
                    font-size: 14px;
                    font-weight: 600;
                }
                #${BANNER_ID} .arb-countdown {
                    font-size: 20px;
                    font-weight: 700;
                    font-variant-numeric: tabular-nums;
                    min-width: 60px;
                    text-align: center;
                }
                #${BANNER_ID} .arb-btn {
                    padding: 5px 16px;
                    border: 2px solid rgba(255,255,255,0.8);
                    border-radius: 4px;
                    background: transparent;
                    color: #fff;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                #${BANNER_ID} .arb-btn:hover {
                    background: rgba(255,255,255,0.2);
                    border-color: #fff;
                }
                #${BANNER_ID} .arb-btn-confirm {
                    background: rgba(255,255,255,0.25);
                    border-color: #fff;
                }
                #${BANNER_ID} .arb-btn-confirm:hover {
                    background: rgba(255,255,255,0.4);
                }
                #${BANNER_ID} .arb-progress {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    height: 3px;
                    background: rgba(255,255,255,0.5);
                    transition: width 1s linear;
                    border-radius: 0 2px 0 0;
                }
            </style>
            <div class="arb-inner" style="position: relative;">
                <span class="arb-icon">&#9888;</span>
                <span class="arb-text">Safe Mode Active</span>
                <span class="arb-countdown" id="arb-countdown">--</span>
                <button class="arb-btn arb-btn-confirm" id="arb-confirm" title="Accept the current configuration">
                    &#10003; CONFIRM
                </button>
                <button class="arb-btn" id="arb-revert" title="Revert to the previous configuration">
                    &#8634; REVERT
                </button>
                <button class="arb-btn" id="arb-extend" title="Add 60 seconds to the timer">
                    +60s
                </button>
                <div class="arb-progress" id="arb-progress"></div>
            </div>
        `;

        document.body.appendChild(banner);
        bannerElement = banner;

        // Button event listeners
        document.getElementById('arb-confirm').addEventListener('click', function() {
            this.disabled = true;
            this.textContent = 'Confirming...';
            apiPost('confirm', function() {
                pollStatus();
            });
        });

        document.getElementById('arb-revert').addEventListener('click', function() {
            if (confirm('Revert to the previous configuration?\nThe system may reboot.')) {
                this.disabled = true;
                this.textContent = 'Reverting...';
                apiPost('cancel', function() {
                    pollStatus();
                });
            }
        });

        document.getElementById('arb-extend').addEventListener('click', function() {
            apiPost('extend', function() {
                pollStatus();
            }, {seconds: 60});
        });
    }

    function showBanner(remaining, total) {
        if (!bannerElement) createBanner();

        bannerElement.classList.add('visible');

        // Danger mode when under 20% time remaining
        let pct = total > 0 ? remaining / total : 0;
        if (pct <= 0.2) {
            bannerElement.classList.add('danger');
        } else {
            bannerElement.classList.remove('danger');
        }

        // Update countdown
        let mins = Math.floor(remaining / 60);
        let secs = remaining % 60;
        let display = mins > 0
            ? mins + 'm ' + String(secs).padStart(2, '0') + 's'
            : secs + 's';
        document.getElementById('arb-countdown').textContent = display;

        // Progress bar
        document.getElementById('arb-progress').style.width = (pct * 100) + '%';

        // Re-enable buttons
        let confirmBtn = document.getElementById('arb-confirm');
        let revertBtn = document.getElementById('arb-revert');
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '&#10003; CONFIRM';
        revertBtn.disabled = false;
        revertBtn.innerHTML = '&#8634; REVERT';

        // Push body content down to avoid overlap
        document.body.style.paddingTop = bannerElement.offsetHeight + 'px';
    }

    function hideBanner() {
        if (bannerElement) {
            bannerElement.classList.remove('visible');
            document.body.style.paddingTop = '';
        }
    }

    function apiPost(action, callback, data) {
        let xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/autorollback/service/' + action, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Include CSRF token if available (OPNsense uses jQuery for this)
        let csrfToken = '';
        if (typeof $ !== 'undefined' && $.ajaxSettings && $.ajaxSettings.headers) {
            csrfToken = $.ajaxSettings.headers['X-CSRFToken'] || '';
        }
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRFToken', csrfToken);
        }

        xhr.onload = function() {
            if (callback) callback();
        };

        let body = '';
        if (data) {
            body = Object.keys(data).map(function(k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
            }).join('&');
        }
        xhr.send(body);
    }

    function pollStatus() {
        let xhr = new XMLHttpRequest();
        xhr.open('GET', '/api/autorollback/service/status', true);
        xhr.onload = function() {
            try {
                let data = JSON.parse(xhr.responseText);
                let state = data.system_state || 'disabled';
                let safeMode = data.safe_mode || {};

                if (state === 'safe_mode' && safeMode.remaining_seconds > 0) {
                    showBanner(safeMode.remaining_seconds, safeMode.timeout || 120);
                    setPolling(POLL_INTERVAL_ACTIVE);
                } else {
                    hideBanner();
                    setPolling(POLL_INTERVAL_IDLE);
                }
            } catch (e) {
                // Silently ignore parse errors — API might be temporarily unavailable
            }
        };
        xhr.onerror = function() {
            // API unreachable — could be mid-rollback, keep polling
        };
        xhr.send();
    }

    function setPolling(interval) {
        if (interval === currentPollInterval && pollTimer) return;
        currentPollInterval = interval;
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollStatus, interval);
    }

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            pollStatus();
            setPolling(POLL_INTERVAL_IDLE);
        });
    } else {
        pollStatus();
        setPolling(POLL_INTERVAL_IDLE);
    }
})();
