<?php

/*
 * Copyright (c) 2026 Emanuele Baglini
 * SPDX-License-Identifier: BSD-2-Clause
 */
// A standalone test runner intentionally declares helpers and executes checks.
// phpcs:disable PSR1.Files.SideEffects
if (getenv('TAILSCALE_TEST_SANDBOX') !== '1' || !is_dir(getenv('TAILSCALE_TEST_CORE') ?: '')) {
    fwrite(STDERR, "Run these tests through tests/run.sh (see tests/README.md).\n");
    exit(1);
}
$tests = [];
function test($name, $body)
{
    $GLOBALS['tests'][$name] = $body;
}
function check($condition, $message = 'assertion failed')
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function events($result, $kind)
{
    return array_column(array_values(array_filter($result['trace'], static fn($e) => $e[0] === $kind)), 1);
}
function scenario($logical = 'opt11')
{
    $gateway = static fn($name, $interface, $device, $family, $peer) => [
        'name' => $name, 'interface' => $interface, 'if' => $device, 'ipprotocol' => $family,
        'gateway' => $peer, 'monitor' => $peer, 'monitor_disable' => '0', 'monitor_noroute' => '0',
        'current_interval' => '1', 'current_loss_interval' => '4', 'current_time_period' => '60',
        'current_data_length' => '1'
    ];
    return [
        'interfaces' => [
            $logical => ['if' => 'tailscale0', 'enable' => '1'],
            'wan' => ['if' => 'em0', 'enable' => '1'], 'opt2' => ['if' => 'wg_test', 'enable' => '1']
        ],
        'details' => [
            'tailscale0' => ['ipv4' => [['ipaddr' => '192.0.2.10', 'subnetbits' => 32]],
                'ipv6' => [['ipaddr' => 'fd00:1::10', 'subnetbits' => 128, 'vltime' => '0']]],
            'em0' => ['ipv4' => [['ipaddr' => '198.51.100.2', 'subnetbits' => 24]], 'ipv6' => []],
            'wg_test' => ['ipv4' => [['ipaddr' => '203.0.113.10', 'subnetbits' => 32]], 'ipv6' => []]
        ],
        'gateways' => [
            'mesh4' => $gateway('mesh4', $logical, 'tailscale0', 'inet', '192.0.2.20'),
            'mesh6' => $gateway('mesh6', $logical, 'tailscale0', 'inet6', 'fd00:1::20'),
            'wan4' => $gateway('wan4', 'wan', 'em0', 'inet', '198.51.100.1'),
            'wg4' => $gateway('wg4', 'opt2', 'wg_test', 'inet', '203.0.113.20')
        ]
    ];
}
function execute($command, $s, $env = [])
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), ['TAILSCALE_TEST_SCENARIO' => json_encode($s, JSON_THROW_ON_ERROR)], $env)
    );
    check(is_resource($process), 'could not start child');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $result = ['status' => proc_close($process), 'trace' => [], 'reported' => false];
    check($stderr === '', 'unexpected PHP/shell error: ' . $stderr);
    if (preg_match('/RESULT:(.+)\s*$/', $stdout, $match)) {
        $result = array_merge($result, json_decode($match[1], true, 512, JSON_THROW_ON_ERROR), ['reported' => true]);
    }
    return $result;
}
function hook_copy()
{
    $path = tempnam('/tmp', 'tailscale-hook-');
    $hook = file_get_contents(dirname(__DIR__) . '/src/etc/rc.syshook.d/start/50-tailscale');
    /* The shell hook is unchanged except for the executable path at the system boundary. */
    file_put_contents($path, str_replace(
        '/usr/local/sbin/pluginctl',
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/scenario.php'),
        $hook
    ));
    return $path;
}
function run_scenario($s)
{
    $hook = hook_copy();
    try {
        return execute(['/bin/sh', $hook], $s);
    } finally {
        unlink($hook);
    }
}
function run_action($action, $native_code)
{
    $actions = file_get_contents(dirname(__DIR__) . '/src/opnsense/service/conf/actions.d/actions_tailscale.conf');
    check(preg_match('/^\\[' . $action . '\\]\ncommand:([^\n]+)/m', $actions, $match) === 1);
    $native = tempnam('/tmp', 'tailscale-native-');
    file_put_contents($native, "#!/bin/sh\n" . 'exit "$NATIVE_CODE"' . "\n");
    $hook = hook_copy();
    $command = str_replace(
        ['/usr/local/etc/rc.d/tailscaled', '/usr/local/etc/rc.syshook.d/start/50-tailscale'],
        ['/bin/sh ' . escapeshellarg($native), '/bin/sh ' . escapeshellarg($hook)],
        $match[1]
    );
    try {
        return execute(['/bin/sh', '-c', $command], scenario(), ['NATIVE_CODE' => (string)$native_code]);
    } finally {
        unlink($native);
        unlink($hook);
    }
}
function monitors($result)
{
    $monitors = [];
    foreach (events($result, 'background_command') as [$format, $args]) {
        if (is_array($format) && str_starts_with($format[0], '/usr/local/bin/dpinger')) {
            $monitors[$args[0]] = $args[1];
        }
    }
    ksort($monitors);
    return $monitors;
}
function check_monitors($result, $expected)
{
    check($result['status'] === 0 && $result['reported'], 'hook did not complete');
    ksort($expected);
    check(monitors($result) === $expected, 'unexpected monitors or bind addresses');
    foreach (events($result, 'signal') as [$file]) {
        check(!str_contains($file, 'dpinger_wan4') && !str_contains($file, 'dpinger_wg4'), 'unrelated monitor stopped');
    }
}
function check_quiet($s)
{
    $result = run_scenario($s);
    check_monitors($result, []);
    foreach (['signal', 'background_command', 'host_route', 'address_read'] as $kind) {
        check(events($result, $kind) === [], 'unexpected work: ' . $kind);
    }
}

test('dual-stack uses native monitor selection and one watcher notification', function () {
    $result = run_scenario(scenario());
    check_monitors($result, ['mesh4' => '192.0.2.10', 'mesh6' => 'fd00:1::10']);
    check(events($result, 'signal') === [
        ['/var/run/dpinger_mesh4.pid', null], ['/var/run/dpinger_mesh6.pid', null],
        ['/var/run/gateway_watcher.pid', 'HUP']
    ]);
    check(count(events($result, 'host_route')) === 2);
    $messages = implode('\n', array_column(events($result, 'log'), 1));
    check(substr_count($messages, 'plugins_configure monitor:dpinger') === 1, 'recursive or duplicate dispatch');
});
foreach ([4, 6] as $version) {
    test('IPv' . $version . ' alone', function () use ($version) {
        $s = scenario();
        unset($s['gateways'][$version === 4 ? 'mesh6' : 'mesh4']);
        check_monitors(run_scenario($s), $version === 4 ? ['mesh4' => '192.0.2.10'] : ['mesh6' => 'fd00:1::10']);
    });
    test('missing IPv' . $version . ' skips only that family with native success', function () use ($version) {
        $s = scenario();
        $s['details']['tailscale0']['ipv' . $version] = [];
        $result = run_scenario($s);
        check_monitors($result, $version === 4 ? ['mesh6' => 'fd00:1::10'] : ['mesh4' => '192.0.2.10']);
        check(str_contains(implode('\n', array_column(events($result, 'log'), 1)), 'address could not be found'));
    });
}
$quiet_cases = [
    'plugin disabled', 'unassigned', 'interface disabled', 'no matching gateways', 'disabled', 'monitor_disable'
];
foreach ($quiet_cases as $case) {
    test($case . ' never becomes a global monitor restart', function () use ($case) {
        $s = scenario();
        if ($case === 'plugin disabled') {
            $s['enabled'] = '0';
        } elseif ($case === 'unassigned') {
            unset($s['interfaces']['opt11']);
        } elseif ($case === 'interface disabled') {
            unset($s['interfaces']['opt11']['enable']);
        } elseif ($case === 'no matching gateways') {
            unset($s['gateways']['mesh4'], $s['gateways']['mesh6']);
        } else {
            foreach (['mesh4', 'mesh6'] as $name) {
                $s['gateways'][$name][$case] = '1';
            }
        }
        check_quiet($s);
    });
}
foreach (['opt3', 'opt99'] as $logical) {
    test('logical assignment ' . $logical, function () use ($logical) {
        check_monitors(run_scenario(scenario($logical)), ['mesh4' => '192.0.2.10', 'mesh6' => 'fd00:1::10']);
    });
}
test('multiple same-family gateways are passed to one native update', function () {
    $s = scenario();
    $s['gateways']['mesh4b'] = array_merge($s['gateways']['mesh4'], ['name' => 'mesh4b']);
    check_monitors(run_scenario($s), ['mesh4' => '192.0.2.10', 'mesh4b' => '192.0.2.10', 'mesh6' => 'fd00:1::10']);
});
test('global IPv6 disable leaves its monitor untouched', function () {
    $s = scenario();
    $s['ipv6_allowed'] = false;
    $result = run_scenario($s);
    check_monitors($result, ['mesh4' => '192.0.2.10']);
    check(!in_array(['/var/run/dpinger_mesh6.pid', null], events($result, 'signal')));
});
foreach (['tentative', 'detached', 'deprecated', 'link-local'] as $flag) {
    test('native IPv6 selection skips ' . $flag . ' source', function () use ($flag) {
        $s = scenario();
        $s['details']['tailscale0']['ipv6'][0][$flag] = true;
        if ($flag === 'link-local') {
            $s['details']['tailscale0']['ipv6'][0]['ipaddr'] = 'fe80::10';
        }
        check_monitors(run_scenario($s), ['mesh4' => '192.0.2.10']);
    });
}
test('native IPv6 selection accepts GUA as well as ULA', function () {
    $s = scenario();
    $s['details']['tailscale0']['ipv6'][0]['ipaddr'] = '2001:db8::10';
    check_monitors(run_scenario($s), ['mesh4' => '192.0.2.10', 'mesh6' => '2001:db8::10']);
});
test('inactive gateways still reach native monitor selection', function () {
    $s = scenario();
    $s['details'] = [];
    foreach (['mesh4', 'mesh6'] as $name) {
        $s['gateways'][$name]['if'] = '';
    }
    $result = run_scenario($s);
    check_monitors($result, []);
    check(str_contains(implode('\n', array_column(events($result, 'log'), 1)), '[mesh4,mesh6]'));
});
foreach (['start', 'restart'] as $action) {
    foreach ([7, 23] as $code) {
        test($action . ' native error ' . $code . ' bypasses callback', function () use ($action, $code) {
            $result = run_action($action, $code);
            check($result['status'] === $code && !$result['reported'] && $result['trace'] === []);
        });
    }
    test($action . ' success runs the actual shell hook', function () use ($action) {
        check_monitors(run_action($action, 0), ['mesh4' => '192.0.2.10', 'mesh6' => 'fd00:1::10']);
    });
}
test('repeated invocations remain bounded', function () {
    foreach ([1, 2] as $unused) {
        $result = run_scenario(scenario());
        check_monitors($result, ['mesh4' => '192.0.2.10', 'mesh6' => 'fd00:1::10']);
        check(count(events($result, 'signal')) === 3 && count(events($result, 'host_route')) === 2);
    }
});
test('core initializes monitors before FreeBSD services', function () {
    $core = getenv('TAILSCALE_TEST_CORE') . '/src/etc';
    $boot = file_get_contents($core . '/rc.bootup');
    $monitor = strpos($boot, "plugins_configure('monitor'");
    $bootup = strpos($boot, "plugins_configure('bootup'");
    check($monitor !== false && $bootup !== false && $monitor < $bootup);
    $rc = file_get_contents($core . '/rc');
    $legacy = strpos($rc, '/usr/local/etc/rc.bootup');
    $syshook = strpos($rc, '/usr/local/etc/rc.syshook start');
    check($legacy !== false && $syshook !== false && $legacy < $syshook);
    check(str_contains(file_get_contents($core . '/rc.syshook.d/start/20-freebsd'), '/usr/local/etc/rc.freebsd start'));
});
test('production PHP and shell syntax and executable boot hook', function () {
    $plugin = dirname(__DIR__);
    check(
        execute([PHP_BINARY, '-l', $plugin . '/src/etc/inc/plugins.inc.d/tailscale.inc'], scenario())['status'] === 0
    );
    $hook = $plugin . '/src/etc/rc.syshook.d/start/50-tailscale';
    check(execute(['/bin/sh', '-n', $hook], scenario())['status'] === 0 && is_executable($hook));
});

echo sprintf("Runtime: PHP %s (%s)\n", PHP_VERSION, PHP_OS_FAMILY);
$failed = 0;
foreach ($tests as $name => $body) {
    try {
        $body();
        echo 'PASS ' . $name . "\n";
    } catch (Throwable $error) {
        $failed++;
        echo 'FAIL ' . $name . ': ' . $error->getMessage() . "\n";
    }
}
echo sprintf("RESULT: %d passed, %d failed, %d total\n", count($tests) - $failed, $failed, count($tests));
exit($failed ? 1 : 0);
