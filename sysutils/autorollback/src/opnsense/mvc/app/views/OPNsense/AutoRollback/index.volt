{#
    OPNsense Auto Rollback - Settings & Safe Mode Control Page

    This page has two sections:
    1. Safe Mode control panel (top) - Start/Confirm/Cancel with live countdown
    2. Settings form (bottom) - Plugin configuration
#}

<style>
    /* Safe Mode Control Panel */
    .safe-mode-panel {
        border-radius: 6px;
        padding: 20px 24px;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    .safe-mode-idle {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
    }
    .safe-mode-active {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border: 2px solid #f0ad4e;
        box-shadow: 0 2px 12px rgba(240, 173, 78, 0.25);
    }
    .safe-mode-restoring {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        border: 2px solid #d9534f;
        animation: pulse-border 1.5s ease-in-out infinite;
    }
    @keyframes pulse-border {
        0%, 100% { box-shadow: 0 2px 12px rgba(217, 83, 79, 0.2); }
        50% { box-shadow: 0 2px 20px rgba(217, 83, 79, 0.5); }
    }

    .safe-mode-panel h3 {
        margin: 0 0 4px 0;
        font-size: 18px;
        font-weight: 600;
    }
    .safe-mode-panel .subtitle {
        color: #6c757d;
        font-size: 13px;
        margin-bottom: 16px;
    }

    /* Countdown Display */
    .countdown-display {
        font-size: 48px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: -1px;
        line-height: 1;
        margin: 12px 0;
    }
    .countdown-display .unit {
        font-size: 16px;
        font-weight: 400;
        color: #6c757d;
        margin-left: 2px;
    }

    /* Progress bar */
    .countdown-bar {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        margin: 12px 0 16px;
        overflow: hidden;
    }
    .countdown-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 1s linear, background-color 0.5s ease;
    }
    .countdown-bar-fill.safe { background: #5cb85c; }
    .countdown-bar-fill.warning { background: #f0ad4e; }
    .countdown-bar-fill.danger { background: #d9534f; }

    /* Action buttons */
    .safe-mode-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    .safe-mode-actions .btn {
        min-width: 130px;
        font-weight: 500;
    }

    /* Status badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-badge.idle { background: #e9ecef; color: #495057; }
    .status-badge.armed { background: #d4edda; color: #155724; }
    .status-badge.active { background: #fff3cd; color: #856404; }
    .status-badge.restoring { background: #f8d7da; color: #721c24; }
    .status-badge .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .status-badge.idle .dot { background: #6c757d; }
    .status-badge.armed .dot { background: #28a745; }
    .status-badge.active .dot { background: #f0ad4e; animation: blink 1s infinite; }
    .status-badge.restoring .dot { background: #d9534f; animation: blink 0.5s infinite; }
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }

    /* CLI hint */
    .cli-hint {
        margin-top: 12px;
        padding: 8px 12px;
        background: #2d2d2d;
        color: #a8dba8;
        border-radius: 4px;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 12px;
    }
    .cli-hint code {
        color: #e8e8e8;
        background: none;
    }
</style>

<script>
    // Auto Rollback - Safe Mode Controller
    $(document).ready(function() {
        let pollInterval = null;
        let countdownInterval = null;
        let currentState = {};

        // --- API Calls ---
        function apiCall(action, data, callback) {
            $.ajax({
                url: '/api/autorollback/service/' + action,
                type: (action === 'status') ? 'GET' : 'POST',
                dataType: 'json',
                data: data || {},
                success: function(result) {
                    if (callback) callback(result);
                },
                error: function(xhr) {
                    console.error('Auto-rollback API error:', action, xhr);
                    if (callback) callback({status: 'error', message: 'API request failed'});
                }
            });
        }

        // --- UI Update ---
        function updateUI(status) {
            currentState = status;
            let state = status.system_state || 'disabled';
            let safeMode = status.safe_mode || {};
            let panel = $('#safe-mode-panel');

            // Update CSS class
            panel.removeClass('safe-mode-idle safe-mode-active safe-mode-restoring');

            if (state === 'safe_mode') {
                panel.addClass('safe-mode-active');
                $('#sm-status-badge').attr('class', 'status-badge active')
                    .html('<span class="dot"></span> Safe Mode Active');
                $('#sm-idle-content').hide();
                $('#sm-active-content').show();
                $('#sm-restoring-content').hide();

                // Update countdown
                updateCountdown(safeMode.remaining_seconds || 0, safeMode.timeout || 120);

                // CLI hint
                $('#sm-cli-hint').html(
                    '<i class="fa fa-terminal"></i> SSH: <code>configctl autorollback safemode.confirm</code> to confirm | ' +
                    '<code>configctl autorollback safemode.cancel</code> to revert'
                );

            } else if (state === 'restoring') {
                panel.addClass('safe-mode-restoring');
                $('#sm-status-badge').attr('class', 'status-badge restoring')
                    .html('<span class="dot"></span> Restoring');
                $('#sm-idle-content').hide();
                $('#sm-active-content').hide();
                $('#sm-restoring-content').show();

            } else {
                panel.addClass('safe-mode-idle');
                let badgeClass = (state === 'armed') ? 'armed' : 'idle';
                let badgeText = (state === 'armed') ? 'Armed' : 'Disabled';
                $('#sm-status-badge').attr('class', 'status-badge ' + badgeClass)
                    .html('<span class="dot"></span> ' + badgeText);
                $('#sm-idle-content').show();
                $('#sm-active-content').hide();
                $('#sm-restoring-content').hide();

                // Enable/disable start button based on plugin enabled state
                $('#btn-start-safemode').prop('disabled', state === 'disabled');
            }
        }

        function updateCountdown(remaining, total) {
            if (remaining <= 0) remaining = 0;
            let minutes = Math.floor(remaining / 60);
            let seconds = remaining % 60;

            let display = '';
            if (minutes > 0) {
                display = minutes + '<span class="unit">m</span> ' +
                          String(seconds).padStart(2, '0') + '<span class="unit">s</span>';
            } else {
                display = seconds + '<span class="unit">s</span>';
            }
            $('#sm-countdown').html(display);

            // Progress bar
            let pct = total > 0 ? (remaining / total) * 100 : 0;
            let barClass = pct > 50 ? 'safe' : (pct > 20 ? 'warning' : 'danger');
            $('#sm-countdown-bar-fill')
                .css('width', pct + '%')
                .attr('class', 'countdown-bar-fill ' + barClass);
        }

        // --- Polling ---
        function startPolling(interval) {
            stopPolling();
            pollInterval = setInterval(function() {
                apiCall('status', null, updateUI);
            }, interval || 3000);
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        // --- Button Handlers ---
        $('#btn-start-safemode').on('click', function() {
            let btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Starting...');

            apiCall('start', {}, function(result) {
                if (result.status === 'ok') {
                    startPolling(1000);  // Fast polling during safe mode
                } else {
                    alert('Failed to start safe mode: ' + (result.message || 'Unknown error'));
                    btn.prop('disabled', false).html('<i class="fa fa-shield"></i> Enter Safe Mode');
                }
                // Status poll will update UI
                apiCall('status', null, updateUI);
            });
        });

        $('#btn-confirm').on('click', function() {
            let btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Confirming...');

            apiCall('confirm', {}, function(result) {
                startPolling(3000);  // Back to normal polling
                apiCall('status', null, updateUI);
            });
        });

        $('#btn-revert').on('click', function() {
            if (!confirm('Are you sure you want to revert to the previous configuration?\n\n' +
                         'Rollback method: ' + (currentState.safe_mode?.rollback_method || 'reboot') + '\n' +
                         'The system may reboot.')) {
                return;
            }
            let btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Reverting...');

            apiCall('cancel', {}, function(result) {
                apiCall('status', null, updateUI);
            });
        });

        $('#btn-extend').on('click', function() {
            apiCall('extend', {seconds: 60}, function(result) {
                apiCall('status', null, updateUI);
            });
        });

        // --- Settings Form ---
        // Map settings API using standard OPNsense pattern
        mapDataToFormUI({'frm_GeneralSettings': '/api/autorollback/settings/get'}).done(function() {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $('#btn-save-settings').on('click', function() {
            saveFormToEndpoint('/api/autorollback/settings/set', 'frm_GeneralSettings', function() {
                // Refresh cron after settings change
                ajaxCall('/api/autorollback/service/status', {}, function(data) {
                    updateUI(data);
                });
            });
        });

        // --- Initial load ---
        apiCall('status', null, function(status) {
            updateUI(status);
            if (status.system_state === 'safe_mode') {
                startPolling(1000);
            } else {
                startPolling(5000);
            }
        });
    });
</script>

<!-- Safe Mode Control Panel -->
<div id="safe-mode-panel" class="safe-mode-panel safe-mode-idle">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h3><i class="fa fa-undo"></i> Safe Mode</h3>
            <div class="subtitle">Make configuration changes safely with automatic rollback protection</div>
        </div>
        <div id="sm-status-badge" class="status-badge idle">
            <span class="dot"></span> Disabled
        </div>
    </div>

    <!-- Idle state -->
    <div id="sm-idle-content">
        <p style="margin: 8px 0 16px; color: #495057;">
            Enter safe mode to snapshot your current configuration before making changes.
            If you don't confirm within the timeout, the system will automatically revert.
        </p>
        <div class="safe-mode-actions">
            <button id="btn-start-safemode" class="btn btn-primary" disabled>
                <i class="fa fa-shield"></i> Enter Safe Mode
            </button>
        </div>
        <div class="cli-hint" style="margin-top: 16px;">
            <i class="fa fa-terminal"></i> SSH: <code>configctl autorollback safemode.start</code>
        </div>
    </div>

    <!-- Active state (countdown) -->
    <div id="sm-active-content" style="display:none;">
        <div class="countdown-display" id="sm-countdown">
            120<span class="unit">s</span>
        </div>
        <div class="countdown-bar">
            <div id="sm-countdown-bar-fill" class="countdown-bar-fill safe" style="width: 100%;"></div>
        </div>
        <div class="safe-mode-actions">
            <button id="btn-confirm" class="btn btn-success btn-lg">
                <i class="fa fa-check"></i> Confirm Changes
            </button>
            <button id="btn-revert" class="btn btn-danger">
                <i class="fa fa-undo"></i> Revert Now
            </button>
            <button id="btn-extend" class="btn btn-default">
                <i class="fa fa-clock-o"></i> +60s
            </button>
        </div>
        <div id="sm-cli-hint" class="cli-hint" style="margin-top: 12px;"></div>
    </div>

    <!-- Restoring state -->
    <div id="sm-restoring-content" style="display:none;">
        <p style="color: #721c24; font-weight: 600; font-size: 16px;">
            <i class="fa fa-spinner fa-spin"></i> Rollback in progress...
        </p>
        <p style="color: #721c24;">
            The system is reverting to the previous configuration. This page may become temporarily unavailable.
        </p>
    </div>
</div>

<!-- Settings Tab -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="settings" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_GeneralSettings']) }}

        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="btn-save-settings" type="button">
                <b>{{ lang._('Save') }}</b> <i id="btn-save-settings_progress" class=""></i>
            </button>
        </div>
    </div>
</div>
