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

// Optional include to get "log_error"
@include_once("util.inc");

// Syslog level used for verbose info log.
// Change to "LOG_NOTICE" to make log output visible in the UI.
const SYSLOG_INFO_LEVEL = LOG_INFO;

/**
 * Interface for logging.
 * @package OPNsense\AcmeClient
 */
interface ILogger
{
    function info($message);

    function error($message, $error = null);
}

/**
 * Shared utilities.
 * @package OPNsense\AcmeClient
 */
class Utils
{
    public static function &log($reconfigure_to_stdout = false): ILogger
    {
        static $logger;

        if (!$logger || $reconfigure_to_stdout) {
            if (!$reconfigure_to_stdout && function_exists("log_error")) {
                $logger = new class implements ILogger
                {
                    function info($message)
                    {
                        syslog(SYSLOG_INFO_LEVEL, basename(__FILE__) . ": INFO: $message");
                    }

                    function error($message, $error = null)
                    {
                        log_error(
                            $error
                                ? ("$message ; Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES))
                                : $message
                        );
                    }
                };
            } else {
                $logger = new class implements ILogger
                {
                    function info($message)
                    {
                        echo "INFO: {$message}" . PHP_EOL;
                    }

                    function error($message, $error = null)
                    {
                        echo "ERROR: "
                            . ($error
                                ? ("$message ; Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES))
                                : $message)
                            . PHP_EOL;
                    }
                };
            }
        }

        return $logger;
    }

    public static function requireThat($expression, $message)
    {
        if (!$expression) {
            self::log()->error("FATAL: $message");
            throw new \AssertionError($message);
        }
        return $expression;
    }

    /**
     * Converts a relative to a normalized absolute path.
     * @param string $file The relative file path to resolve against base.
     * @param string $base The base path to use for resolving the file.
     * @return bool|string The resolved path or false when impossible.
     */
    public static function resolvePath(string $file, string $base = ".")
    {
        $combined_path = $file;

        if (empty($file) || $file[0] != DIRECTORY_SEPARATOR) {
            if (empty($base) || $base[0] != DIRECTORY_SEPARATOR) {
                $base = realpath(($base ?: "."));
            }

            $combined_path = $base . DIRECTORY_SEPARATOR . $file;
        }

        $path = [];

        foreach (explode(DIRECTORY_SEPARATOR, $combined_path) as $part) {
            if (empty($part) || $part === '.') {
                continue;
            }

            if ($part !== '..') {
                array_push($path, $part);
            } elseif (!empty($path)) {
                array_pop($path);
            } else {
                return false;
            }
        }

        return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path);
    }
}
