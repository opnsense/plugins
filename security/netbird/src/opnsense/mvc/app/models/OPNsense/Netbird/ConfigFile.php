<?php

/*
 * Copyright (C) 2026 Benny <bxnny@bxnny.de>
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

namespace OPNsense\Netbird;

/**
 * Reading and replacing the NetBird daemon's configuration file.
 *
 * The file holds the peer's WireGuard private key, so losing it costs an
 * enrolment rather than a setting. Every replacement therefore goes through a
 * sibling temporary file and rename(), which POSIX requires to be atomic: a
 * reader sees the whole old file or the whole new one, never a truncated one.
 *
 * Deliberately free of OPNsense classes, so it can be tested without the
 * framework - the same reason StatusReport is.
 */
class ConfigFile
{
    /** kept next to the target, holding what was there before the last write */
    public const BACKUP_SUFFIX = '.bak';

    /** written, flushed and renamed over the target; never left behind */
    public const TEMP_SUFFIX = '.tmp';

    /** used when the target does not exist yet and has no mode to inherit */
    public const FALLBACK_MODE = 0600;

    /**
     * @throws \RuntimeException with a message saying which of the failures it was
     */
    public static function read(string $target): array
    {
        if (!is_file($target)) {
            throw new \RuntimeException("there is no configuration at $target yet");
        }

        $raw = @file_get_contents($target);
        if ($raw === false) {
            throw new \RuntimeException("the configuration at $target cannot be read");
        }

        $config = json_decode($raw, true);
        if (!is_array($config)) {
            throw new \RuntimeException("the configuration at $target cannot be decoded: " . json_last_error_msg());
        }

        return $config;
    }

    /**
     * Replace $target with $config, or leave $target exactly as it was.
     *
     * @throws \RuntimeException
     */
    public static function write(string $target, array $config): void
    {
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('the new configuration cannot be encoded: ' . json_last_error_msg());
        }

        $mode = self::modeOf($target);
        $temp = $target . self::TEMP_SUFFIX;

        try {
            /* the backup goes first: if it cannot be written, nothing has been replaced yet */
            $previous = is_file($target) ? @file_get_contents($target) : false;
            if ($previous !== false) {
                self::put($target . self::BACKUP_SUFFIX, $previous, $mode);
            }

            self::put($temp, $encoded, $mode);

            if (!@rename($temp, $target)) {
                throw new \RuntimeException("the new configuration cannot be moved into place at $target");
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /**
     * A comma-separated field as the daemon's list of strings.
     *
     * An empty field is an empty list, not null and not a list holding one
     * empty string: to a Go []string those are three different things, and
     * only the first one means "no entries".
     */
    public static function listOf(string $value): array
    {
        $items = [];

        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The target's own mode, so a private key never widens on the way through.
     */
    private static function modeOf(string $target): int
    {
        $mode = @fileperms($target);

        return $mode === false ? self::FALLBACK_MODE : ($mode & 0777);
    }

    /**
     * Write one file, restrictively from the first byte and flushed to disk.
     *
     * The umask matters: a fresh file is created with the process umask, which
     * on this platform is wide enough to expose the key for as long as the
     * write takes. chmod() afterwards would close a door that was already open.
     */
    private static function put(string $path, string $contents, int $mode): void
    {
        $previousUmask = umask(0077);

        try {
            $handle = @fopen($path, 'wb');
            if ($handle === false) {
                throw new \RuntimeException("cannot create $path");
            }

            try {
                if (@fwrite($handle, $contents) !== strlen($contents)) {
                    throw new \RuntimeException("cannot write all of $path");
                }
                if (!@fflush($handle)) {
                    throw new \RuntimeException("cannot flush $path");
                }
                /* fsync() exists from PHP 8.1; without it the rename can outrun the data */
                if (function_exists('fsync') && !@fsync($handle)) {
                    throw new \RuntimeException("cannot sync $path to disk");
                }
            } finally {
                @fclose($handle);
            }
        } finally {
            umask($previousUmask);
        }

        if (!@chmod($path, $mode)) {
            throw new \RuntimeException("cannot set the mode on $path");
        }
    }
}
