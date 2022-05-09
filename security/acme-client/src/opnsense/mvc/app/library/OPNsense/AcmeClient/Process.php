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
 * Utility class to execute shell processes and handle their IO.
 * @package OPNsense\AcmeClient
 */
class Process
{
    private $handle;
    private $inputs;
    private $outputs;

    public $exitCode = null;

    /**
     * Starts the specified process and returns an object to manage it.
     * @param array $cmd the command to run.
     * @param null $cwd the working directory or null to use current.
     * @param null $env the environment or null to use current.
     * @return Process|null A process instance or null when startup failed.
     */
    public static function open(array $cmd, $cwd = null, $env = null): ?Process
    {
        $p = new Process($cmd, $cwd, $env);
        return $p->isRunning() ? $p : null;
    }

    private static function manageOpenedProcess($process_handle, $release = false)
    {
        static $open_processes;

        if (!is_array($open_processes)) {
            $open_processes = [];

            // Ensure we never leave zombies around: Hooking into script shutdown and kill processes that are still running.
            register_shutdown_function(function () use (&$open_processes) {
                foreach ($open_processes as $handle) {
                    if (is_resource($handle)) {
                        Utils::log()->error("Terminating process: " . json_encode(proc_get_status($handle)));
                        @proc_terminate($handle);
                    }
                }
                $open_processes = [];
            });
        }

        if ($process_handle) {
            if ($release) {
                if (in_array($process_handle, $open_processes)) {
                    $open_processes = array_diff($open_processes, [$process_handle]);
                }
            } else {
                $open_processes[] = $process_handle;
            }
        }
    }

    public function __construct($cmd, $cwd = null, $env = null)
    {
        $cmd = join(" ", array_map(function ($v) {
            return escapeshellarg($v);
        }, $cmd));

        $spec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $this->handle = proc_open($cmd, $spec, $pipes, $cwd, $env);

        if (is_resource($this->handle)) {
            $this->outputs = $pipes;
            $this->inputs = [array_shift($this->outputs)];

            foreach ($this->outputs as $stream) {
                stream_set_blocking($stream, false);
            }

            self::manageOpenedProcess($this->handle);
        } else {
            Utils::log()->error("Failed opening '$cmd' in '$cwd'");
        }
    }

    public function __destruct()
    {
        $this->close();

        if ($this->isRunning()) {
            $this->close(true);
        }
    }

    private $linesBuffer = [];

    private function nextBufferedLine()
    {
        return empty($this->linesBuffer)
            ? false
            : array_shift($this->linesBuffer);
    }

    /**
     * Returns one line from stdout or stdin as it gets available. May return 'false' when no line became available
     * within the specified $timeout or when another stream events occurred that returned no new content.
     * @param $timeout float timeout in seconds
     * @param $max_length int max length of a single line
     * @return false|string One line of stdout/err (merged) or false when no new line exists.
     */
    public function get($timeout = 5, $max_length = 64 * 1024)
    {
        if (($line = $this->nextBufferedLine()) !== false) {
            return $line;
        }

        $readables = array_filter($this->outputs, fn($stream) => is_resource($stream) && !feof($stream));
        $micros = intval(($timeout - floor($timeout)) * 1000000) + 100;
        $timeout = floor($timeout);
        $__ = null;

        $can_read = !empty($readables)
            && stream_select($readables, $__, $__, $timeout, $micros) !== false;

        if ($can_read) {
            foreach ($readables as $stream) {
                $content = fread($stream, $max_length);
                if ($content !== false) {
                    array_push($this->linesBuffer, ...preg_split('/\r\n|\n|\r/', $content));
                    if (empty($this->linesBuffer[-1])) {
                        array_pop($this->linesBuffer); // remove trailing empty newline
                    }
                }
            }
        }

        return $this->nextBufferedLine();
    }

    public function put($data, $append = PHP_EOL)
    {
        if ($this->isRunning() && is_resource($stdin = $this->inputs[0]) && !feof($stdin)) {
            fwrite($stdin, $data);
            if ($append) {
                fwrite($stdin, $append);
            }
        }
    }

    public function closeInput()
    {
        if (!feof($stdin = $this->inputs[0])) {
            fclose($stdin);
        }
    }

    public function close($force = false)
    {
        // Read up-to 10k remaining lines from STDOUT/ERR to release locks before closing.
        for ($i = 0; ($line = $this->get(0)) && $i < 10000; $i++) {
            Utils::log()->error("WARN: process: $line");
        }

        if ($this->isRunning()) {
            $this->exitCode = $force
                ? proc_terminate($this->handle)
                : proc_close($this->handle);
        }

        if (!$this->isRunning()) {
            self::manageOpenedProcess($this->handle, true);
        }

        return $this->exitCode;
    }

    public function isRunning()
    {
        $status = is_resource($this->handle)
            ? proc_get_status($this->handle)
            : false;

        if (is_array($status)) {
            if (!$this->exitCode && $this->exitCode !== 0 && !$status["running"]) {
                $this->exitCode = $status["exitcode"];
            }
            return $status["running"];
        }

        return false;
    }
}
