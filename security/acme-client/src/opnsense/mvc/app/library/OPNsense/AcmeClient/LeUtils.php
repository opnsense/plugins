<?php

/*
 * Copyright (C) 2017-2025 Frank Wall
 * Copyright (C) 2015 Deciso B.V.
 * Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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

use OPNsense\Core\Config;

/**
 * Helper functions for LeAcme
 * @package OPNsense\AcmeClient
 */
class LeUtils
{
    public static function base64url_decode($str)
    {
        return base64_decode(str_pad(strtr($str, '-_', '+/'), strlen($str) % 4, '=', STR_PAD_RIGHT));
    }

    public static function base64url_encode($str)
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    /**
     * Properly escape arguments to prevent shell command injection.
     * This is a copy of the exec_safe() legacy function.
     * @param string $format The format string to use.
     * @param string $args The arguments to escape.
     * @return array|string The formatted and escaped arguments.
     */
    public static function execSafe($format, $args = array())
    {
        if (!is_array($args)) {
            /* just in case there's only one argument */
            $args = array($args);
        }

        foreach ($args as $id => $arg) {
            $args[$id] = escapeshellarg($arg);
        }

        return vsprintf($format, $args);
    }

    /**
     * log runtime information
     */
    public static function log($msg)
    {
        syslog(LOG_NOTICE, "AcmeClient: {$msg}");
    }

    /**
     * log additional debug output
     */
    public static function log_debug($msg, bool $debug = false)
    {
        if ($debug) {
            syslog(LOG_NOTICE, "AcmeClient: {$msg}");
        }
    }

    /**
     * log error messages
     */
    public static function log_error($msg)
    {
        syslog(LOG_ERR, "AcmeClient: {$msg}");
    }

    /**
     * run arbitrary shell commands and log the result
     * @param $proc_cmd string the command that should be run
     * @param $proc_env array optional environment variables that should be used
     * @return bool
     */
    public static function run_shell_command($proc_cmd, $proc_env = array())
    {
        $proc_desc = array(  // descriptor array for proc_open()
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
            2 => array("pipe", "w")  // stderr
        );
        $proc_pipes = array();
        $proc = proc_open($proc_cmd, $proc_desc, $proc_pipes, null, $proc_env);

        // Make sure the resource could be setup properly
        if (is_resource($proc)) {
            // This workaround ensures that the accurate return code
            // is reliably returned.
            fclose($proc_pipes[0]);
            stream_set_blocking($proc_pipes[1], false);
            stream_set_blocking($proc_pipes[2], false);
            while (!feof($proc_pipes[1]) || !feof($proc_pipes[2])) {
                $stdout = fread($proc_pipes[1], 1024);
                $stderr = fread($proc_pipes[2], 1024);
                usleep(50000);
            }
            fclose($proc_pipes[1]);
            fclose($proc_pipes[2]);

            // Get exit code
            $result = proc_close($proc);
            self::log(sprintf("AcmeClient: The shell command returned exit code '%d': '%s'", $result, $proc_cmd));
            return($result);
        } else {
            self::log_error(sprintf("AcmeClient: Unable to prepare shell command '%s'", $proc_cmd));
            return(-999);
        }
    }
}
