<?php

/*
 * Copyright (C) 2019 Juergen Kellerer
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

namespace OPNsense\AcmeClient;

/**
 * Wrapper around the 'sftp' commandline client.
 * @package OPNsense\AcmeClient
 */
class SftpClient
{
    private const CONNECT_REPLY_TIMEOUT = 120;
    private const COMMAND_REPLY_TIMEOUT = 30;

    private $connection_info = [];
    private $identity_type;

    /* @var false|array */
    private $failed_status;
    /* @var SSHKeys */
    private $ssh_keys;
    /* @var null|Process */
    private $process = null;
    /* @var null|string */
    private $pwd = null;

    public function __construct($config_path, $identity_type = SSHKeys::DEFAULT_IDENTITY_TYPE)
    {
        $this->ssh_keys = new SSHKeys($config_path);
        $this->identity_type = $identity_type;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function connected(): ?array
    {
        return $this->process && $this->process->isRunning()
            ? $this->connection_info
            : null;
    }

    public function connect($host, $username, $host_key = "", $port = SSHKeys::DEFAULT_PORT)
    {
        if (empty(trim($host)) || empty(trim($username))) {
            $this->failed_status = ["invalid_parameters" => true];
            Utils::log()->error("Failed connecting to '$host'. Hostname or username is missing.");
            return false;
        }

        $trust = $this->ssh_keys->trustHost($host, $host_key, $port);
        if ($trust["ok"] !== true) {
            Utils::log()->error("Failed establishing trust in '$host'; Cause: {$trust["error"]}");
            unset($trust["ok"]);
            $this->failed_status = array_merge($trust, ["host_not_trusted" => true]);
            return false;
        } else {
            $host = $trust["host"];
        }

        // Building sftp command.
        $cmd = [
            "sftp",
            "-P", $port,
            "-oUser=$username",
            "-oUserKnownHostsFile={$this->ssh_keys->knownHostsFile()}",
        ];

        // Handle client side identity
        $identity = $this->ssh_keys->getIdentity($this->identity_type, true);
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
        array_push($cmd, "$host");

        // Creating the sftp process
        if ($this->process = Process::open($cmd)) {
            $this->processAvailableInput(self::CONNECT_REPLY_TIMEOUT, 1, null, 0.75);
            if (($error = $this->lastError()) || !$this->process->isRunning()) {
                Utils::log()->error("Failed connecting to '$host' (user: '$username')", $error);
                return false;
            }
            $this->connection_info = ["host" => $host, "port" => $port, "user" => $username];
            return true;
        }
        return false;
    }

    private function processAvailableInput(float $timeout = 0, $expected_lines = 0, callable $lines_consumer = null, $remaining_timeout = 0)
    {
        Utils::requireThat($this->process !== null, "SFTP: process not connected");

        // Error list matching output from "sftp". Keep names in sync with similar list in SSHKeys.
        static $expected_errors = [
            ["host_not_resolved", /*   -> */ '/.*not resolve.*/i'],
            ["host_not_trusted", /*    -> */ '/.*IDENTIFICATION HAS CHANGED.*/i'],
            ["connection_refused", /*  -> */ '/.*connection refused.*/i'],
            ["connection_closed", /*   -> */ '/.*connection closed.*/i'],
            ["network_timeout", /*     -> */ '/.*timed out.*/i'],
            ["network_unreachable", /* -> */ '/.*network.+unreachable.*/i'],
            ["permission_denied", /*   -> */ '/.*permission denied.*/i'],
            ["file_not_found", /*      -> */ '/.*(no such|not found).*/i'],
            ["failure", /*             -> */ '/.*(error|failure|you must supply).*/i'],
        ];

        while (($line = $this->process->get($timeout)) !== false) {
            foreach ($expected_errors as $ee) {
                if (preg_match($ee[1], $line)) {
                    if (!$this->failed_status || $ee[0] !== "connection_closed") {
                        $this->failed_status = [$ee[0] => true, "error" => trim($line)];
                    }
                    break;
                }
            }

            $consumed = ($lines_consumer && $lines_consumer($line) === true);
            if (!$consumed) {
                Utils::log()->info("SFTP: " . rtrim($line));
            }

            if (!$lines_consumer || $consumed) {
                if (--$expected_lines <= 0) {
                    $timeout = $remaining_timeout;
                }
            }
        }
    }

    public function close()
    {
        if (($p = $this->process) !== null) {
            $p->put("exit");
            $p->closeInput();

            $this->processAvailableInput(1.5);
            $p->close();

            $this->process = null;

            if ($this->failed_status && ($this->failed_status["connection_closed"] ?? false)) {
                $this->clearError();
            }
        }
    }

    public function lastError($timeout = 0.5)
    {
        if ($this->failed_status === false) {
            $this->processAvailableInput($timeout);
        }
        return $this->failed_status;
    }

    public function clearError()
    {
        $this->failed_status = false;
        return $this;
    }

    public function ls()
    {
        $files = [];
        $this->processAvailableInput();
        $this->process->put("ls -la");

        $regex = '/^([bcdlsp\-][rwx\-]{9}[+@]?)\s+[0-9]+\s+([^\s]+)\s+([^\s]+)\s+([0-9]+)\s+(\w+\s+[0-9]+\s+[0-9:]+)\s+(.+)$/';
        $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 2, function ($line) use (&$files, $regex) {
            if (preg_match($regex, $line, $matches)) {
                $filename = trim(stripcslashes($matches[6])); // decodes octal UTF-8 sequences
                $files[$filename] = [
                    "type" => $matches[1][0],
                    "permissions" => $matches[1],
                    "owner" => $matches[2],
                    "group" => $matches[3],
                    "size" => intval($matches[4]),
                    "mtime" => strtotime($matches[5])
                ];
                return true;
            }
            return false;
        }, 1);

        return $files;
    }

    public function pwd()
    {
        if ($this->pwd === null) {
            $remote_path = false;

            $this->processAvailableInput();
            $this->process->put("pwd");
            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 1, function ($line) use (&$remote_path) {
                if (preg_match('/^.+directory:\s(.+)$/i', $line, $matches)) {
                    $remote_path = trim(stripcslashes($matches[1]));
                    return true;
                }
                return false;
            });

            $this->pwd = $remote_path;
        }

        return $this->pwd;
    }

    /**
     * Returns the absolute remote path for the specified file.
     * @param string $remote_path the relative path.
     * @param string|null $remote_pwd the remote base directory. Omit to use current.
     * @return bool|string An absolute path.
     */
    public function resolve(string $remote_path, ?string $remote_pwd = null)
    {
        if (($pwd = ($remote_pwd ?: $this->pwd())) !== false) {
            $remote_path = Utils::resolvePath($remote_path, str_replace("/", DIRECTORY_SEPARATOR, $pwd));
            $remote_path = str_replace("\\", "/", $remote_path);
            return $remote_path;
        }
        return false;
    }

    public function get($remote_file, $local_file = "")
    {
        $this->processAvailableInput();
        $this->process->put("get "
            . escapeshellarg($remote_file)
            . (empty($local_file) ? "" : " " . escapeshellarg($local_file)));
        $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 2);
        return $this;
    }

    public function put($local_file, $remote_file = "", $preserve = true)
    {
        if (is_file($local_file)) {
            $this->processAvailableInput();
            $this->process->put("put " . ($preserve ? "-p " : "")
                . escapeshellarg($local_file)
                . (empty($remote_file) ? "" : " " . escapeshellarg($remote_file)));
            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 2);
        } else {
            Utils::log()->info("put: File $local_file doesn't exist.");
            $this->failed_status = ["file_not_found" => true, "error" => $local_file];
        }

        return $this;
    }

    public function mkdir($remote_path)
    {
        if (($remote_path = $this->resolve($remote_path)) !== false) {
            $this->process->put("mkdir " . escapeshellarg($remote_path));
            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 1);
        }
        return $this;
    }

    public function cd($remote_path)
    {
        if (($remote_path = $this->resolve($remote_path)) !== false) {
            $this->clearError();
            $this->process->put("cd " . escapeshellarg($remote_path));

            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 1);
            $error = $this->lastError();
            $pwd = false;
            $this->pwd = null;

            if ($error || $remote_path !== ($pwd = $this->pwd())) {
                $this->failed_status = array_merge(($error ?: []), [
                    "failure" => true,
                    "error" => "Failed changing path to '$remote_path' (pwd: '$pwd'); Cause: {$error["error"]}"
                ]);
            }
        }
        return $this;
    }

    public function chmod($remote_file, $mode)
    {
        if (($remote_file = $this->resolve($remote_file)) !== false && $remote_file !== $this->pwd()) {
            $this->processAvailableInput();
            $this->process->put("chmod " . escapeshellarg($mode) . " " . escapeshellarg($remote_file));
            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 2);
        }
        return $this;
    }

    public function chgrp($remote_file, $group_id)
    {
        if (($remote_file = $this->resolve($remote_file)) !== false && $remote_file !== $this->pwd()) {
            $this->processAvailableInput();
            $this->process->put("chgrp " . escapeshellarg($group_id) . " " . escapeshellarg($remote_file));
            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 2);
        }
        return $this;
    }

    public function rm($remote_file)
    {
        if (($remote_file = $this->resolve($remote_file)) !== false && $remote_file !== $this->pwd()) {
            $this->processAvailableInput();
            $this->process->put("rm " . escapeshellarg($remote_file));
            $this->processAvailableInput(self::COMMAND_REPLY_TIMEOUT, 2);
        }
        return $this;
    }
}
