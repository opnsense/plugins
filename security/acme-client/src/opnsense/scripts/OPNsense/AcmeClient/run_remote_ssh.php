#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2022 Juergen Kellerer
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

const ABOUT = <<<TXT

   This script implements remote command execution using SSH. It reuses
   identities and "known_hosts" management from SFTP, located inside the
   local configuration folder (see "upload_sftp.php" for details).

   Primary purpose is to support the creation of automation tasks that trigger
   actions after a certificate has been uploaded (requires that actions can
   have an execution order).

   In addition to automations, all operations can also be triggered manually
   using simple CLI commands.

   See: EXAMPLES & actions_acmeclient.conf

TXT;

// Commands & help
const COMMANDS = [
    "run-command" => [
        "description" => "runs the a command on the specified target host",
        "options" => [
            "host::", "port::", "host-key::", "user::", "identity-type::", "run::"],
        "implementation" => "commandRunRemote",
        "default" => true,
    ],

    "test-connection" => [
        "description" => "connects to the host and returns results as JSON",
        "options" => ["host:", "port::", "host-key::", "user:", "identity-type::"],
        "implementation" => "commandTestConnection",
    ],

    "show-identity" => [
        "description" => "prints the ssh client identity (publickey)",
        "options" => ["identity-type::", "source-ip::", "host::", "unrestricted"],
        "implementation" => "commandShowIdentity",
    ],
];

const EXAMPLES = <<<TXT
- Show the public key used to communicate with the SSH server
  ./run_remote_ssh.php --log --identity-type=ecdsa show-identity

- Test connectivity with host
  ./run_remote_ssh.php --log --host=sshpserver --user=name test-connection

- Run a command at the specific server
  ./run_remote_ssh.php --log --host=sshserver --user=name --run='/bin/sh -c "pwd && ls -la"'

- Load settings from automation with ID and run the command
  ./run_remote_ssh.php --log --automation-id=ID
TXT;

// Connection test
const CONNECTION_TEST_RESULT = 'OpnSense_ACME_SSH_Connected';
const CONNECTION_TEST_COMMAND = 'echo "' . CONNECTION_TEST_RESULT . '"';

const CONNECTION_EXECUTE_TIMEOUT = 60 * 7; // Max seconds that a command may run

// Exit codes
const EXITCODE_SUCCESS = 0;
const EXITCODE_ERROR = 1;
const EXITCODE_ERROR_UNKNOWN_COMMAND = 254;

// Optional imports
@include_once("config.inc");
@include_once("certs.inc");
@include_once("util.inc");

// Optional autoloader (for local dev environment)
if (!function_exists("log_error")) {
    spl_autoload_register(function ($class_name) {
        require_once(__DIR__ . "/../../../mvc/app/library/" . str_replace("\\", "/", $class_name) . ".php");
    });
}

// Importing classes
use OPNsense\AcmeClient\Process;
use OPNsense\AcmeClient\SSHKeys;
use OPNsense\AcmeClient\Utils;

// Implementing logic
function commandShowIdentity(array &$options): int
{
    $identity_type = trim(($options["identity-type"] ?? "")) ?: SSHKeys::DEFAULT_IDENTITY_TYPE;
    $source_ip = trim(($options["source-ip"] ?? ""));
    $host = trim(($options["host"] ?? ""));

    $keys = new SSHKeys(configPath());
    if (($id_file = $keys->getIdentity($identity_type)) && is_readable($id_file)) {
        if (
            !isset($options["unrestricted"])
            && ($restrictions = SSHKeys::getIdentityRestrictions($host, $source_ip, ""))
        ) {
            echo "$restrictions ";
        }

        echo file_get_contents($id_file);
        return EXITCODE_SUCCESS;
    } else {
        Utils::log()->error("Failed getting identity. See log output for details.");
    }
    return EXITCODE_ERROR;
}

function commandTestConnection(array &$options): int
{
    $result = ["actions" => ["connecting"], "success" => false];

    $options["run"] = CONNECTION_TEST_COMMAND;
    $lines = runRemoteCommand($options, $error);

    if (!$error) {
        $result["actions"][] = "connected";
        if (($result["success"] = in_array(CONNECTION_TEST_RESULT, $lines))) {
            $result["actions"][] = "echo-tested";
        }
    } else {
        $result = array_merge($result, ($error ?: []));
    }

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

    return $result["success"] ? EXITCODE_SUCCESS : EXITCODE_ERROR;
}

function commandRunRemote(array &$options): int
{
    if (empty($options["run"])) {
        Utils::log()->error("SSH: Command is empty, nothing to do.");
        return EXITCODE_ERROR;
    }

    $lines = runRemoteCommand($options, $error);
    if (!$error) {
        $host = $options["host"] . (($port = ($options["port"] ?? false)) ? ":$port" : "");
        Utils::log()->info("SSH [$host]> {$options["run"]}:" . PHP_EOL . join(PHP_EOL, $lines));
        return EXITCODE_SUCCESS;
    }

    return EXITCODE_ERROR;
}

function runRemoteCommand(array $options, &$error): ?array
{
    static $expected_errors = [
        ["host_not_resolved", /*   -> */ '/.*not resolve.*/i'],
        ["host_not_trusted", /*    -> */ '/.*IDENTIFICATION HAS CHANGED.*/i'],
        ["connection_refused", /*  -> */ '/.*connection refused.*/i'],
        ["connection_closed", /*   -> */ '/.*connection closed.*/i'],
        ["network_timeout", /*     -> */ '/.*timed out.*/i'],
        ["network_unreachable", /* -> */ '/.*network.+unreachable.*/i'],
        ["permission_denied", /*   -> */ '/.*permission denied.*/i'],
        ["failure", /*             -> */ '/.*(error|failure|you must supply).*/i'],
    ];

    $ssh_keys = new SSHKeys(configPath());

    $identity_type = trim(($options["identity-type"] ?? ""));
    $host = trim(($options["host"] ?? ""));
    $host_key = ($options["host-key"] ?? "");
    $port = $options["port"] ?? 22;
    $username = $options["user"] ?? false;
    $command = $options["run"] ?? "";

    list($ok, $cmd) = buildSSHArguments($ssh_keys, $host, $username, $identity_type, $host_key, $port);
    if ($ok) {
        if (empty($command)) {
            $error = ["no_command" => true];
        } else {
            $cmd[] = $command;
        }
    } else {
        $error = $cmd;
        $error["connect_failed"] = true;
        return null;
    }

    $result = [];
    $exit_code = null;
    $expected_error = null;

    if ($process = Process::open($cmd)) {
        $process->closeInput();

        $lines = 0;
        $start = time();
        $mustClose = fn($lines) => (time() - $start) > CONNECTION_EXECUTE_TIMEOUT || $lines > 10000;

        while ($process->isRunning() && !$mustClose($lines)) {
            for (; ($line = $process->get()) !== false && !$mustClose($lines); $lines++) {
                if (!$expected_error) {
                    foreach ($expected_errors as $ee) {
                        if (preg_match($ee[1], $line)) {
                            if ($ee[0] !== "connection_closed") {
                                $expected_error = [$ee[0] => true, "error" => trim($line)];
                            }
                            break;
                        }
                    }
                }
                $result[] = $line;
            }
        }
        $exit_code = $process->close();
        $ok = $exit_code === 0;
    } else {
        $ok = false;
    }

    if (!$ok) {
        $cl = join(" ", array_map(fn($v) => escapeshellarg($v), $cmd));
        $error = array_merge(($expected_error ?? []), [
            "result" => $result,
            "exit_code" => $exit_code
        ]);
        $error["connect_failed"] = $exit_code == 255;
        Utils::log()->error("SSH failed with '$exit_code': $cl", $error);
    }

    return $result;
}

function buildSSHArguments(SSHKeys $ssh_keys, $host, $username, $identity_type = "", $host_key = "", $port = SSHKeys::DEFAULT_PORT): array
{
    if (empty(trim($host)) || empty(trim($username))) {
        Utils::log()->error("Failed connecting to '$host'. Hostname or username is missing.");
        return [false, ["invalid_parameters" => true]];
    }

    if (empty($identity_type)) {
        $identity_type = SSHKeys::DEFAULT_IDENTITY_TYPE;
    }

    $trust = $ssh_keys->trustHost($host, $host_key, $port);
    if ($trust["ok"] !== true) {
        Utils::log()->error("Failed establishing trust in '$host'; Cause: {$trust["error"]}");
        unset($trust["ok"]);
        return [false, array_merge($trust, ["host_not_trusted" => true])];
    } else {
        $host = $trust["host"];
    }

    // Building ssh command.
    $cmd = [
        "ssh",
        "-p", $port,
        "-oUser=$username",
        "-oUserKnownHostsFile={$ssh_keys->knownHostsFile()}",
    ];

    // Handle client side identity
    $identity = $ssh_keys->getIdentity($identity_type, true);
    if (is_file($identity) && is_readable($identity)) {
        array_push(
            $cmd,
            "-i",
            $identity,
            "-oPreferredAuthentications=publickey"
        );
    } else {
        Utils::log()->error("Failed adding client identity ($identity). Connect will likely fail.");
    }

    // Adding the host
    $cmd[] = "$host";

    return [true, $cmd];
}

function help()
{
    Utils::printCLIHelp(ABOUT, EXAMPLES, COMMANDS);
}

function getOptionsById($automation_id)
{
    Utils::log()->info("Reading options from automation: $automation_id");

    if (is_object($action = Utils::getAutomationActionById($automation_id))) {
        if ($action->enabled && "configd_remote_ssh" === (string)$action->type) {
            return [
                "host" => trim((string)$action->remote_ssh_host),
                "host-key" => trim((string)$action->remote_ssh_host_key),
                "port" => trim((string)$action->remote_ssh_port),
                "identity-type" => trim((string)$action->remote_ssh_identity_type),
                "user" => trim((string)$action->remote_ssh_user),
                "run" => trim((string)$action->remote_ssh_command),
            ];
        } else {
            Utils::log()->error("Ignoring disabled or invalid automation '$automation_id'");
        }
    } else {
        Utils::log()->error("No upload automation found with uuid = '$automation_id'");
    }

    return false;
}

function configPath(): string
{
    if (($path = Utils::configPath())) {
        return $path . DIRECTORY_SEPARATOR . "sftp-config"; // shared with sftp to have the same identities
    }
    die("Failed detecting config path");
}

function requireThat($expression, $message)
{
    try {
        Utils::requireThat($message, $message);
    } catch (\AssertionError $e) {
        exit(EXITCODE_ERROR);
    }
    return $expression;
}

// Running the main script
Utils::runCLIMain(
    "help",
    "getOptionsById",
    COMMANDS,
    EXITCODE_SUCCESS,
    EXITCODE_ERROR_UNKNOWN_COMMAND
);
