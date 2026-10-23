<?php

/*
 * Copyright (c) 2026 Emanuele Baglini
 * SPDX-License-Identifier: BSD-2-Clause
 */
/* Regression suite. All host operations are intercepted in scenario.php. */
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
    return array_values(array_map(
        static fn($row) => $row[1],
        array_filter($result['trace'], static fn($row) => $row[0] === $kind)
    ));
}
function queues($r)
{
    return events($r, 'queue');
}
function gateway($name, $interface, $device, $family, $peer)
{
    return ['name' => $name, 'interface' => $interface, 'if' => $device, 'ipprotocol' => $family,
        'gateway' => $peer, 'monitor' => $peer, 'monitor_disable' => '0', 'monitor_noroute' => '0',
        'current_interval' => '1', 'current_loss_interval' => '4', 'current_time_period' => '60',
        'current_data_length' => '1'];
}
function scenario($logical = 'opt11')
{
    return [
        'interfaces' => [
            $logical => ['if' => 'tailscale0', 'enable' => '1'],
            'wan' => ['if' => 'em0', 'enable' => '1'],
            'opt2' => ['if' => 'wg_test', 'enable' => '1']
        ],
        'details' => [
            'tailscale0' => ['ipv4' => [['ipaddr' => '192.0.2.10', 'subnetbits' => 32]],
                'ipv6' => [['ipaddr' => 'fd00:1::10', 'subnetbits' => 128, 'vltime' => '0']]],
            'em0' => ['ipv4' => [['ipaddr' => '198.51.100.2', 'subnetbits' => 24]], 'ipv6' => []],
            'wg_test' => ['ipv4' => [['ipaddr' => '203.0.113.10', 'subnetbits' => 32]], 'ipv6' => []],
        ],
        'gateways' => [
            'mesh4' => gateway('mesh4', $logical, 'tailscale0', 'inet', '192.0.2.20'),
            'mesh6' => gateway('mesh6', $logical, 'tailscale0', 'inet6', 'fd00:1::20'),
            'wan4' => gateway('wan4', 'wan', 'em0', 'inet', '198.51.100.1'),
            'wg4' => gateway('wg4', 'opt2', 'wg_test', 'inet', '203.0.113.20'),
        ],
    ];
}
function family_only($version)
{
    $s = scenario();
    unset($s['gateways'][$version === 4 ? 'mesh6' : 'mesh4']);
    return $s;
}
function execute($cmd, $s, $extra = [], $allow_stderr = false)
{
    $pipes = [];
    $process = proc_open(
        $cmd,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), ['TAILSCALE_TEST_SCENARIO' => json_encode($s, JSON_THROW_ON_ERROR)], $extra)
    );
    check(is_resource($process), 'could not start child');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    check($allow_stderr || $stderr === '', 'unexpected PHP/shell error: ' . $stderr);
    $r = ['status' => $status, 'trace' => [], 'result' => null, 'stderr' => $stderr];
    if (preg_match('/RESULT:(.+)\n?$/', trim($stdout), $matches)) {
        $r = array_merge($r, json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR));
    }
    return $r;
}
function run_scenario($s)
{
    return execute([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/scenario.php'], $s);
}
function run_action($action, $s, $native_code)
{
    $plugin = dirname(__DIR__);
    $actions = file_get_contents($plugin . '/src/opnsense/service/conf/actions.d/actions_tailscale.conf');
    check(preg_match('/^\\[' . $action . '\\]\\ncommand:([^\\n]+)/m', $actions, $matches) === 1, 'action not found');
    $native = tempnam('/tmp', 'tailscale-native-');
    file_put_contents($native, '#!/bin/sh' . "\n" . 'exit "$NATIVE_CODE"' . "\n");
    chmod($native, 0700);
    $command = str_replace(
        ['/usr/local/etc/rc.d/tailscaled', '/usr/local/etc/rc.syshook.d/start/50-tailscale'],
        [
            '/bin/sh ' . escapeshellarg($native),
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/scenario.php')
        ],
        $matches[1]
    );
    $s['mode'] = 'hook';
    try {
        return execute(['/bin/sh', '-c', $command], $s, ['NATIVE_CODE' => (string)$native_code]);
    } finally {
        unlink($native);
    }
}
function started_monitors($r)
{
    return array_values(array_map(
        static fn($e) => [$e[1][0], $e[1][1]],
        array_filter(
            events($r, 'background_command'),
            static fn($e) => is_array($e[0]) && str_starts_with($e[0][0], '/usr/local/bin/dpinger')
        )
    ));
}
function monitor_signals($r)
{
    return array_values(array_filter(
        events($r, 'signal'),
        static fn($e) => str_starts_with($e[0], '/var/run/dpinger_')
    ));
}

test('disabled plugin is quiet', function () {
    $s = scenario();
    $s['enabled'] = '0';
    $r = run_scenario($s);
    check($r['result'] === true && $r['trace'] === []);
});
test('unassigned device is quiet and never assigned', function () {
    $s = scenario();
    unset($s['interfaces']['opt11']);
    $r = run_scenario($s);
    check($r['result'] === true && queues($r) === [] && events($r, 'log') === []);
});
test('disabled assignment is quiet', function () {
    $s = scenario();
    unset($s['interfaces']['opt11']['enable']);
    $r = run_scenario($s);
    check($r['result'] === true && $r['trace'] === []);
});
foreach (['opt3', 'opt99'] as $logical) {
    test('logical assignment ' . $logical, function () use ($logical) {
        $r = run_scenario(scenario($logical));
        check($r['result'] === true && count(queues($r)) === 2);
    });
}
test('ready dual-stack uses escaped detached force requests', function () {
    $r = run_scenario(scenario());
    check(queues($r) === [
        ['interface newip', ['tailscale0', 'force'], true],
        ['interface newipv6', ['tailscale0', 'force'], true]
    ]);
    check(events($r, 'gateway_selection') === [[false, false, true]]);
    check(!str_contains(json_encode(events($r, 'log')), 'restored'));
});
foreach ([4, 6] as $version) {
    test('IPv' . $version . ' alone', function () use ($version) {
        $r = run_scenario(family_only($version));
        check($r['result'] === true && count(queues($r)) === 1);
        check(queues($r)[0][0] === ($version === 4 ? 'interface newip' : 'interface newipv6'));
    });
    test('missing IPv' . $version . ' fails explicitly', function () use ($version) {
        $s = family_only($version);
        $s['details']['tailscale0'][$version === 4 ? 'ipv4' : 'ipv6'] = [];
        $r = run_scenario($s);
        check($r['result'] === false && queues($r) === []);
        check(str_contains(json_encode(events($r, 'log')), 'no usable IPv' . $version));
    });
    test('partial readiness missing IPv' . $version, function () use ($version) {
        $s = scenario();
        $s['details']['tailscale0'][$version === 4 ? 'ipv4' : 'ipv6'] = [];
        $r = run_scenario($s);
        check($r['result'] === false && count(queues($r)) === 1);
        check(queues($r)[0][0] === ($version === 4 ? 'interface newipv6' : 'interface newip'));
    });
}
test('absent device does not hide inactive configured gateways', function () {
    $s = scenario();
    $s['details'] = [];
    $s['gateways']['mesh4']['if'] = '';
    $s['gateways']['mesh6']['if'] = '';
    $r = run_scenario($s);
    check($r['result'] === false && queues($r) === [] && count(events($r, 'log')) === 2);
});
test('no Tailscale gateways means no network work', function () {
    $s = scenario();
    unset($s['gateways']['mesh4'], $s['gateways']['mesh6']);
    $r = run_scenario($s);
    check($r['result'] === true && queues($r) === [] && events($r, 'address_read') === []);
});
foreach (['disabled', 'monitor_disable'] as $flag) {
    test($flag . ' gateways remain disabled', function () use ($flag) {
        $s = scenario();
        $s['gateways']['mesh4'][$flag] = '1';
        $s['gateways']['mesh6'][$flag] = '1';
        $r = run_scenario($s);
        check($r['result'] === true && queues($r) === [] && events($r, 'address_read') === []);
    });
}
test('multiple same-family gateways queue only once', function () {
    $s = family_only(4);
    $s['gateways']['mesh4b'] = gateway('mesh4b', 'opt11', 'tailscale0', 'inet', '192.0.2.21');
    check(count(queues(run_scenario($s))) === 1);
});
test('IPv6 globally disabled does not demand an IPv6 address', function () {
    $s = scenario();
    $s['ipv6_allowed'] = false;
    $s['details']['tailscale0']['ipv6'] = [];
    $r = run_scenario($s);
    check($r['result'] === true && count(queues($r)) === 1 && count(events($r, 'log')) === 1);
});
foreach (['tentative', 'detached', 'deprecated', 'link-local'] as $flag) {
    test('unusable IPv6 ' . $flag, function () use ($flag) {
        $s = family_only(6);
        $s['details']['tailscale0']['ipv6'][0][$flag] = true;
        if ($flag === 'link-local') {
            $s['details']['tailscale0']['ipv6'][0]['ipaddr'] = 'fe80::10';
        }
        $r = run_scenario($s);
        check($r['result'] === false && queues($r) === []);
    });
}
test('prefix-only assignment cannot substitute link-local readiness', function () {
    $s = family_only(6);
    $s['prefix_only'] = true;
    $s['details']['tailscale0']['ipv6'] = [[
        'ipaddr' => 'fe80::10', 'subnetbits' => 128, 'vltime' => '0', 'link-local' => true
    ]];
    $r = run_scenario($s);
    check($r['result'] === false && queues($r) === []);
});
test('GUA IPv6 is accepted without hardcoded Tailscale ranges', function () {
    $s = family_only(6);
    $s['details']['tailscale0']['ipv6'][0]['ipaddr'] = '2001:db8::10';
    check(run_scenario($s)['result'] === true);
});
foreach (['', 'Execute error', 'OK', 'not-a-request-id', 'throw'] as $reply) {
    test('queue failure ' . ($reply ?: 'empty'), function () use ($reply) {
        $s = family_only(4);
        $s['queue']['interface newip'] = $reply;
        $r = run_scenario($s);
        check($r['result'] === false && count(queues($r)) === 1);
        $log = json_encode(events($r, 'log'));
        check(str_contains($log, 'failed to queue') && !str_contains($log, 'synthetic-private-value'));
    });
}
test('one queue failure preserves other-family submission', function () {
    $s = scenario();
    $s['queue']['interface newip'] = '';
    $r = run_scenario($s);
    check($r['result'] === false && count(queues($r)) === 2 && count(events($r, 'log')) === 2);
});
test('repeated calls are bounded without recursion or daemon commands', function () {
    $s = scenario();
    $s['repeat'] = 3;
    $r = run_scenario($s);
    check($r['result'] === true && count(queues($r)) === 6);
    check(events($r, 'backend') === [] && events($r, 'command') === [] && events($r, 'dad_wait') === []);
});
test('remote peer state is not a readiness input', function () {
    $s = scenario();
    $s['statuses'] = ['mesh4' => ['status' => 'down'], 'mesh6' => ['status' => 'down']];
    check(run_scenario($s)['result'] === true);
});
foreach (['start', 'restart'] as $action) {
    foreach ([7, 23] as $code) {
        test($action . ' native error ' . $code . ' is preserved', function () use ($action, $code) {
            $r = run_action($action, scenario(), $code);
            check($r['status'] === $code && $r['trace'] === []);
        });
    }
    test($action . ' native success runs the actual hook', function () use ($action) {
        $r = run_action($action, scenario(), 0);
        check($r['status'] === 0 && count(queues($r)) === 2);
    });
    test($action . ' successful daemon but missing address fails integration', function () use ($action) {
        $s = scenario();
        $s['details'] = [];
        $r = run_action($action, $s, 0);
        check($r['status'] === 1 && queues($r) === [] && count(events($r, 'log')) === 2);
        check(!str_contains(json_encode(events($r, 'log')), 'stopped'));
    });
}
test('boot hook quiet no-op exits zero', function () {
    $s = scenario();
    $s['enabled'] = '0';
    $s['mode'] = 'hook';
    $r = run_scenario($s);
    check($r['status'] === 0 && $r['trace'] === []);
});
test('hook queue failure exits nonzero', function () {
    $s = family_only(4);
    $s['mode'] = 'hook';
    $s['queue']['interface newip'] = '';
    check(run_scenario($s)['status'] === 1);
});
foreach (
    [
        'newip' => ['inet', 'mesh4', '192.0.2.10'],
        'newipv6' => ['inet6', 'mesh6', 'fd00:1::10']
    ] as $mode => $expected
) {
    test('original core ' . $mode . ' selects only its family and source', function () use ($mode, $expected) {
        $s = scenario();
        $s['mode'] = $mode;
        $s['oldip'] = '192.0.2.10';
        $r = run_scenario($s);
        check($r['status'] === 0);
        check(started_monitors($r) === [[$expected[1], $expected[2]]], 'wrong monitor/source');
        check(
            monitor_signals($r) === [['/var/run/dpinger_' . $expected[1] . '.pid', null]],
            'unrelated monitor signalled'
        );
        check(count(events($r, 'firewall_reload')) === 1 && count(events($r, 'resolver')) === 1);
        check(in_array(['wireguard configure', false], events($r, 'backend')), 'VPN hook not exercised');
        check(count(queues($r)) === 0, 'recursive Tailscale notification');
        check(count(events($r, 'dad_wait')) === ($mode === 'newipv6' ? 1 : 0));
    });
    test('original core ' . $mode . ' respects monitor_disable', function () use ($mode, $expected) {
        $s = scenario();
        $s['mode'] = $mode;
        $s['gateways'][$expected[1]]['monitor_disable'] = '1';
        $r = run_scenario($s);
        check(started_monitors($r) === []);
    });
    test('original core ' . $mode . ' no matching gateways never reloads all monitors', function () use ($mode) {
        $s = scenario();
        $s['mode'] = $mode;
        unset($s['gateways']['mesh4'], $s['gateways']['mesh6']);
        $r = run_scenario($s);
        check(started_monitors($r) === [] && monitor_signals($r) === [] && events($r, 'signal') === []);
    });
}
test('original IPv4 forced renewal survives unchanged cache', function () {
    $s = scenario();
    $s['mode'] = 'newip';
    $s['oldip'] = '192.0.2.10';
    $s['oldgw'] = '192.0.2.20';
    $s['router'] = '192.0.2.20';
    $r = run_scenario($s);
    check(count(started_monitors($r)) === 1 && $r['cache_ip'] === '192.0.2.10' && $r['cache_gw'] === '192.0.2.20');
    check(events($r, 'command') === [["/usr/bin/wg syncconf %s %s", ['wg_test', '/tmp/synthetic-wg.conf']]]);
});
test('original IPv4 unchanged cache without force skips reconfiguration', function () {
    $s = scenario();
    $s['mode'] = 'newip';
    $s['oldip'] = '192.0.2.10';
    $s['force'] = '';
    $r = run_scenario($s);
    check(started_monitors($r) === [] && events($r, 'firewall_reload') === []);
});
test('original IPv4 changed address kills only its old-address states', function () {
    $s = scenario();
    $s['mode'] = 'newip';
    $s['oldip'] = '192.0.2.9';
    $r = run_scenario($s);
    $kills = array_values(array_filter(events($r, 'command'), static fn($e) => str_contains($e[0], 'pfctl -k')));
    check($kills === [['/sbin/pfctl -k 0.0.0.0/0 -k %s', '192.0.2.9'], ['/sbin/pfctl -k %s', '192.0.2.9']]);
});
test('core monitor empty array is a no-op', function () {
    $s = scenario();
    $s['mode'] = 'empty_monitors';
    $r = run_scenario($s);
    check(events($r, 'background_command') === [] && events($r, 'signal') === []);
});
test('core routing retains down-gateway input for default switching', function () {
    $s = scenario();
    $s['mode'] = 'routing';
    $s['system']['gw_switch_default'] = '1';
    $s['statuses'] = ['mesh4' => ['status' => 'down']];
    $s['default_gateway'] = $s['gateways']['wan4'];
    $r = run_scenario($s);
    check(events($r, 'default_lookup') === [[['mesh4'], 'inet']]);
    check(events($r, 'default_route')[0][0]['name'] === 'wan4');
});
test('original boot order separates legacy bootup and syshook services', function () {
    $core = getenv('TAILSCALE_TEST_CORE') . '/src/etc';
    $rc = file_get_contents($core . '/rc');
    $boot = file_get_contents($core . '/rc.bootup');
    $monitor = strpos($boot, "plugins_configure('monitor'");
    $bootup = strpos($boot, "plugins_configure('bootup'");
    check($monitor !== false && $bootup !== false && $monitor < $bootup, 'monitor/bootup order missing or changed');
    $legacy = strpos($rc, '/usr/local/etc/rc.bootup');
    $syshook = strpos($rc, '/usr/local/etc/rc.syshook start');
    check($legacy !== false && $syshook !== false && $legacy < $syshook, 'legacy/syshook order missing or changed');
    check(str_contains(file_get_contents($core . '/rc.syshook.d/start/20-freebsd'), '/usr/local/etc/rc.freebsd start'));
    check(str_contains(file_get_contents($core . '/rc.freebsd'), '${rc_filename} ${1}'));
});

foreach ([true, false] as $detach) {
    test('actual Backend transport detach=' . ($detach ? 'true' : 'false'), function () use ($detach) {
        require_once getenv('TAILSCALE_TEST_CORE') . '/src/opnsense/mvc/app/library/OPNsense/Core/Backend.php';
        $socket = '/tmp/tailscale-configd-' . bin2hex(random_bytes(6)) . '.sock';
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/configd_server.php', $socket],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        check(is_resource($process));
        try {
            check(trim(fgets($pipes[1])) === 'READY');
            $backend = new \OPNsense\Core\Backend();
            $property = new ReflectionProperty($backend, 'configdSocket');
            $property->setValue($backend, $socket);
            $reply = $backend->configdpRun('interface newip', ['tailscale0', 'force'], $detach);
            check(trim($reply) === '11111111-2222-4333-8444-555555555555');
            $event = base64_decode(substr(trim(fgets($pipes[1])), 6), true);
            check($event === ($detach ? '&' : '') . "interface newip 'tailscale0' 'force'");
            check(proc_get_status($process)['running'], 'request must return before simulated action completion');
        } finally {
            fwrite($pipes[0], "complete\n");
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            check(proc_close($process) === 0 && $stderr === '', 'transport server failed');
        }
    });
}
test('production PHP syntax and executable hook', function () {
    $plugin = dirname(__DIR__);
    foreach (['/src/etc/inc/plugins.inc.d/tailscale.inc', '/src/etc/rc.syshook.d/start/50-tailscale'] as $path) {
        $r = execute([PHP_BINARY, '-l', $plugin . $path], scenario());
        check($r['status'] === 0);
    }
    check(is_executable($plugin . '/src/etc/rc.syshook.d/start/50-tailscale'));
});

echo sprintf("Runtime: PHP %s (%s)\n", PHP_VERSION, PHP_OS_FAMILY);
$failed = 0;
foreach ($tests as $name => $body) {
    try {
        $body();
        echo 'PASS ' . $name . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo 'FAIL ' . $name . ': ' . $e->getMessage() . "\n";
    }
}
echo sprintf("RESULT: %d passed, %d failed, %d total\n", count($tests) - $failed, $failed, count($tests));
exit($failed ? 1 : 0);
