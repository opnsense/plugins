<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class RadiusServerController extends ApiControllerBase
{
    use AuditTrait;
    use ValidationTrait;
    
    public function listAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $current = (int)($p['current'] ?? 0);
        $rowCount = (int)($p['rowCount'] ?? 12);
        $searchPhrase = strtolower(trim((string)($p['searchPhrase'] ?? '')));
        
        $rows = [];
        
        // Try multiple config reading methods
        $methods = [];
        
        // Method 1: Direct XML file reading
        $configPaths = ['/conf/config.xml', '/usr/local/etc/config.xml', '/etc/config.xml'];
        foreach ($configPaths as $path) {
            if (is_readable($path)) {
                $xml = @simplexml_load_file($path);
                if ($xml && isset($xml->system->authserver)) {
                    $methods['direct_xml'] = $path;
                    foreach ($xml->system->authserver as $srv) {
                        if ((string)($srv->type ?? '') !== 'radius') continue;
                        
                        $name = (string)($srv->name ?? '');
                        $host = (string)($srv->host ?? '');
                        $auth_port = (string)($srv->auth_port ?? $srv->radius_auth_port ?? '1812');
                        $acct_port = (string)($srv->acct_port ?? $srv->radius_acct_port ?? '');
                        $timeout = (string)($srv->timeout ?? $srv->radius_timeout ?? '5');
                        $stationid = (string)($srv->radius_stationid ?? '');
                        $descr = (string)($srv->descr ?? '');
                        $services = (!empty($auth_port) && !empty($acct_port)) ? 'both' : 'auth';
                        
                        $rows[] = [
                            'name' => $name,
                            'host' => $host,
                            'secret' => '***',
                            'services' => $services,
                            'auth_port' => $auth_port,
                            'acct_port' => $acct_port === '' ? null : $acct_port,
                            'timeout' => $timeout,
                            'stationid' => $stationid,
                            'descr' => $descr,
                            'sync_memberof' => !empty((string)($srv->sync_memberof ?? '')),
                            'sync_create_local_users' => !empty((string)($srv->sync_create_local_users ?? '')),
                            'sync_memberof_groups' => !empty((string)($srv->sync_memberof_groups ?? '')) ? explode(',', (string)$srv->sync_memberof_groups) : [],
                            'sync_default_groups' => !empty((string)($srv->sync_default_groups ?? '')) ? explode(',', (string)$srv->sync_default_groups) : [],
                            'refid' => (string)($srv->refid ?? ''),
                        ];
                    }
                    break;
                }
            }
        }
        
        // Method 2: Config::getInstance() if direct reading failed
        if (count($rows) === 0) {
            try {
                Config::getInstance()->forceReload();
                $cfg = Config::getInstance()->object();
                $methods['config_instance'] = 'used';
                
                if (isset($cfg->system->authserver)) {
                    foreach ($cfg->system->authserver as $srv) {
                        if ((string)($srv->type ?? '') !== 'radius') continue;
                        
                        $name = (string)($srv->name ?? '');
                        $host = (string)($srv->host ?? '');
                        $auth_port = (string)($srv->auth_port ?? $srv->radius_auth_port ?? '1812');
                        $acct_port = (string)($srv->acct_port ?? $srv->radius_acct_port ?? '');
                        $timeout = (string)($srv->timeout ?? $srv->radius_timeout ?? '5');
                        $stationid = (string)($srv->radius_stationid ?? '');
                        $descr = (string)($srv->descr ?? '');
                        $services = (!empty($auth_port) && !empty($acct_port)) ? 'both' : 'auth';
                        
                        $rows[] = [
                            'name' => $name,
                            'host' => $host,
                            'secret' => '***',
                            'services' => $services,
                            'auth_port' => $auth_port,
                            'acct_port' => $acct_port === '' ? null : $acct_port,
                            'timeout' => $timeout,
                            'stationid' => $stationid,
                            'descr' => $descr,
                            'sync_memberof' => !empty((string)($srv->sync_memberof ?? '')),
                            'sync_create_local_users' => !empty((string)($srv->sync_create_local_users ?? '')),
                            'sync_memberof_groups' => !empty((string)($srv->sync_memberof_groups ?? '')) ? explode(',', (string)$srv->sync_memberof_groups) : [],
                            'sync_default_groups' => !empty((string)($srv->sync_default_groups ?? '')) ? explode(',', (string)$srv->sync_default_groups) : [],
                            'refid' => (string)($srv->refid ?? ''),
                        ];
                    }
                }
            } catch (\Exception $e) {
                $methods['config_instance_error'] = $e->getMessage();
            }
        }
        
        if ($searchPhrase !== '') {
            $rows = array_values(array_filter($rows, function($r) use ($searchPhrase){
                return (strpos(strtolower($r['name']), $searchPhrase) !== false)
                       || (strpos(strtolower($r['host']), $searchPhrase) !== false)
                       || (strpos(strtolower($r['descr']), $searchPhrase) !== false);
            }));
        }
        
        $total = count($rows);
        if ($rowCount == -1) { $rowCount = $total; }
        elseif ($rowCount <= 0) { $rowCount = 25; }
        $current = max(1, $current);
        $offset = ($current - 1) * $rowCount;
        $pageRows = array_slice($rows, $offset, $rowCount);
        
        return [
            'current' => $current,
            'rowCount' => count($pageRows),
            'rows' => $pageRows,
            'total' => $total
        ];
    }

    public function deleteAction(): array
    {
        $name = trim((string)($this->request->getPost('name') ?? $this->request->get('name') ?? ''));
        if ($name === '') { 
            return ['status'=>'error','message'=>'missing name']; 
        }
        
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system) || !isset($cfg->system->authserver)) { 
            return ['status'=>'ok','deleted'=>false]; 
        }
        
        $deleted = false;
        $idx = 0;
            foreach ($cfg->system->authserver as $srv) {
            if ((string)($srv->type ?? '') === 'radius' && (string)$srv->name === $name) { 
                    unset($cfg->system->authserver[$idx]); 
                    $deleted = true; 
                    break; 
                }
                $idx++;
        }
        
        if ($deleted) { 
            Config::getInstance()->save(); 
            $this->audit('radius.delete', ['name'=>$name]); 
        }
        return ['status'=>'ok','deleted'=>$deleted];
    }

    public function upsertAction(): array
    {
            $p = $this->request->getPost();
            
            // Extract and validate required fields
            $name = trim((string)($p['name'] ?? ''));
            $host = trim((string)($p['host'] ?? ''));
            $secret = (string)($p['secret'] ?? '');
        $services = (string)($p['services'] ?? $p['radius_srvcs'] ?? 'both');
            
            if ($name === '') {
                return ['status'=>'error','message'=>'missing descriptive name'];
            }
            if ($host === '') {
                return ['status'=>'error','message'=>'missing hostname or IP address'];
            }
            if ($secret === '') {
                return ['status'=>'error','message'=>'missing shared secret'];
            }
            if (!$this->isValidHostname($host)) {
                return ['status'=>'error','message'=>'invalid hostname or IP address'];
            }
            
            // Extract optional fields with defaults
            $auth_port = (int)($p['auth_port'] ?? $p['radius_auth_port'] ?? 1812);
            $acct_port = (int)($p['acct_port'] ?? $p['radius_acct_port'] ?? 1813);
            $timeout = (int)($p['timeout'] ?? $p['radius_timeout'] ?? 5);
            $stationid = trim((string)($p['stationid'] ?? $p['radius_stationid'] ?? ''));
            $descr = trim((string)($p['descr'] ?? ''));
            
            if ($timeout <= 0) {
                return ['status'=>'error','message'=>'timeout must be numeric and positive'];
            }
            
            $cfg = Config::getInstance()->object();
            
        // Find existing server by name
        $existing = null;
        if (isset($cfg->system->authserver)) {
            foreach ($cfg->system->authserver as $srv) {
                if ((string)$srv->name === $name) { 
                    $existing = $srv; 
                    break; 
                }
            }
        }
            
            if ($existing === null) {
                // Create new authserver entry
            if (!isset($cfg->system)) { $cfg->addChild('system'); }
                $srv = $cfg->system->addChild('authserver');
                $srv->addChild('refid', uniqid());
                $srv->addChild('name', htmlspecialchars($name));
                $srv->addChild('type', 'radius');
                $srv->addChild('host', htmlspecialchars($host));
            $srv->addChild('secret', htmlspecialchars($secret));
            $srv->addChild('timeout', (string)$timeout);
                
            // Set service-dependent ports (using legacy field names)
                if ($services === 'both') {
                $srv->addChild('auth_port', (string)$auth_port);
                $srv->addChild('acct_port', (string)$acct_port);
                } elseif ($services === 'auth') {
                $srv->addChild('auth_port', (string)$auth_port);
                }
                
                // Optional fields
                if ($stationid !== '') {
                    $srv->addChild('radius_stationid', htmlspecialchars($stationid));
                }
                if ($descr !== '') {
                    $srv->addChild('descr', htmlspecialchars($descr));
                }
                
                Config::getInstance()->save();
                $this->audit('radius.create', ['name'=>$name]);
                
                return ['status'=>'ok','created'=>true,'message'=>'RADIUS server created successfully'];
                
            } else {
                // Update existing server
                $existing->type = 'radius';
                $existing->host = htmlspecialchars($host);
            $existing->secret = htmlspecialchars($secret);
            $existing->timeout = (string)$timeout;
                
            // Update service-dependent ports (using legacy field names)
                if ($services === 'both') {
                $existing->auth_port = (string)$auth_port;
                $existing->acct_port = (string)$acct_port;
                } elseif ($services === 'auth') {
                $existing->auth_port = (string)$auth_port;
                if (isset($existing->acct_port)) {
                    unset($existing->acct_port);
                }
                }
                
                // Remove legacy radius_* fields to prevent duplicate keys in config
                if (isset($existing->radius_auth_port)) {
                    unset($existing->radius_auth_port);
                }
                if (isset($existing->radius_acct_port)) {
                    unset($existing->radius_acct_port);
                }
                if (isset($existing->radius_timeout)) {
                    unset($existing->radius_timeout);
                }
                if (isset($existing->radius_secret)) {
                    unset($existing->radius_secret);
                }
                
                // Update optional fields
                if ($stationid !== '') {
                    $existing->radius_stationid = htmlspecialchars($stationid);
                } elseif (isset($existing->radius_stationid)) {
                    unset($existing->radius_stationid);
                }
                
                if ($descr !== '') {
                    $existing->descr = htmlspecialchars($descr);
                } elseif (isset($existing->descr)) {
                    unset($existing->descr);
                }
                
                Config::getInstance()->save();
                $this->audit('radius.update', ['name'=>$name]);
                
                return ['status'=>'ok','created'=>false,'message'=>'RADIUS server updated successfully'];
        }
    }

    public function getAction(): array
    {
        $name = trim((string)($this->request->get('name') ?? ''));
        if ($name === '') {
            return ['status'=>'error','message'=>'missing server name parameter'];
        }
        
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system->authserver)) {
            return ['status'=>'error','message'=>'no servers configured'];
        }
        
            foreach ($cfg->system->authserver as $srv) {
                if ((string)$srv->name === $name && (string)$srv->type === 'radius') {
                // Parse group arrays
                $sync_memberof_groups = [];
                $sync_default_groups = [];
                
                if (!empty($srv->sync_memberof_groups)) {
                    $sync_memberof_groups = explode(',', (string)$srv->sync_memberof_groups);
                }
                if (!empty($srv->sync_default_groups)) {
                    $sync_default_groups = explode(',', (string)$srv->sync_default_groups);
                }
                
                // Determine service type
                $services = 'auth';
                $auth_port_val = (string)($srv->auth_port ?? $srv->radius_auth_port ?? '');
                $acct_port_val = (string)($srv->acct_port ?? $srv->radius_acct_port ?? '');
                if (!empty($auth_port_val) && !empty($acct_port_val)) {
                    $services = 'both';
                }
                
                return [
                    'status' => 'ok',
                    'server' => [
                        'name' => (string)($srv->name ?? ''),
                        'host' => (string)($srv->host ?? ''),
                        'secret' => (string)($srv->secret ?? $srv->radius_secret ?? ''),
                        'services' => $services,
                        'auth_port' => (string)($srv->auth_port ?? $srv->radius_auth_port ?? '1812'),
                        'acct_port' => (string)($srv->acct_port ?? $srv->radius_acct_port ?? '1813'),
                        'timeout' => (string)($srv->timeout ?? $srv->radius_timeout ?? '5'),
                        'stationid' => (string)($srv->radius_stationid ?? ''),
                        'descr' => (string)($srv->descr ?? ''),
                        'sync_memberof' => !empty($srv->sync_memberof),
                        'sync_create_local_users' => !empty($srv->sync_create_local_users),
                        'sync_memberof_groups' => $sync_memberof_groups,
                        'sync_default_groups' => $sync_default_groups,
                        'refid' => (string)($srv->refid ?? ''),
                    ]
                ];
            }
        }
        
        return ['status'=>'error','message'=>'server not found'];
    }

    public function testAction(): array
    {
        return ['status'=>'ok','message'=>'RadiusServerController is working','timestamp'=>date('H:i:s')];
    }
    
    public function listallAction(): array
    {
        $rows = [];
        
        // Try direct XML reading from production paths
        $configPaths = ['/conf/config.xml', '/usr/local/etc/config.xml', '/etc/config.xml'];
        foreach ($configPaths as $path) {
            if (is_readable($path)) {
                $xml = @simplexml_load_file($path);
                if ($xml && isset($xml->system->authserver)) {
                    foreach ($xml->system->authserver as $srv) {
                        if ((string)($srv->type ?? '') !== 'radius') continue;
                        
                        $rows[] = [
                            'name' => (string)($srv->name ?? ''),
                            'host' => (string)($srv->host ?? ''),
                            'secret' => '***',
                            'services' => (!empty((string)($srv->auth_port ?? '')) && !empty((string)($srv->acct_port ?? ''))) ? 'both' : 'auth',
                            'auth_port' => (string)($srv->auth_port ?? $srv->radius_auth_port ?? '1812'),
                            'acct_port' => (string)($srv->acct_port ?? $srv->radius_acct_port ?? ''),
                            'timeout' => (string)($srv->timeout ?? $srv->radius_timeout ?? '5'),
                            'stationid' => (string)($srv->radius_stationid ?? ''),
                            'descr' => (string)($srv->descr ?? ''),
                            'refid' => (string)($srv->refid ?? ''),
                        ];
                    }
                    break;
                }
            }
        }
        
        return [
            'current' => 1,
            'rowCount' => count($rows),
            'rows' => $rows,
            'total' => count($rows),
            'config_found' => count($rows) > 0
        ];
    }
}

