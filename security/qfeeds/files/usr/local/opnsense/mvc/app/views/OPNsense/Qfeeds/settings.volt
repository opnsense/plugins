 <!-- Navigation tabs -->
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings"><b>Settings</b></a></li>
    <li><a data-toggle="tab" href="#logs"><b>Logs</b></a></li>
    <li><a data-toggle="tab" href="#help"><b>Help/About</b></a></li>
</ul>

<div class="tab-content content-box">
    <!-- Settings tab -->
    <div id="settings" class="tab-pane fade in active">
        <div class="content-box">
            <div class="alert alert-info" style="margin-bottom: 24px;">
                <h4><i class="fa fa-info-circle"></i> How to use Q-Feeds</h4>
                <ul style="margin-bottom: 0;">
                    <li>This plugin fetches threat intelligence feeds from Q-Feeds and creates OPNsense aliases (e.g., <b>qfeeds_malware_ip</b>).</li>
                    <li><b>To block threats, you must create firewall rules using these aliases</b> (see <a href="#help">Help</a> tab for details).</li>
                    <li>Check the <b>Logs</b> tab for plugin activity and troubleshooting. Log file: <code>/var/log/qfeeds.log</code>.</li>
                    <li>Need help? Visit <a href="https://qfeeds.com" target="_blank">Q-Feeds</a> or <a href="mailto:support@qfeeds.com">contact support</a>.</li>
                </ul>
            </div>
            <form id="qfeeds-settings-form">
                <section class="page-content-main">
                    <header class="content-box-head">
                        <h2>Q-Feeds Settings</h2>
                    </header>
                    <div class="content-box-main">
                        <div id="settings-loading" class="loading-indicator">
                            <i class="fa fa-spinner fa-spin"></i> Loading settings...
                        </div>
                        <div id="settings-content" style="display: none;">
                            <div class="form-group">
                                <label class="checkbox-inline">
                                    <input type="checkbox" id="qfeeds.general.enabled" name="qfeeds[general][enabled]" value="1" checked> 
                                    Enable Q-Feeds
                                </label>
                            </div>
                            <div class="form-group">
                                <label for="qfeeds.general.api_token">API Token:</label>
                                <input type="password" class="form-control" id="qfeeds.general.api_token" name="qfeeds[general][api_token]" required>
                                <button type="button" class="btn btn-default" id="toggle-token">Show</button>
                                <span title="Your Q-Feeds API token. Get this from your Q-Feeds account.">&#9432;</span>
                                <div id="api-token-help" class="alert alert-warning" style="margin-top:10px; display:none;">
                                    <i class="fa fa-key"></i> No API token entered. <a href="https://qfeeds.com/start-trial-license/" target="_blank"><b>Get your free Q-Feeds API token here</b></a>.
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="qfeeds.general.interval">Update Interval (minutes, min 20):</label>
                                <input type="number" class="form-control" id="qfeeds.general.interval" name="qfeeds[general][interval]" min="20" max="1440" value="20" required>
                                <span title="How often to update the feeds. Minimum is 20 minutes.">&#9432;</span>
                            </div>
                            <div class="form-group">
                                <label>Feed Types:</label><br>
                                <label class="checkbox-inline">
                                    <input type="checkbox" id="qfeeds.feed_types.malware_ip" name="qfeeds[feed_types][malware_ip]" value="1"> 
                                    Malware IP
                                </label>
                                <span title="Select which types of threat intelligence to fetch.">&#9432;</span>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                                <button type="button" class="btn btn-default" id="manual-update">Manual Update</button>
                                <button type="button" class="btn btn-danger" id="qfeeds-uninstall">Uninstall/Cleanup</button>
                                <button type="button" class="btn btn-warning" id="qfeeds-alias-cleanup">Advanced Alias Cleanup</button>
                            </div>
                        </div>
                    </div>
                    <footer class="content-box-foot">
                        <div id="qfeeds-status">
                            <strong>Last Update:</strong> <span id="last_update_time">-</span>
                            <strong>Result:</strong> <span id="last_update_result">-</span>
                            <strong>IOC Count:</strong> <span id="last_ioc_count">-</span>
                            <strong>Plugin Version:</strong> <span id="plugin_version">-</span>
                        </div>
                        <div id="qfeeds-result"></div>
                    </footer>
                </section>
            </form>
        </div>
    </div>

    <!-- Logs tab -->
    <div id="logs" class="tab-pane fade">
        <div class="content-box">
            <section class="page-content-main">
                <header class="content-box-head">
                    <h2>Q-Feeds Logs</h2>
                </header>
                <div class="content-box-main">
                    <div class="alert alert-info" style="margin-bottom: 18px;">
                        <i class="fa fa-info-circle"></i> Below is the Q-Feeds plugin log. Use this for troubleshooting and support.
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <button type="button" class="btn btn-primary" id="refresh-qfeeds-log"><i class="fa fa-refresh"></i> Refresh Log</button>
                        <button type="button" class="btn btn-default" id="copy-qfeeds-log"><i class="fa fa-clipboard"></i> Copy Log</button>
                        <button type="button" class="btn btn-danger" id="flush-qfeeds-log"><i class="fa fa-trash"></i> Flush Log</button>
                    </div>
                    <div class="panel panel-default" style="border: 1px solid #337ab7;">
                        <div class="panel-heading" style="background: #f5faff; border-bottom: 1px solid #337ab7;">
                            <b><i class="fa fa-file-text-o"></i> /var/log/qfeeds.log</b>
                        </div>
                        <pre id="qfeeds-log-output" style="background: #f8fafd; color: #222; max-height: 400px; overflow: auto; margin: 0; padding: 12px; font-size: 13px; border: none;"></pre>
                    </div>
                    <div class="debug-container" style="margin-top: 18px; border: 1px solid #ccc; padding: 10px;">
                        <h4>Debug Information (API responses)</h4>
                        <div id="logs-loading" class="loading-indicator">
                            <i class="fa fa-spinner fa-spin"></i> Loading logs...
                        </div>
                        <pre id="debug-output" style="max-height: 400px; overflow: auto; display: none;"></pre>
                    </div>
                </div>
            </section>
        </div>
    </div>
    
    <!-- Help tab -->
    <div id="help" class="tab-pane fade">
        <div class="content-box">
            <section class="page-content-main">
                <header class="content-box-head">
                    <h2>Q-Feeds OPNsense Plugin Help</h2>
                </header>
                <div class="content-box-main">
                    <p><strong>What is Q-Feeds?</strong><br>
                    Q-Feeds is a threat intelligence service that provides up-to-date lists of malicious IPs, domains, and URLs (Indicators of Compromise, or IOCs). These feeds help you block known threats at the firewall level, improving your network security and reducing the risk of compromise.
                    <br><a href="https://qfeeds.com" target="_blank">https://qfeeds.com</a></p>
                    <p><strong>What does this plugin do?</strong><br>
                    This plugin fetches the latest IOCs from Q-Feeds and automatically updates OPNsense aliases. You can use these aliases in your firewall rules to block malicious traffic.</p>
                    <ul>
                      <li>Enter your API token from your Q-Feeds account.</li>
                      <li>Select the feed types you want (malware IPs).</li>
                      <li>Set the update interval (minimum 20 minutes).</li>
                      <li>Save settings. The plugin will fetch and update aliases automatically.</li>
                      <li>Use the created aliases (e.g., qfeeds_malware_ip) in your firewall rules to block threats.</li>
                      <li>Use the Manual Update button to fetch the latest feeds on demand.</li>
                      <li>Use the Uninstall/Cleanup button to remove all plugin data and aliases.</li>
                    </ul>
                    <p><strong>Troubleshooting</strong></p>
                    <ul>
                      <li>No aliases appear: Check your API token and feed type selection. Check the plugin log at /var/log/qfeeds.log for errors.</li>
                      <li>Updates fail: Ensure your OPNsense box has internet access and your API token is valid.</li>
                      <li>Aliases not updating: Check cron job status and permissions. Try a manual update.</li>
                      <li>Translations not working: Ensure .mo files are present in the LC_MESSAGES directory and your OPNsense language is supported.</li>
                    </ul>
                    <p><strong>Supported Languages:</strong> Dutch, German, French, Spanish, and English.</p>
                    <p>To add more languages, create a .po file in LC_MESSAGES, translate, and compile with msgfmt.</p>
                    <p><strong>Security</strong><br>
                    Only admin users can access and modify plugin settings. API tokens are masked in the GUI and never logged. All updates and errors are logged to /var/log/qfeeds.log.</p>
                    <p><strong>Support</strong><br>
                    For Q-Feeds service questions, visit <a href="https://qfeeds.com" target="_blank">https://qfeeds.com</a> or contact <a href="mailto:support@qfeeds.com">support@qfeeds.com</a>.<br>
                    For plugin issues, check the logs and use the troubleshooting section above.</p>
                </div>
            </section>
        </div>
    </div>
</div>

<style>
.loading-indicator {
    text-align: center;
    padding: 20px;
    font-size: 16px;
    color: #666;
}
.loading-indicator .fa-spinner {
    margin-right: 10px;
}
</style>

<script>
// Mask/reveal API token
const tokenInput = document.getElementById('qfeeds.general.api_token');
document.getElementById('toggle-token').addEventListener('click', function() {
    tokenInput.type = tokenInput.type === 'password' ? 'text' : 'password';
    this.textContent = tokenInput.type === 'password' ? 'Show' : 'Hide';
});

// Show API token help if empty
function updateApiTokenHelp() {
    const helpDiv = document.getElementById('api-token-help');
    if (tokenInput.value.trim() === '') {
        helpDiv.style.display = '';
    } else {
        helpDiv.style.display = 'none';
    }
}
tokenInput.addEventListener('input', updateApiTokenHelp);
document.addEventListener('DOMContentLoaded', updateApiTokenHelp);

// Initialize bootstrap tabs
$(document).ready(function() {
    $('#maintabs a').click(function (e) {
        e.preventDefault();
        $(this).tab('show');
    });
    
    if (window.location.hash) {
        $('#maintabs a[href="' + window.location.hash + '"]').tab('show');
    }
    
    // Load settings after DOM is fully ready and tabs are initialized
    console.log("DOM ready, loading settings...");
    loadSettings();
});

// Load current settings and status
async function loadSettings() {
    console.log("loadSettings() called");
    
    // Show loading indicators
    document.getElementById('settings-loading').style.display = 'block';
    document.getElementById('settings-content').style.display = 'none';
    document.getElementById('logs-loading').style.display = 'block';
    document.getElementById('debug-output').style.display = 'none';
    
    try {
        ajaxCall('/api/qfeeds/settings/get', {}, function(data, status) {
            console.log("Settings data received:", data);
            
            // Hide loading indicators
            document.getElementById('settings-loading').style.display = 'none';
            document.getElementById('settings-content').style.display = 'block';
            document.getElementById('logs-loading').style.display = 'none';
            document.getElementById('debug-output').style.display = 'block';
            
            // Add more detailed error checking
            if (data && data.error) {
                document.getElementById('debug-output').textContent = 
                    "Error: " + (data.message || data.errorMessage || "Unknown error");
                document.getElementById('qfeeds-result').innerText = 
                    "Error loading settings: " + (data.message || data.errorMessage || "Unknown error");
                return;
            }
            
            if (data && data.qfeeds) {
                // General settings
                if (data.qfeeds.general) {
                    // Enabled checkbox
                    if (data.qfeeds.general.enabled !== undefined) {
                        document.getElementById('qfeeds.general.enabled').checked = 
                            data.qfeeds.general.enabled === '1';
                    }
                    
                    // API Token
                    if (data.qfeeds.general.api_token) {
                        document.getElementById('qfeeds.general.api_token').value = 
                            data.qfeeds.general.api_token;
                        updateApiTokenHelp();
                    }
                    
                    // Interval
                    if (data.qfeeds.general.interval) {
                        document.getElementById('qfeeds.general.interval').value = 
                            data.qfeeds.general.interval;
                    }
                    
                    // Status fields
                    if (data.qfeeds.general.last_update_time) {
                        document.getElementById('last_update_time').textContent = 
                            data.qfeeds.general.last_update_time;
                    }
                    if (data.qfeeds.general.last_update_result) {
                        document.getElementById('last_update_result').textContent = 
                            data.qfeeds.general.last_update_result;
                    }
                    if (data.qfeeds.general.last_ioc_count) {
                        document.getElementById('last_ioc_count').textContent = 
                            data.qfeeds.general.last_ioc_count;
                    }
                    if (data.qfeeds.general.plugin_version) {
                        document.getElementById('plugin_version').textContent = 
                            data.qfeeds.general.plugin_version;
                    }
                }
                
                // Feed types
                if (data.qfeeds.feed_types) {
                    document.getElementById('qfeeds.feed_types.malware_ip').checked = 
                        data.qfeeds.feed_types.malware_ip === '1';
                }
            }
            
            // Debug output
            document.getElementById('debug-output').textContent = JSON.stringify(data, null, 2);
        });
    } catch (error) {
        console.error('Error loading settings:', error);
        document.getElementById('debug-output').textContent = 'Error: ' + error.message;
    }
}

// Refresh logs functionality
document.getElementById('refresh-qfeeds-log').addEventListener('click', function() {
    loadQfeedsLog();
});

// Copy to clipboard functionality
document.getElementById('copy-qfeeds-log').addEventListener('click', function() {
    const logText = document.getElementById('qfeeds-log-output').textContent;
    navigator.clipboard.writeText(logText).then(function() {
        alert('Log copied to clipboard!');
    }, function() {
        alert('Failed to copy log');
    });
});

// Flush logs functionality
document.getElementById('flush-qfeeds-log').addEventListener('click', function() {
    if (!confirm('Are you sure you want to flush (clear) the Q-Feeds log? This cannot be undone.')) return;
    fetch('/api/qfeeds/settings/flushLog', { 
        method: 'GET'
    })
    .then(resp => resp.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Flush log: Unexpected response:\n' + text);
            return;
        }
        alert(data.message || (data.result === 'ok' ? 'Log flushed.' : 'Failed to flush log.'));
        loadQfeedsLog();
    })
    .catch(err => {
        alert('Error flushing log: ' + err);
    });
});

// Save settings
document.getElementById('qfeeds-settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const resultDiv = document.getElementById('qfeeds-result');
    resultDiv.innerText = 'Saving...';
    
    try {
        // Build data from form - using a simpler approach
        let formObj = {
            qfeeds: {
                general: {
                    enabled: '0',
                    api_token: document.getElementById('qfeeds.general.api_token').value,
                    interval: document.getElementById('qfeeds.general.interval').value
                },
                feed_types: {
                    malware_ip: document.getElementById('qfeeds.feed_types.malware_ip').checked ? '1' : '0'
                }
            }
        };
        
        // Set enabled checkbox (can't use the array approach above as it depends on being checked)
        if (document.getElementById('qfeeds.general.enabled').checked) {
            formObj.qfeeds.general.enabled = '1';
        }
        
        // Debug the data we're sending
        console.log("Sending data:", formObj);
        document.getElementById('debug-output').textContent = "Sending: " + JSON.stringify(formObj, null, 2);
        
        // Send the data
        ajaxCall('/api/qfeeds/settings/set', formObj, function(respData, status) {
            // Debug output
            document.getElementById('debug-output').textContent = JSON.stringify(respData, null, 2);
            
            // Check for errors
            if (respData.error) {
                resultDiv.innerText = 'Error: ' + (respData.message || respData.errorMessage || 'Unknown error');
                return;
            }
            
            // Handle the response
            if (respData.result === 'saved') {
                resultDiv.innerText = 'Settings saved!';
                loadSettings();
            } else {
                // Handle validation errors
                if (respData.validationMessages) {
                    const errorMessages = [];
                    for (const field in respData.validationMessages) {
                        errorMessages.push(`${field}: ${respData.validationMessages[field]}`);
                    }
                    resultDiv.innerText = 'Validation errors:\n' + errorMessages.join('\n');
                } else {
                    resultDiv.innerText = 'Error: ' + (respData.message || respData.error || 'Unknown error');
                }
            }
        });
    } catch (error) {
        resultDiv.innerText = 'Error: ' + error.message;
        document.getElementById('debug-output').textContent = 'Error: ' + error.message;
        console.error('Error saving settings:', error);
    }
});

// Manual update
document.getElementById('manual-update').addEventListener('click', function() {
    const resultDiv = document.getElementById('qfeeds-result');
    resultDiv.innerText = 'Updating...';
    
    try {
        ajaxCall('/api/qfeeds/settings/update', {}, function(data, status) {
            // Debug output
            document.getElementById('debug-output').textContent = JSON.stringify(data, null, 2);
            
            // Check for errors
            if (data.error) {
                resultDiv.innerText = 'Error: ' + (data.message || data.errorMessage || 'Unknown error');
                return;
            }
            
            if (data.result) {
                if (typeof data.result === 'object') {
                    resultDiv.innerText = Object.entries(data.result)
                        .map(entry => `${entry[0]}: ${entry[1]}`)
                        .join('\n');
                } else {
                    resultDiv.innerText = data.result;
                }
            } else {
                resultDiv.innerText = data.message || 'Update failed.';
            }
            
            // Update status display
            if (data.last_update_time) {
                document.getElementById('last_update_time').textContent = data.last_update_time;
            }
            if (data.last_update_result) {
                document.getElementById('last_update_result').textContent = data.last_update_result;
            }
            if (data.last_ioc_count) {
                document.getElementById('last_ioc_count').textContent = data.last_ioc_count;
            }
            
            // Reload all settings just to be sure
            loadSettings();
        });
    } catch (error) {
        resultDiv.innerText = 'Error: ' + error.message;
        document.getElementById('debug-output').textContent = 'Error: ' + error.message;
        console.error('Error during update:', error);
    }
});

// Uninstall/cleanup
document.getElementById('qfeeds-uninstall').addEventListener('click', function() {
    if (!confirm('Are you sure you want to uninstall and remove all Q-Feeds data and aliases?')) return;
    
    const resultDiv = document.getElementById('qfeeds-result');
    resultDiv.innerText = 'Cleaning up...';
    
    try {
        ajaxCall('/api/qfeeds/settings/cleanup', {}, function(data, status) {
            document.getElementById('debug-output').textContent = JSON.stringify(data, null, 2);
            if (data.error) {
                resultDiv.innerText = 'Error: ' + (data.message || data.errorMessage || 'Unknown error');
                return;
            }
            resultDiv.innerText = data.result || 'Cleanup complete.';
            loadSettings();
        });
    } catch (error) {
        resultDiv.innerText = 'Error: ' + error.message;
        document.getElementById('debug-output').textContent = 'Error: ' + error.message;
        console.error('Error during cleanup:', error);
    }
});

// Advanced alias cleanup
document.getElementById('qfeeds-alias-cleanup').addEventListener('click', function() {
    if (!confirm('Are you sure you want to run advanced alias cleanup? This will remove all duplicate or broken Q-Feeds aliases and flush pf tables.')) return;
    const resultDiv = document.getElementById('qfeeds-result');
    resultDiv.innerText = 'Running advanced alias cleanup...';
    try {
        ajaxCall('/api/qfeeds/settings/cleanupAliases', {}, function(data, status) {
            document.getElementById('debug-output').textContent = JSON.stringify(data, null, 2);
            if (data.error) {
                resultDiv.innerText = 'Error: ' + (data.message || data.errorMessage || 'Unknown error');
                return;
            }
            resultDiv.innerText = data.message || 'Advanced alias cleanup complete.';
            loadSettings();
        });
    } catch (error) {
        resultDiv.innerText = 'Error: ' + error.message;
        document.getElementById('debug-output').textContent = 'Error: ' + error.message;
        console.error('Error during advanced alias cleanup:', error);
    }
});

// Add Q-Feeds log loading logic
function loadQfeedsLog() {
    const logOutput = document.getElementById('qfeeds-log-output');
    logOutput.textContent = 'Loading log...';
    fetch('/api/qfeeds/settings/qfeedsLog')
        .then(resp => resp.json())
        .then(data => {
            if (data && data.log) {
                logOutput.textContent = data.log;
            } else {
                logOutput.textContent = 'No log data found.';
            }
        })
        .catch(err => {
            logOutput.textContent = 'Error loading log: ' + err;
        });
}

// Load log on tab show
$(document).ready(function() {
    $('a[data-toggle="tab"][href="#logs"]').on('shown.bs.tab', function() {
        loadQfeedsLog();
    });
    // Optionally, load log immediately if logs tab is default
    if ($('#logs').hasClass('in active')) {
        loadQfeedsLog();
    }
});
</script> 