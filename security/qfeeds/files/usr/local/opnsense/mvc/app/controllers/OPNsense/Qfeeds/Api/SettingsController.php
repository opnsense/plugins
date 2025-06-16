<?php
namespace OPNsense\Qfeeds\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Qfeeds\Qfeeds;
use OPNsense\Firewall\Alias as FirewallAliasModel;
use OPNsense\Core\Backend;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'qfeeds';
    protected static $internalModelClass = 'OPNsense\\Qfeeds\\Qfeeds';

    /**
     * Basic debug function
     */
    private function debug($message)
    {
        // Make sure we have a path to write to
        $logDir = dirname('/var/log/qfeeds.log');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // More detailed logging
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT);
        }
        
        file_put_contents('/var/log/qfeeds.log', 
            '[' . date('c') . '] ' . $message . "\n", 
            FILE_APPEND);
            
        // Also log to system log for critical errors
        if (strpos($message, 'ERROR:') !== false) {
            syslog(LOG_ERR, "QFeeds: $message");
        }
    }

    /**
     * Verify authentication and ensure user has access
     * @return boolean
     */
    private function checkAuth()
    {
        $this->debug("Checking authentication");
        
        // If not authenticated, log and return false
        if (!$this->getUserName()) {
            $this->debug("API call not authenticated - no user found");
            return false;
        }
        
        $this->debug("Authenticated as user: " . $this->getUserName());
        return true;
    }

    /**
     * Retrieve settings or return defaults
     * @return array
     */
    public function getAction()
    {
        try {
            $this->debug("getAction called");
            
            // Check authentication
            if (!$this->checkAuth()) {
                $this->debug("ERROR: Authentication failed in getAction");
                return array("error" => "Authentication required");
            }
            
            $result = array("qfeeds" => $this->getModel()->getNodes());
            $this->debug("getAction returning: " . json_encode($result));
            return $result;
        } catch (\Exception $e) {
            $this->debug("ERROR: Exception in getAction: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return array(
                "error" => true,
                "message" => $e->getMessage(),
                "errorMessage" => "Unexpected error, check log for details"
            );
        }
    }

    /**
     * Get model instance
     * @return \OPNsense\Qfeeds\Qfeeds
     */
    protected function getModel()
    {
        try {
            $this->debug("Getting model instance (using parent::getModel())");
            $model = parent::getModel();
            $this->debug("Model class: " . get_class($model));
            
            // Verify model is properly initialized by checking if top-level nodes are null
            // BaseModel uses magic __get, so direct property access should work.
            // If these are null, it means the model didn't load its structure from XML or config.
            if ($model->general === null || $model->feed_types === null) {
                $this->debug("ERROR: Model structure not properly initialized (general or feed_types node is null).");
                $this->debug("Current model nodes accessible: " . json_encode($model->getNodes()));
            } else {
                $this->debug("Model structure looks valid (general and feed_types nodes are not null).");
            }
            
            return $model;
        } catch (\Exception $e) {
            $this->debug("ERROR: Exception in getModel: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update settings
     * @return array
     */
    public function setAction()
    {
        try {
            $this->debug("setAction called");
            
            // Check authentication
            if (!$this->checkAuth()) {
                $this->debug("ERROR: Authentication failed in setAction");
                return array("result" => "failed", "error" => "Authentication required");
            }
            
            if ($this->request->isPost()) {
                $this->debug("POST data: " . json_encode($this->request->getPost()));
                
                // Get model instance using the class method, which has our checks
                $mdl = $this->getModel(); 
                $this->debug("Using model instance from getModel() for setAction. Class: " . get_class($mdl));
                
                $raw_post_data = $this->request->getPost("qfeeds"); 
                $this->debug("Full RAW POST data for qfeeds: " . json_encode($raw_post_data));

                if ($raw_post_data === null) {
                    $this->debug("Error: RAW POST data for 'qfeeds' is null. Cannot proceed.");
                    return array("result" => "failed", "errorMessage" => "Internal error: Invalid request data.");
                }

                // Construct a clean data array matching the XML structure STRICTLY
                $data_to_pass_to_setnodes = [
                    'general' => [],
                    'feed_types' => []
                ];

                // Populate 'general' fields if they exist in raw POST data
                $general_fields = ['enabled', 'api_token', 'interval', 'last_update_time', 'last_update_result', 'last_ioc_count', 'plugin_version'];
                if (isset($raw_post_data['general']) && is_array($raw_post_data['general'])) {
                    foreach ($general_fields as $field) {
                        if (array_key_exists($field, $raw_post_data['general'])) {
                            $data_to_pass_to_setnodes['general'][$field] = $raw_post_data['general'][$field];
                        }
                    }
                }

                // Populate 'feed_types' fields if they exist in raw POST data
                $feed_type_fields = ['malware_ip'];
                if (isset($raw_post_data['feed_types']) && is_array($raw_post_data['feed_types'])) {
                    foreach ($feed_type_fields as $field) {
                        if (array_key_exists($field, $raw_post_data['feed_types'])) {
                            $data_to_pass_to_setnodes['feed_types'][$field] = $raw_post_data['feed_types'][$field];
                        }
                    }
                }
                
                $this->debug("CLEANED data to pass to setNodes: " . json_encode($data_to_pass_to_setnodes));

                // Restore controller-level validation (important now that XML is full again)
                if (!isset($data_to_pass_to_setnodes['general']['api_token']) || empty($data_to_pass_to_setnodes['general']['api_token'])) {
                    $this->debug("API token missing or empty in cleaned data.");
                    return array("result" => "failed", "validationMessages" => ["general.api_token" => "API token is required"]);
                }
                if (!isset($data_to_pass_to_setnodes['general']['interval']) || 
                    !is_numeric($data_to_pass_to_setnodes['general']['interval']) || 
                    (int)$data_to_pass_to_setnodes['general']['interval'] < 20) {
                    $this->debug("Interval missing or invalid in cleaned data.");
                    return array("result" => "failed", "validationMessages" => ["general.interval" => "Interval must be at least 20 minutes"]);
                }

                // Set nodes in model
                $validations = $mdl->setNodes($data_to_pass_to_setnodes);
                $this->debug("Result of setNodes (before check): " . json_encode($validations));

                // If setNodes returned an array and it has validation messages, then fail.
                if (is_array($validations) && count($validations) > 0) {
                    $this->debug("Validation errors from setNodes: " . json_encode($validations));
                    return array("result" => "failed", "validationMessages" => $validations);
                } 

                // Save if validated correctly
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                
                // Update cron job based on interval
                $interval = $mdl->general->interval->__toString();
                $this->updateCronJob($interval);
                
                $this->debug("Save successful");
                return array("result" => "saved");
            }
            
            $this->debug("Not a POST request");
            return array("result" => "failed", "error" => "Not a POST request");
        } catch (\Exception $e) {
            $this->debug("ERROR: Exception in setAction: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return array(
                "error" => true,
                "message" => $e->getMessage(),
                "errorMessage" => "Unexpected error, check log for details"
            );
        }
    }

    /**
     * Update feeds and aliases
     * @return array
     */
    public function updateAction()
    {
        try {
            $this->debug("updateAction called - manual update triggered");
            
            $mdlQfeeds = $this->getModel();
            $api_token = $mdlQfeeds->general->api_token->__toString();
            $results = [];
            $total_iocs = 0;
            $log_msgs = [];
            $success = true;
            
            // Validate required settings
            if (empty($api_token)) {
                $this->debug("API token not configured, using test data");
                $log_msgs[] = "API token not configured, using test data";
                // We'll continue with test data, so not returning error
            }
            
            // Check which feed types are enabled
            $enabled_feeds = [];
            if ((string)$mdlQfeeds->feed_types->malware_ip === '1') {
                $enabled_feeds[] = 'malware_ip';
            }
            
            $this->debug("Enabled feeds: " . implode(", ", $enabled_feeds));
            
            if (count($enabled_feeds) == 0) {
                return ["result" => "error", "message" => "No feed types selected. Please enable at least one feed type."];
            }
            
            // Fetch each enabled feed
            foreach ($enabled_feeds as $feed_type) {
                $ioc_count = 0;
                $result = $this->fetchAndUpdateAlias($feed_type, $api_token, $log_msgs, $ioc_count, $success);
                $results[$feed_type] = $result;
                $total_iocs += $ioc_count;
            }
            
            // Update model status
            $mdlQfeeds->general->last_update_time = date('c');
            $mdlQfeeds->general->last_update_result = $success ? 'success' : 'failure';
            $mdlQfeeds->general->last_ioc_count = $total_iocs;
            
            // Save to config
            $mdlQfeeds->serializeToConfig();
            Config::getInstance()->save();
            
            // Log update details
            $this->logUpdate($log_msgs);

            // Run advanced alias cleanup after update to ensure no duplicates remain
            $cleanupResult = $this->cleanupAliasesAction();
            $this->debug("Post-update cleanupAliasesAction result: " . json_encode($cleanupResult));

            // Force firewall to apply changes
            exec("/usr/local/etc/rc.filter_configure");
            $this->debug("Applied firewall changes");
            
            return [
                "result" => $results, 
                "last_update_time" => $mdlQfeeds->general->last_update_time->__toString(), 
                "last_update_result" => $mdlQfeeds->general->last_update_result->__toString(), 
                "last_ioc_count" => $mdlQfeeds->general->last_ioc_count->__toString(),
                "log_messages" => $log_msgs
            ];
        } catch (\Exception $e) {
            $this->debug("ERROR in updateAction: " . $e->getMessage());
            return [
                "result" => "error", 
                "message" => "Exception during update: " . $e->getMessage()
            ];
        }
    }

    private function fetchAndUpdateAlias($feed_type, $api_token, &$log_msgs, &$ioc_count, &$success)
    {
        // Ensure aliases directory exists
        $aliases_dir = "/usr/local/opnsense/scripts/qfeeds/aliases";
        if (!is_dir($aliases_dir)) {
            @mkdir($aliases_dir, 0755, true);
            $this->debug("Created aliases directory: $aliases_dir");
        }
        
        // Set testMode to true initially, will be changed if API call works
        $testMode = true;
        $ioc_list = false;
        
        // Only try real API if token is provided
        if (!empty($api_token)) {
            $this->debug("Fetching real data from API for $feed_type with token: " . substr($api_token, 0, 3) . "***");
            
            // Configure API URL - use HTTPS instead of HTTP to avoid redirect issues
            $url = "https://api.qfeeds.com/api.php?feed_type=" . urlencode($feed_type) . "&api_token=" . urlencode($api_token);
            $this->debug("API URL: " . $url);
            
            $retries = 3;
            
            // Setup proper context with timeout and user agent
            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'user_agent' => 'QFeeds-OPNsense-Plugin/1.0',
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $connectionError = false;
            for ($i = 0; $i < $retries; $i++) {
                $this->debug("API attempt " . ($i+1) . " for $feed_type");
                
                // Try curl first if available
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'QFeeds-OPNsense-Plugin/1.0');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
                    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Allow up to 5 redirects
                    
                    $ioc_list = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    $this->debug("Curl HTTP code: " . $http_code);
                    if (!empty($curl_error)) {
                        $this->debug("Curl error: " . $curl_error);
                        $connectionError = true;
                    }
                    
                    if ($http_code >= 200 && $http_code < 300 && !empty($ioc_list)) {
                        $this->debug("Successfully fetched data using curl for $feed_type, size: " . strlen($ioc_list) . " bytes");
                        $testMode = false; // Got real data, don't use test data
                        break;
                    }
                    
                    if ($http_code == 401 || $http_code == 403) {
                        $msg = "Authentication failed with API. Check your API token.";
                        $log_msgs[] = $msg;
                        $this->debug("ERROR: " . $msg);
                        $success = false;
                        $connectionError = true;
                        break;
                    }
                    
                    $ioc_list = false;
                } else {
                    // Fallback to file_get_contents if curl not available
                    $ioc_list = @file_get_contents($url, false, $context);
                    
                    // Check HTTP response code using $http_response_header
                    if (isset($http_response_header[0])) {
                        $this->debug("HTTP Response: " . $http_response_header[0]);
                        
                        // Check if it was a 401 or 403 error
                        if (strpos($http_response_header[0], '401') !== false || 
                            strpos($http_response_header[0], '403') !== false) {
                            $msg = "Authentication failed with API. Check your API token.";
                            $log_msgs[] = $msg;
                            $this->debug("ERROR: " . $msg);
                            $success = false;
                            $connectionError = true;
                            break;
                        }
                    } else {
                        $connectionError = true;
                    }
                    
                    if ($ioc_list !== false && !empty($ioc_list)) {
                        $this->debug("Successfully fetched data for $feed_type, size: " . strlen($ioc_list) . " bytes");
                        $testMode = false; // Got real data, don't use test data
                        break;
                    }
                }
                
                $this->debug("Fetch attempt failed, retrying...");
                sleep(2);
            }
            
            if ($ioc_list === false || empty($ioc_list)) {
                $msg = "Failed to fetch feed for $feed_type after $retries attempts.";
                $log_msgs[] = $msg;
                $this->debug("ERROR: " . $msg);
                
                if ($connectionError) {
                    $this->debug("CONNECTION ERROR: Unable to reach API server. Check network connectivity.");
                    $log_msgs[] = "CONNECTION ERROR: Unable to reach API server.";
                }
                
                $this->debug("Using test data as fallback");
                $testMode = true; // Force using test data as fallback
            }
        } else {
            $this->debug("No API token provided, using test data");
            $log_msgs[] = "No API token provided, using test data";
        }
        
        // Use test data if API didn't work
        if ($testMode) {
            $this->debug("Using test data for $feed_type");
            // Create test data for dev/testing
            if ($feed_type == 'malware_ip') {
                $ioc_list = "1.2.3.4\n5.6.7.8\n9.10.11.12\n192.168.1.1\n8.8.8.8";
            }
            $this->debug("Generated test data for $feed_type");
            
            // Add timestamp to test data so it changes on every update to force alias refresh
            $ioc_list .= "\n# Generated: " . time();
        }
        
        // Simple content validation
        if (empty($ioc_list) || strlen(trim($ioc_list)) < 5) {
            $msg = "API returned empty or invalid data for $feed_type";
            $log_msgs[] = $msg;
            $this->debug("ERROR: " . $msg);
            $success = false;
            return $msg;
        }
        
        // Process IOC data
        $this->debug("Processing IOC data...");
        $ioc_array = array_filter(array_map('trim', explode("\n", $ioc_list)), function($line) {
            // Filter out comments and empty lines
            return !empty($line) && substr($line, 0, 1) !== '#';
        });
        $ioc_count = count($ioc_array);
        
        $this->debug("Found $ioc_count IOCs in the feed");
        
        if ($ioc_count < 1) {
            $msg = "Feed contained no valid IOCs";
            $log_msgs[] = $msg;
            $this->debug("ERROR: " . $msg);
            $success = false;
            return $msg;
        }
        
        $alias_name = "qfeeds_" . $feed_type;
        $alias_path = "/usr/local/opnsense/scripts/qfeeds/aliases/{$alias_name}.txt";
        
        if ($feed_type !== 'malware_ip') {
            return "Feed type not supported";
        }

        if (!is_dir(dirname($alias_path))) {
            @mkdir(dirname($alias_path), 0755, true);
            $this->debug("Created directory: " . dirname($alias_path));
            
            // Double check directory was created
            if (!is_dir(dirname($alias_path))) {
                $msg = "Failed to create directory: " . dirname($alias_path);
                $log_msgs[] = $msg;
                $this->debug($msg);
                $success = false;
                return $msg;
            }
        }
        
        // EXTERNAL ALIAS WORKFLOW (pf table backed)
        // ----------------------------------------------

        // Write or overwrite the alias file
        $new_content = implode("\n", $ioc_array) . "\n# Last updated: " . date('c');
        $this->debug("Writing to alias file: $alias_path with " . count($ioc_array) . " entries");
        if (@file_put_contents($alias_path, $new_content) === false) {
            $msg = "Failed to write to file: $alias_path";
            $log_msgs[] = $msg;
            $this->debug("ERROR: " . $msg);
            $success = false;
            return $msg;
        }

        // Always remove and re-create the external alias definition on every update
        $description = "Q-Feeds $feed_type feed";
        if (!$this->createFirewallAlias($alias_name, $alias_path, $description)) {
            $msg = "Failed to create or verify firewall alias definition for $alias_name.";
            $log_msgs[] = $msg;
            $this->debug("ERROR: " . $msg);
            $success = false;
            return $msg;
        }

        // Always update the pf table for external alias
        if (!$this->updateAlias($alias_name, $ioc_array)) {
            $msg = "Alias $alias_name file updated but pf table reload failed";
            $log_msgs[] = $msg;
            $this->debug("ERROR: " . $msg);
            $success = false;
            return $msg;
        }

        $msg = "Updated alias $alias_name with $ioc_count entries and updated pf table";
        $log_msgs[] = $msg;
        $this->debug($msg);

        return $msg;
    }

    /**
     * Update a firewall alias by updating the pf table (external type).
     * The content file is assumed to be already updated by fetchAndUpdateAlias.
     * @param string $alias_name Name of the alias to update
     * @param array $ioc_array (Currently unused, kept for compatibility with caller)
     * @return bool Success or failure of pfctl update
     */
    private function updateAlias($alias_name, $ioc_array)
    {
        try {
            $this->debug("Updating pf table for external alias: $alias_name");
            $aliasPath = "/usr/local/opnsense/scripts/qfeeds/aliases/{$alias_name}.txt";
            if (file_exists($aliasPath)) {
                $cmd = "/sbin/pfctl -t " . escapeshellarg($alias_name) . " -T replace -f " . escapeshellarg($aliasPath) . " 2>&1";
                @exec($cmd, $out, $rc);
                $this->debug("pfctl replace result (rc=$rc): " . implode(";", $out));
                return $rc === 0;
            } else {
                $this->debug("Alias file does not exist: $aliasPath");
                return false;
            }
        } catch (\Exception $e) {
            $this->debug("ERROR: Exception in updateAlias (pfctl): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if alias exists using the OPNsense Firewall Alias model.
     * @param string $alias_name Name of the alias to check
     * @return bool True if alias exists in the configuration model.
     */
    private function aliasExists($alias_name)
    {
        // First, inspect current configuration through the model – this also sees aliases that
        // were defined earlier in the same run but not yet loaded into pf.
        try {
            $model = new \OPNsense\Firewall\Alias();
            foreach ($model->aliases->alias as $a) {
                if ((string)$a->name === $alias_name) {
                    $this->debug("aliasExists: Found alias $alias_name in config model.");
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // ignore and fall back to CLI probe
        }

        $this->debug("aliasExists: Not in model, trying CLI list for $alias_name.");
        $cmd = "configctl firewall alias_util list | grep -w " . escapeshellarg($alias_name);
        $output = null;
        $result = -1;
        @exec($cmd, $output, $result);
        if ($result === 0 && !empty($output)) {
            $this->debug("aliasExists: Found alias $alias_name via CLI list.");
            return true;
        }
        $this->debug("aliasExists: Alias $alias_name not found (model nor CLI).");
        return false;
    }
    
    /**
     * Removes an alias from config.xml using direct XML manipulation.
     * This is a utility function to help clean up before creating a new one.
     */
    private function removeFirewallAliasXML($alias_name)
    {
        $this->debug("removeFirewallAliasXML: Attempting to remove alias '$alias_name' from config.xml");
        $config_file = '/conf/config.xml';
        if (!file_exists($config_file) || !is_writable($config_file)) {
            $this->debug("removeFirewallAliasXML: Config file $config_file does not exist or is not writable.");
            return false;
        }

        try {
            $xml_content = file_get_contents($config_file);
            if ($xml_content === false) {
                $this->debug("removeFirewallAliasXML: Failed to read $config_file.");
                return false;
            }

            // More aggressive regex to find any <alias>...</alias> block containing <name>$alias_name</name>
            // This aims to remove potentially malformed entries as well.
            // It ensures that $alias_name is the exact content of the <name> tag.
            $pattern = '/<alias>.*?<name>' . preg_quote($alias_name, '/') . '<\/name>.*?<\\/alias>/s';
            
            $original_length = strlen($xml_content);
            $new_xml_content = preg_replace($pattern, '', $xml_content);
            
            if ($new_xml_content === null) {
                 $this->debug("removeFirewallAliasXML: preg_replace failed for alias '$alias_name'.");
                 return false;
            }
            
            if (strlen($new_xml_content) === $original_length) {
                $this->debug("removeFirewallAliasXML: Alias '$alias_name' not found in $config_file with the specified pattern.");
                return false; // Alias not found or no change made
            }

            if (file_put_contents($config_file, $new_xml_content) === false) {
                $this->debug("removeFirewallAliasXML: Failed to write updated XML to $config_file.");
                return false;
            }
            
            $this->debug("removeFirewallAliasXML: Successfully removed occurrences of alias '$alias_name' from $config_file.");
            return true;
        } catch (\Exception $e) {
            $this->debug("removeFirewallAliasXML: Exception while removing alias '$alias_name': " . $e->getMessage());
            return false;
        }
    }

    private function createFirewallAlias($alias_name, $alias_file_path, $description)
    {
        // Always remove all existing aliases with this name before creating a new one
        $this->debug("createFirewallAlias: Removing all existing aliases named '$alias_name' before creation.");
        $this->removeFirewallAliasXML($alias_name);
        // Try using the official FirewallAlias model first and only fall back to raw XML if that fails
        $this->debug("createFirewallAlias: Attempting for: $alias_name using FirewallAliasModel (with legacy XML fallback).");

        // If the alias is already present we are done – prevents duplicates.
        if ($this->aliasExists($alias_name)) {
            $this->debug("createFirewallAlias: Alias '$alias_name' already present – skipping creation.");
            return true;
        }

        try {
            // Load firewall alias model
            $aliasModel = new \OPNsense\Firewall\Alias();

            // Search for an existing alias with the same name (iterator-friendly for all model versions)
            $target = null;
            foreach ($aliasModel->aliases->alias as $alias) {
                if ((string)$alias->name === $alias_name) {
                    $target = $alias;
                    break;
                }
            }

            // Create a new alias node when none exists yet
            if ($target === null) {
                $target = $aliasModel->aliases->alias->Add();
            }

            // Populate / update the alias fields
            $target->name       = $alias_name;
            $target->type       = 'external';
            $target->content    = 'file://' . $alias_file_path;
            // attribute name changed from "descr" to "description" in newer model versions – set both for compatibility
            if (property_exists($target, 'description')) {
                $target->description = $description;
            }
            if (property_exists($target, 'descr')) {
                $target->descr = $description;
            }
            $target->updatefreq = '1';
            $target->enabled    = '1';
            // Leave <proto> empty so the alias applies to IPv4 and IPv6.

            // Persist the change
            $validationRes = $aliasModel->performValidation();
            if ($validationRes->count() > 0) {
                // Some model versions put empty \ArrayObject items in the collection even when there are no real errors.
                // Check if there are **real** messages; if not, continue as success.
                $flat = method_exists($validationRes, 'getFlat') ? $validationRes->getFlat() : (array)$validationRes;
                $hasRealIssues = false;
                foreach ($flat as $msg) {
                    if (!empty($msg)) {
                        $hasRealIssues = true;
                        break;
                    }
                }

                if ($hasRealIssues) {
                    $this->debug("createFirewallAlias: Validation errors when using model: " . json_encode($flat));
                    throw new \RuntimeException('Model validation failed');
                } else {
                    $this->debug("createFirewallAlias: Validation returned empty messages – ignoring as success.");
                }
            }

            $aliasModel->serializeToConfig();

            $this->debug("createFirewallAlias: Alias '$alias_name' stored via model; triggering backend reload.");

            // Reload firewall aliases – ignore result, caller will handle success/failure
            $this->triggerBackendReconfigure('firewall alias reconfigure');

            return true; // Success via model path, no need for legacy XML
        } catch (\Throwable $e) {
            // Check if the alias already exists; if so, treat as success to prevent duplicates.
            if ($this->aliasExists($alias_name)) {
                $this->debug("createFirewallAlias: Alias already exists after validation failure – treating as success.");
                return true;
            }
            $this->debug("createFirewallAlias: Model-based creation failed (" . $e->getMessage() . "), falling back to legacy XML manipulation.");
        }

        // If model-based creation failed, fall back to legacy XML manipulation
        return $this->createFirewallAliasLegacy($alias_name, $alias_file_path, $description);
    }

    /**
     * Legacy alias creation method kept only as a fallback when the model approach fails for some reason.
     * Currently it just logs the failure and returns false.  The original direct-XML implementation
     * was removed for maintainability; if required, that logic can be re-added here.
     */
    private function createFirewallAliasLegacy($alias_name, $alias_file_path, $description)
    {
        $this->debug("createFirewallAliasLegacy: Attempting direct XML manipulation for '$alias_name'.");

        // First remove any existing (possibly malformed) alias definitions of the same name.
        $this->removeFirewallAliasXML($alias_name);

        // If alias already exists at this point treat that as success
        if ($this->aliasExists($alias_name)) {
            $this->debug("createFirewallAliasLegacy: Alias '$alias_name' already exists after cleanup – treating as success.");
            return true;
        }

        $config_file = '/conf/config.xml';
        if (!file_exists($config_file) || !is_writable($config_file)) {
            $this->debug("createFirewallAliasLegacy: Config file $config_file not writable.");
            return false;
        }

        try {
            $xml_content = file_get_contents($config_file);
            if ($xml_content === false) {
                $this->debug("createFirewallAliasLegacy: Failed to read $config_file");
                return false;
            }

            // Build alias XML snippet
            $alias_xml  = "\n<alias>\n";
            $alias_xml .= "  <name>" . htmlspecialchars($alias_name) . "</name>\n";
            $alias_xml .= "  <type>external</type>\n";
            $alias_xml .= "  <content>file://" . htmlspecialchars($alias_file_path) . "</content>\n";
            $alias_xml .= "  <descr>" . htmlspecialchars($description) . "</descr>\n";
            $alias_xml .= "  <updatefreq>1</updatefreq>\n"; // 1 hour
            $alias_xml .= "  <proto></proto>\n";
            $alias_xml .= "  <enabled>1</enabled>\n";
            $alias_xml .= "</alias>";

            // Insert snippet right before </aliases>
            $marker = '</aliases>';
            $pos = strrpos($xml_content, $marker);
            if ($pos === false) {
                // create <aliases>...</aliases> inside <firewall> block if missing
                $fw_end = '</firewall>';
                $fw_pos = strrpos($xml_content, $fw_end);
                if ($fw_pos === false) {
                    $this->debug("createFirewallAliasLegacy: Can't find <firewall> block in config.xml");
                    return false;
                }

                $aliases_section = "\n<aliases>" . $alias_xml . "\n</aliases>";
                $xml_content = substr_replace($xml_content, $aliases_section . "\n" . $fw_end, $fw_pos, strlen($fw_end));
            } else {
                $xml_content = substr_replace($xml_content, $alias_xml . "\n" . $marker, $pos, strlen($marker));
            }

            if (file_put_contents($config_file, $xml_content) === false) {
                $this->debug("createFirewallAliasLegacy: Failed writing updated config.xml");
                return false;
            }

            // Force config reload for other processes
            Config::getInstance()->forceReload();

            // Trigger firewall reload
            $this->triggerBackendReconfigure('filter reload');

            $this->debug("createFirewallAliasLegacy: Alias '$alias_name' created via direct XML.");
            return true;
        } catch (\Throwable $e) {
            $this->debug("createFirewallAliasLegacy: Exception " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper method to trigger an OPNsense backend configuration reload.
     * @param string $action The configd action to run (e.g., "filter reload")
     * @return bool Success or failure
     */
    private function triggerBackendReconfigure(string $action = "filter reload")
    {
        $this->debug("Triggering backend reconfigure action: {$action}");
        try {
            $backend = new Backend();
            $reconfigure_success = false;

            // Attempt 1: Use the specific firewall alias reconfigure API endpoint if appropriate
            // This is generally preferred for alias changes over a full filter reload.
            if ($action === "firewall alias reconfigure" || $action === "filter reload") { // Allow this to be called specifically
                $this->debug("Using Backend::configdpRun for 'firewall alias reconfigure'");
                $raw_response_alias_reconfigure = $backend->configdpRun("firewall alias reconfigure");
                $this->debug("Backend::configdpRun('firewall alias reconfigure') raw response: " . json_encode($raw_response_alias_reconfigure));

                // Check response: success is often an empty string, "OK", or contains no "error" or "fail".
                if (is_string($raw_response_alias_reconfigure) &&
                    (trim($raw_response_alias_reconfigure) === "OK" || trim($raw_response_alias_reconfigure) === "" ||
                    (stripos($raw_response_alias_reconfigure, "error") === false && stripos($raw_response_alias_reconfigure, "fail") === false))) {
                    $this->debug("Backend::configdpRun('firewall alias reconfigure') appears to have succeeded.");
                    $reconfigure_success = true;
                } else {
                    $this->debug("Backend::configdpRun('firewall alias reconfigure') may have failed or action not applicable. Response: [" . json_encode($raw_response_alias_reconfigure) . "]");
                }
            }

            // Attempt 2: If the specific alias reconfigure didn't run, wasn't successful, or a different action was requested.
            if (!$reconfigure_success && ($action === "filter reload" || strpos($action, "filter") !== false)) {
                $this->debug("Using Backend::configdpRun for broader action: {$action}");
                $raw_response_filter_reload = $backend->configdpRun($action); // Use the originally passed action if it's filter-related
                $this->debug("Backend::configdpRun('{$action}') raw response: " . json_encode($raw_response_filter_reload));

                if (is_string($raw_response_filter_reload) &&
                    (trim($raw_response_filter_reload) === "OK" || trim($raw_response_filter_reload) === "" ||
                    (stripos($raw_response_filter_reload, "error") === false && stripos($raw_response_filter_reload, "fail") === false))) {
                    $this->debug("Backend::configdpRun('{$action}') appears to have succeeded.");
                    $reconfigure_success = true;
                } else {
                    $this->debug("Backend::configdpRun('{$action}') may have failed. Response: [" . json_encode($raw_response_filter_reload) . "]");
                }
            }


            if ($reconfigure_success) {
                return true;
            }

            // Fallback to exec /usr/local/etc/rc.filter_configure if configdpRun failed or wasn't definitive
            $this->debug("Backend API calls for '{$action}' did not report definitive success. Attempting fallback: exec /usr/local/etc/rc.filter_configure");
            $exec_output = null;
            $return_var = -1;
            $fallback_command = "/usr/local/etc/rc.filter_configure";
            
            @exec($fallback_command, $exec_output, $return_var);
            $log_output = is_array($exec_output) ? implode("; ", $exec_output) : strval($exec_output);
            $this->debug("Fallback exec {$fallback_command}: Output: [{$log_output}], Return code: {$return_var}");
            return $return_var === 0;

        } catch (\Exception $e) {
            $this->debug("Exception during backend reconfigure '{$action}': " . $e->getMessage());
            $this->debug("Stack trace: " . $e->getTraceAsString());
            $this->debug("Attempting final fallback due to exception: exec /usr/local/etc/rc.filter_configure");
            $exec_output = null;
            $return_var = -1;
            @exec("/usr/local/etc/rc.filter_configure", $exec_output, $return_var);
            $log_output = is_array($exec_output) ? implode("; ", $exec_output) : strval($exec_output);
            $this->debug("Final fallback exec rc.filter_configure: Output: [{$log_output}], Return code: {$return_var}");
            return $return_var === 0;
        }
    }

    private function updateCronJob($interval)
    {
        $interval = max(20, (int)$interval);
        $cron_path = "/etc/cron.d/qfeeds";
        $cron_line = "*/$interval * * * * root /usr/local/bin/php /usr/local/opnsense/scripts/qfeeds/update_feeds.php\n";
        $content = "# Qfeeds plugin cron job\n# This will be updated dynamically based on user interval\n" . $cron_line;
        
        file_put_contents($cron_path, $content);
        
        // Refresh cron
        @exec("/usr/bin/touch /etc/cron.d", $output, $return_var);
    }

    private function logUpdate($log_msgs)
    {
        $logfile = "/var/log/qfeeds.log";
        $date = date('c');
        foreach ($log_msgs as $msg) {
            @file_put_contents($logfile, "[$date] $msg\n", FILE_APPEND);
        }
    }

    /**
     * Perform cleanup
     * @return array
     */
    public function cleanupAction()
    {
        $mdlQfeeds = $this->getModel();
        // Clear config
        $mdlQfeeds->general->api_token = '';
        $mdlQfeeds->feed_types->malware_ip = '0';
        $mdlQfeeds->general->interval = '20';
        $mdlQfeeds->general->last_update_time = '';
        $mdlQfeeds->general->last_update_result = '';
        $mdlQfeeds->general->last_ioc_count = '0';

        // Save changes
        $mdlQfeeds->serializeToConfig();
        Config::getInstance()->save();
        
        // Remove alias files and backups
        $alias_dir = "/usr/local/opnsense/scripts/qfeeds/aliases/";
        $removed = 0;
        if (is_dir($alias_dir)) {
            foreach (glob($alias_dir . "qfeeds_*.txt*") as $file) {
                @unlink($file);
                $removed++;
            }
        }
        $this->logUpdate(["Cleanup performed, removed $removed alias files and reset config."]);
        return ["result" => "Cleanup complete. Removed $removed alias files and reset config."];
    }

    /**
     * Cleanup all duplicate or broken qfeeds_ aliases from config.xml and pf tables.
     * Keeps only the correct external alias for each feed type.
     * @return array
     */
    public function cleanupAliasesAction()
    {
        $this->debug("Starting cleanupAliasesAction (DOM-based)");
        $config_file = '/conf/config.xml';
        $alias_prefix = 'qfeeds_';
        $removed = 0;
        $kept = 0;
        $errors = [];
        $before_names = [];
        $after_names = [];
        if (!file_exists($config_file) || !is_writable($config_file)) {
            $msg = "Config file $config_file does not exist or is not writable.";
            $this->debug($msg);
            return ["result" => "error", "message" => $msg];
        }
        $xml = new \DOMDocument();
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;
        if (!$xml->load($config_file)) {
            $msg = "Failed to load $config_file as XML.";
            $this->debug($msg);
            return ["result" => "error", "message" => $msg];
        }
        $xpath = new \DOMXPath($xml);
        $aliases = $xpath->query('//aliases/alias');
        $alias_map = [];
        foreach ($aliases as $alias) {
            $nameNode = $alias->getElementsByTagName('name')->item(0);
            if ($nameNode && stripos($nameNode->nodeValue, $alias_prefix) === 0) {
                $name = $nameNode->nodeValue;
                $before_names[] = $name;
                if (!isset($alias_map[$name])) {
                    $alias_map[$name] = [];
                }
                $alias_map[$name][] = $alias;
            }
        }
        // Remove all but the first valid external alias for each name
        foreach ($alias_map as $name => $aliasNodes) {
            $kept_this = false;
            foreach ($aliasNodes as $alias) {
                $typeNode = $alias->getElementsByTagName('type')->item(0);
                if (!$kept_this && $typeNode && strtolower($typeNode->nodeValue) === 'external') {
                    $kept++;
                    $kept_this = true;
                    $after_names[] = $name;
                    continue;
                }
                // Remove this alias node
                $alias->parentNode->removeChild($alias);
                $removed++;
            }
        }
        // Save the cleaned config.xml
        if ($xml->save($config_file) === false) {
            $msg = "Failed to write updated config.xml.";
            $this->debug($msg);
            return ["result" => "error", "message" => $msg];
        }
        // Remove pf tables for all qfeeds_ aliases except the ones we kept
        foreach ($before_names as $name) {
            if (!in_array($name, $after_names)) {
                $cmd = "/sbin/pfctl -t " . escapeshellarg($name) . " -T flush 2>&1";
                @exec($cmd, $out, $rc);
                $this->debug("Flushed pf table for $name: rc=$rc, output=" . implode(';', $out));
            }
        }
        // Force full config/model reload
        \OPNsense\Core\Config::getInstance()->forceReload();
        $this->triggerBackendReconfigure('filter reload');
        $msg = "Cleanup complete. Removed $removed duplicate/broken aliases, kept $kept valid external aliases.";
        $this->debug($msg);
        return ["result" => "ok", "message" => $msg, "removed" => $removed, "kept" => $kept, "before" => $before_names, "after" => $after_names, "errors" => $errors];
    }

    /**
     * Return the last 500 lines of /var/log/qfeeds.log for the GUI log viewer
     */
    public function qfeedsLogAction()
    {
        $logfile = '/var/log/qfeeds.log';
        $max_lines = 500;
        if (!file_exists($logfile)) {
            return ["log" => "Log file not found: $logfile" ];
        }
        $lines = [];
        $fp = fopen($logfile, 'r');
        if ($fp === false) {
            return ["log" => "Unable to open log file: $logfile" ];
        }
        // Efficiently read last N lines
        $buffer = '';
        $pos = -1;
        $line_count = 0;
        fseek($fp, 0, SEEK_END);
        $filesize = ftell($fp);
        while ($filesize > 0 && $line_count < $max_lines) {
            $seek = min(4096, $filesize);
            fseek($fp, -$seek, SEEK_CUR);
            $buffer = fread($fp, $seek) . $buffer;
            fseek($fp, -$seek, SEEK_CUR);
            $filesize -= $seek;
            $lines = explode("\n", $buffer);
            $line_count = count($lines) - 1;
        }
        fclose($fp);
        $lines = array_slice($lines, -$max_lines);
        return ["log" => implode("\n", $lines) ];
    }

    /**
     * Delete /var/log/qfeeds.log (admin only)
     */
    public function flushLogAction()
    {
        try {
            if (!$this->checkAuth()) {
                return ["result" => "error", "message" => "Not authorized."];
            }
            $logfile = '/var/log/qfeeds.log';
            if (!file_exists($logfile)) {
                return ["result" => "error", "message" => "Log file not found."];
            }
            if (!@unlink($logfile)) {
                return ["result" => "error", "message" => "Failed to delete log file."];
            }
            return ["result" => "ok", "message" => "Log deleted (flushed)."];
        } catch (\Throwable $e) {
            return ["result" => "error", "message" => "Exception: " . $e->getMessage()];
        }
    }
} 

