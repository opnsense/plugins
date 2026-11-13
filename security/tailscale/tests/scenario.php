<?php

/*
 * Copyright (c) 2026 Emanuele Baglini
 * SPDX-License-Identifier: BSD-2-Clause
 */
/* Test-only boundaries: no firewall, daemon, tailnet or host network access. */
// Keep the simulated models and scenario execution together as one test fixture.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses, PSR1.Files.SideEffects
namespace OPNsense\Tailscale {
    class Settings
    {
        public $enabled;
        public function __construct()
        {
            $this->enabled = $GLOBALS['scenario']['enabled'] ?? '1';
        }
    }
}
namespace OPNsense\Routing {
    class Gateways
    {
        public function gatewaysIndexedByName($disabled = false, $localhost = false, $inactive = false)
        {
            \record('gateway_selection', [$disabled, $localhost, $inactive]);
            return array_filter(
                $GLOBALS['scenario']['gateways'] ?? [],
                static function ($gw) use ($disabled, $inactive) {
                    return ($disabled || empty($gw['disabled'])) && ($inactive || !empty($gw['if']));
                }
            );
        }
    }
}
namespace {
    $scenario = json_decode(getenv('TAILSCALE_TEST_SCENARIO'), true, 512, JSON_THROW_ON_ERROR);
    $config = ['interfaces' => $scenario['interfaces'], 'system' => []];
    $trace = [];
    $plugin = dirname(__DIR__);
    $core = getenv('TAILSCALE_TEST_CORE');
    set_include_path('/test-includes' . PATH_SEPARATOR . $plugin . '/src/etc/inc');
    register_shutdown_function(static function () {
        echo "\nRESULT:" . json_encode(['trace' => $GLOBALS['trace']], JSON_THROW_ON_ERROR) . "\n";
    });
    function record($kind, $value = null)
    {
        $GLOBALS['trace'][] = [$kind, $value];
    }
    function load_core_functions($relative, $names)
    {
        $source = file_get_contents($GLOBALS['core'] . '/' . $relative);
        $tokens = token_get_all($source);
        $found = [];
        for ($i = 0; $i < count($tokens); $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || !in_array($tokens[$j][1], $names)) {
                continue;
            }
            $name = $tokens[$j][1];
            $body = '';
            $depth = 0;
            $started = false;
            for (; $i < count($tokens); $i++) {
                $t = $tokens[$i];
                $text = is_array($t) ? $t[1] : $t;
                $body .= $text;
                if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]))) {
                    $depth++;
                    $started = true;
                } elseif ($t === '}') {
                    $depth--;
                }
                if ($started && $depth === 0) {
                    break;
                }
            }
            eval($body);
            $found[] = $name;
        }
        if (array_diff($names, $found)) {
            throw new \RuntimeException('Missing original functions: ' . implode(',', array_diff($names, $found)));
        }
    }
    function legacy_config_get_interfaces()
    {
        return $GLOBALS['config']['interfaces'];
    }
    function config_read_array(...$path)
    {
        $value = $GLOBALS['config'];
        foreach ($path as $key) {
            if ($key === false) {
                break;
            } $value = $value[$key] ?? [];
        }
        return $value;
    }
    function get_real_interface($interface, $family = null)
    {
        return $GLOBALS['config']['interfaces'][$interface]['if'] ?? $interface;
    }
    function legacy_interfaces_details($device = null)
    {
        record('address_read', $device);
        return $GLOBALS['scenario']['details'] ?? [];
    }
    function is_ipv6_allowed()
    {
        return $GLOBALS['scenario']['ipv6_allowed'] ?? true;
    }
    function is_ipaddrv4($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
    function is_ipaddrv6($ip)
    {
        return filter_var(explode('%', $ip ?? '')[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
    function is_linklocal($ip)
    {
        $bytes = @inet_pton(explode('%', $ip ?? '')[0]);
        return $bytes !== false && strlen($bytes) === 16 && ord($bytes[0]) === 0xfe && (ord($bytes[1]) & 0xc0) === 0x80;
    }
    function is_uniquelocal($ip)
    {
        $bytes = @inet_pton($ip);
        return $bytes !== false && strlen($bytes) === 16 && (ord($bytes[0]) & 0xfe) === 0xfc;
    }
    function gen_subnet($ip, $bits)
    {
        return long2ip(ip2long($ip) & ($bits ? (-1 << (32 - $bits)) : 0));
    }
    function gen_subnetv6($ip, $bits)
    {
        return $ip; /* /128 synthetic fixtures only */
    }
    function ip_in_subnet($ip, $network)
    {
        [$prefix, $bits] = explode('/', $network);
        $a = @inet_pton($ip);
        $b = @inet_pton($prefix);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        for ($i = 0; $bits > 0; $i++, $bits -= 8) {
            $mask = (0xff << (8 - min(8, (int)$bits))) & 0xff;
            if ((ord($a[$i]) & $mask) !== (ord($b[$i]) & $mask)) {
                return false;
            }
        }
        return true;
    }
    function interfaces_has_prefix_only($interface)
    {
        return $GLOBALS['scenario']['prefix_only'] ?? false;
    }
    function link_interface_to_track6($interface, $all = false)
    {
        return [];
    }
    function service_log(...$args)
    {
    }
    function log_msg($message, $level = LOG_INFO)
    {
        record('log', [$level, $message]);
    }
    function system_host_route(...$args)
    {
        record('host_route', $args);
    }
    function mwexecfb($format, $args = [], $options = [])
    {
        record('background_command', [$format, $args, $options]);
        return 0;
    }
    function isvalidpid($file)
    {
        return true;
    }
    function killbypid($file, $signal = null)
    {
        record('signal', [$file, $signal]);
    }
    function dpinger_processes()
    {
        $result = [];
        foreach ($GLOBALS['scenario']['gateways'] as $name => $gw) {
            $result[$name] = ['pidfile' => '/var/run/dpinger_' . $name . '.pid'];
        }
        return $result;
    }
    if (!function_exists('gettext')) {
        function gettext($text)
        {
            return $text;
        }
    }
    function configdp_run(...$args)
    {
        throw new \RuntimeException('Unexpected full interface renewal request.');
    }
    function plugins_scan($glob = '*')
    {
        $paths = ['dpinger' => '/test-includes/empty.inc',
            'tailscale' => $GLOBALS['plugin'] . '/src/etc/inc/plugins.inc.d/tailscale.inc'];
        return array_filter($paths, static fn($path, $name) => fnmatch($glob, $name), ARRAY_FILTER_USE_BOTH);
    }
    load_core_functions('src/etc/inc/interfaces.inc', [
        'convert_real_interface_to_friendly_interface_name', 'interfaces_addresses', 'interfaces_primary_address',
        '_interfaces_primary_address6', 'interfaces_routed_address6', 'interfaces_scoped_address6'
    ]);
    load_core_functions('src/etc/inc/plugins.inc', ['plugins_argument_map', 'plugins_configure']);
    load_core_functions('src/etc/inc/plugins.inc.d/dpinger.inc', [
        'dpinger_instances', 'dpinger_services', 'dpinger_configure', 'dpinger_configure_do'
    ]);
    require $core . '/src/sbin/pluginctl';
}
