#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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
 * nebula-cert wrapper — model-free, pure JSON I/O.
 *
 * Usage: pki.php <subcommand> <base64-json-params>
 *
 * Subcommands:
 *   generate-ca  params: {name, curve, duration_hours, groups?, networks?, unsafe_networks?, passphrase?}
 *   sign-cert    params: {name, networks, groups?, unsafe_networks?, duration_hours?, ca_crt, ca_key, in_pub?, passphrase?}
 *   print-cert   params: {crt}
 *
 * passphrase (generate-ca/sign-cert) is passed to nebula-cert ONLY via the
 * NEBULA_CA_PASSPHRASE environment variable — never on the command line — so it
 * is not exposed in the process argument list.  On generate-ca a non-empty
 * passphrase adds -encrypt (the CA key is stored encrypted).  On sign-cert a
 * non-empty passphrase decrypts an encrypted CA key.
 *
 * Prints a JSON result to stdout; exits 0 on success, 1 on error.
 * On error: {"error": "<message>"}
 */

const NEBULA_CERT_BIN = '/usr/local/bin/nebula-cert';

/**
 * Emit a JSON error to stdout, then exit with code 0.
 *
 * Exit 0 is required so that configd's script_output action type does not
 * suppress stdout.  The caller (controller) inspects the JSON 'error' key.
 */
function fail(string $msg): never
{
    echo json_encode(['error' => $msg]) . "\n";
    exit(0);
}

/**
 * Run a shell command capturing stdout + stderr separately.
 *
 * $extraEnv lets the caller inject environment variables for the child WITHOUT
 * exposing them on the process argument list (which is world-readable via ps).
 * It is used to pass NEBULA_CA_PASSPHRASE to nebula-cert for encrypted CA keys:
 * the passphrase never appears in $cmd, only in the child's environment.  When
 * $extraEnv is empty we pass null so proc_open inherits the current environment
 * (preserving PATH etc.) exactly as before.
 *
 * Returns ['out' => string, 'err' => string, 'rc' => int].
 */
function run_cmd(string $cmd, array $extraEnv = []): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = null;
    if (!empty($extraEnv)) {
        // Merge onto the inherited environment so PATH and friends survive.
        $env = array_merge($_ENV, getenv(), $extraEnv);
    }
    $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        return ['out' => '', 'err' => 'proc_open failed', 'rc' => -1];
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    return ['out' => $out, 'err' => $err, 'rc' => $rc];
}

/**
 * Create a secure temp directory; register a shutdown cleanup so it is
 * always removed even on fatal PHP errors.  Returns the dir path.
 */
function make_tempdir(): string
{
    $dir = rtrim(shell_exec('mktemp -d'), "\n");
    if (empty($dir) || !is_dir($dir)) {
        fail('mktemp -d failed');
    }
    /* register cleanup; harmless if already removed */
    register_shutdown_function(function () use ($dir) {
        if (is_dir($dir)) {
            shell_exec('rm -rf ' . escapeshellarg($dir));
        }
    });
    return $dir;
}

/**
 * Forcibly remove a temp dir immediately (on the success path so we don't wait
 * for shutdown).
 */
function cleanup_tempdir(string $dir): void
{
    if (is_dir($dir)) {
        shell_exec('rm -rf ' . escapeshellarg($dir));
    }
}

/* -------------------------------------------------------------------------
 * Subcommand: generate-ca
 * ------------------------------------------------------------------------- */
function cmd_generate_ca(array $params): void
{
    $name            = $params['name']            ?? '';
    $curve           = $params['curve']           ?? '25519';
    $duration_hours  = $params['duration_hours']  ?? null;
    $groups          = $params['groups']          ?? '';
    $networks        = $params['networks']        ?? '';
    $unsafe_networks = $params['unsafe_networks'] ?? '';
    $passphrase      = $params['passphrase']      ?? '';

    if ($name === '') {
        fail('generate-ca: name is required');
    }

    $tmpdir  = make_tempdir();
    $crt_out = $tmpdir . '/ca.crt';
    $key_out = $tmpdir . '/ca.key';

    $cmd  = NEBULA_CERT_BIN . ' ca';
    $cmd .= ' -name '    . escapeshellarg($name);
    $cmd .= ' -curve '   . escapeshellarg($curve);
    $cmd .= ' -out-crt ' . escapeshellarg($crt_out);
    $cmd .= ' -out-key ' . escapeshellarg($key_out);

    if (!empty($duration_hours)) {
        $cmd .= ' -duration ' . escapeshellarg((string)(int)$duration_hours . 'h');
    }
    if (!empty($groups)) {
        $cmd .= ' -groups ' . escapeshellarg($groups);
    }
    if (!empty($networks)) {
        $cmd .= ' -networks ' . escapeshellarg($networks);
    }
    if (!empty($unsafe_networks)) {
        $cmd .= ' -unsafe-networks ' . escapeshellarg($unsafe_networks);
    }

    // Encrypted CA key: add -encrypt and pass the passphrase ONLY via the
    // environment (NEBULA_CA_PASSPHRASE), never on the argv (ps-visible).  The
    // resulting ca.key carries an "ENCRYPTED" PEM header.
    $extraEnv = [];
    if ($passphrase !== '') {
        $cmd .= ' -encrypt';
        $extraEnv['NEBULA_CA_PASSPHRASE'] = $passphrase;
    }

    $res = run_cmd($cmd, $extraEnv);
    if ($res['rc'] !== 0) {
        cleanup_tempdir($tmpdir);
        fail(trim($res['err']) ?: 'nebula-cert ca failed');
    }

    $crt = @file_get_contents($crt_out);
    $key = @file_get_contents($key_out);

    cleanup_tempdir($tmpdir);

    if ($crt === false || $key === false) {
        fail('generate-ca: output files missing after nebula-cert ca');
    }

    echo json_encode(['crt' => $crt, 'key' => $key]) . "\n";
}

/* -------------------------------------------------------------------------
 * Subcommand: sign-cert
 * ------------------------------------------------------------------------- */
function cmd_sign_cert(array $params): void
{
    $name            = $params['name']            ?? '';
    $networks        = $params['networks']        ?? '';
    $groups          = $params['groups']          ?? '';
    $unsafe_networks = $params['unsafe_networks'] ?? '';
    $duration_hours  = $params['duration_hours']  ?? null;
    $ca_crt          = $params['ca_crt']          ?? '';
    $ca_key          = $params['ca_key']          ?? '';
    $in_pub          = $params['in_pub']          ?? '';
    $passphrase      = $params['passphrase']      ?? '';

    if ($name === '') {
        fail('sign-cert: name is required');
    }
    if ($networks === '') {
        fail('sign-cert: networks is required');
    }
    if ($ca_crt === '' || $ca_key === '') {
        fail('sign-cert: ca_crt and ca_key are required');
    }

    $tmpdir      = make_tempdir();
    $ca_crt_file = $tmpdir . '/ca.crt';
    $ca_key_file = $tmpdir . '/ca.key';
    $crt_out     = $tmpdir . '/host.crt';

    if (file_put_contents($ca_crt_file, $ca_crt) === false) {
        cleanup_tempdir($tmpdir);
        fail('sign-cert: failed to write ca_crt to temp file');
    }
    if (file_put_contents($ca_key_file, $ca_key) === false) {
        cleanup_tempdir($tmpdir);
        fail('sign-cert: failed to write ca_key to temp file');
    }

    $cmd  = NEBULA_CERT_BIN . ' sign';
    $cmd .= ' -ca-crt '  . escapeshellarg($ca_crt_file);
    $cmd .= ' -ca-key '  . escapeshellarg($ca_key_file);
    $cmd .= ' -name '    . escapeshellarg($name);
    $cmd .= ' -networks ' . escapeshellarg($networks);
    $cmd .= ' -out-crt ' . escapeshellarg($crt_out);

    // CSR mode: sign a node-supplied public key; no -out-key (node keeps its key).
    if ($in_pub !== '') {
        $pub_file = $tmpdir . '/host.pub';
        if (file_put_contents($pub_file, $in_pub) === false) {
            cleanup_tempdir($tmpdir);
            fail('sign-cert: failed to write in_pub to temp file');
        }
        $cmd .= ' -in-pub ' . escapeshellarg($pub_file);
    } else {
        // Generate-here mode: nebula-cert writes a fresh keypair.
        $key_out  = $tmpdir . '/host.key';
        $cmd     .= ' -out-key ' . escapeshellarg($key_out);
    }

    if (!empty($duration_hours)) {
        $cmd .= ' -duration ' . escapeshellarg((string)(int)$duration_hours . 'h');
    }
    if (!empty($groups)) {
        $cmd .= ' -groups ' . escapeshellarg($groups);
    }
    if (!empty($unsafe_networks)) {
        $cmd .= ' -unsafe-networks ' . escapeshellarg($unsafe_networks);
    }

    // When the CA key is encrypted, nebula-cert decrypts it with the passphrase
    // read from NEBULA_CA_PASSPHRASE.  We pass it ONLY via the environment (never
    // argv) so it is not exposed in the process list.  A wrong/missing passphrase
    // for an encrypted key surfaces as a non-zero rc with a stderr message like
    // "invalid passphrase or corrupt private key", which we propagate to the
    // caller via fail() below.
    $extraEnv = [];
    if ($passphrase !== '') {
        $extraEnv['NEBULA_CA_PASSPHRASE'] = $passphrase;
    }

    $res = run_cmd($cmd, $extraEnv);
    if ($res['rc'] !== 0) {
        cleanup_tempdir($tmpdir);
        fail(trim($res['err']) ?: 'nebula-cert sign failed');
    }

    $crt = @file_get_contents($crt_out);
    if ($crt === false) {
        cleanup_tempdir($tmpdir);
        fail('sign-cert: crt output file missing after nebula-cert sign');
    }

    if ($in_pub !== '') {
        // CSR mode: no private key is produced or stored.
        cleanup_tempdir($tmpdir);
        echo json_encode(['crt' => $crt, 'key' => '']) . "\n";
    } else {
        $key = @file_get_contents($key_out);
        cleanup_tempdir($tmpdir);
        if ($key === false) {
            fail('sign-cert: key output file missing after nebula-cert sign');
        }
        echo json_encode(['crt' => $crt, 'key' => $key]) . "\n";
    }
}

/* -------------------------------------------------------------------------
 * Subcommand: print-cert
 * ------------------------------------------------------------------------- */
function cmd_print_cert(array $params): void
{
    $crt = $params['crt'] ?? '';

    if ($crt === '') {
        fail('print-cert: crt is required');
    }

    $tmpdir   = make_tempdir();
    $crt_file = $tmpdir . '/cert.crt';

    if (file_put_contents($crt_file, $crt) === false) {
        cleanup_tempdir($tmpdir);
        fail('print-cert: failed to write crt to temp file');
    }

    $cmd = NEBULA_CERT_BIN . ' print -path ' . escapeshellarg($crt_file) . ' -json';
    $res = run_cmd($cmd);

    cleanup_tempdir($tmpdir);

    if ($res['rc'] !== 0) {
        fail(trim($res['err']) ?: 'nebula-cert print failed');
    }

    $decoded = json_decode(trim($res['out']), true);
    if ($decoded === null) {
        fail('print-cert: nebula-cert print returned non-JSON: ' . trim($res['out']));
    }

    echo json_encode(['info' => $decoded]) . "\n";
}

/* -------------------------------------------------------------------------
 * Entry point
 * ------------------------------------------------------------------------- */

if ($argc < 3) {
    fail('Usage: pki.php <subcommand> <base64-json-params>');
}

$subcommand  = $argv[1];
$params_b64  = $argv[2];
$params_json = base64_decode($params_b64, true);

if ($params_json === false) {
    fail('params: base64 decode failed');
}

$params = json_decode($params_json, true);
if ($params === null) {
    fail('params: JSON decode failed: ' . json_last_error_msg());
}

switch ($subcommand) {
    case 'generate-ca':
        cmd_generate_ca($params);
        break;
    case 'sign-cert':
        cmd_sign_cert($params);
        break;
    case 'print-cert':
        cmd_print_cert($params);
        break;
    default:
        fail("unknown subcommand: {$subcommand}");
}
