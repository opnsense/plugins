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
 * Handles file uploads via SFTP.
 * @package OPNsense\AcmeClient
 */
class SftpUploader
{
    public const UPLOAD_SUCCESS = 0;
    public const UPLOAD_ERROR = 1;
    public const UPLOAD_ERROR_NO_PERMISSION = 2;
    public const UPLOAD_ERROR_NO_OVERWRITE = 3;
    public const UPLOAD_ERROR_CHMOD_FAILED = 4;
    public const UPLOAD_ERROR_CHGRP_FAILED = 5;

    /* @var SftpClient */
    private $sftp;
    private $sftp_connection_file_owner = -2;

    private $pending_files = [];
    private $pending_base_path = "";
    private $current_file = "";
    private $temporary_files_index = -1;

    public function __construct(SftpClient &$sftp)
    {
        $this->sftp = $sftp;
    }

    public function __destruct()
    {
        $this->temporaryFile(true);
    }

    /**
     * Add a file to upload.
     *
     * @param string $local_file the path to the local file.
     * @param string $remote_file the remote path to copy the file to or empty to use the files name.
     * @param bool $chmod the 4 digit unix permission to apply or false to leave it unchanged.
     * @param bool $chgrp the numeric group on the remote server to apply or false to leave it unchanged.
     * @return string the name of the normalized local file.
     */
    public function addFile(string $local_file, $remote_file = "", $chmod = false, $chgrp = false): string
    {
        Utils::requireThat(is_file($local_file) && is_readable($local_file), "Not a file or not readable: '$local_file'");
        $local_file = realpath($local_file);

        $this->deleteSourceIfRequested($local_file);

        $this->pending_files[$local_file] = [
            "source" => $local_file,
            "target" => $remote_file,
            "mode" => $chmod,
            "group" => $chgrp
        ];

        return $local_file;
    }

    /**
     * Adds content to upload.
     *
     * @param string $content the binary content to upload.
     * @param string $remote_file the remote path to copy the file to.
     * @param int $content_last_modified the unix timestamp when the content was last modified (is preserved when chmod is also specified).
     * @param bool $chmod the 4 digit unix permission to apply or false to leave it unchanged.
     * @param bool $chgrp the numeric group on the remote server to apply or false to leave it unchanged.
     * @return string the name of the remote file.
     */
    public function addContent(string $content, string $remote_file = "", $content_last_modified = 0, $chmod = false, $chgrp = false): string
    {
        $local_file = $this->temporaryFile();
        Utils::requireThat($local_file, "Failed creating temporary file for '$remote_file'");

        $content_written = file_put_contents($local_file, $content);
        Utils::requireThat($content_written > 0, "Failed writing content of '$remote_file' to '$local_file', disk full?");

        if (($time = intval($content_last_modified)) && $time > 0) {
            touch($local_file, $time);
        }

        $remote_file = trim($remote_file);
        if (empty($remote_file)) {
            $remote_file = basename($local_file);
        }

        $local_file = $this->addFile($local_file, $remote_file, $chmod, $chgrp);
        $this->pending_files[$local_file]["delete_source"] = true;

        return $remote_file;
    }

    /**
     * @return array a list of files pending an upload.
     */
    public function pending(): array
    {
        return array_values($this->pending_files);
    }

    /**
     * @return array|null The current file being uploaded or failed to upload. Null when no upload was performed or when it succeeded.
     */
    public function current(): ?array
    {
        return empty($this->current_file) || empty($this->pending_files)
            ? null
            : $this->pending_files[$this->current_file];
    }

    /**
     * Uploads all files from the pending list.
     * @return int a return code indicating whether upload was successful or stopped.
     */
    public function upload(): int
    {
        // Correct state when we are restarted after an error
        if ($this->current_file) {
            // Restore the remote path to where we originally have been.
            if ($this->pending_base_path) {
                $error = $this->sftp
                    ->clearError()
                    ->cd($this->pending_base_path)
                    ->lastError();

                if ($error) {
                    Utils::log()->error("Cannot continue since changing to initial remote path '{$this->pending_base_path}' failed", $error);
                    return self::UPLOAD_ERROR;
                }
            }

            // Remove file that caused an error previously.
            unset($this->pending_files[$this->current_file]);
        }

        $this->pending_base_path = $remote_base_path = $this->sftp->pwd();
        $remote_files = [];
        $remote_path = ".";

        // Collecting files to upload (sorted by target to reduce remote directory changes)
        $files_to_upload = $this->pending();
        usort($files_to_upload, function (&$a, &$b) {
            return $a["target"] <=> $b["target"];
        });

        // Uploading the files
        foreach ($files_to_upload as $file) {
            // Managing pending files.
            $local_file = $this->current_file = $file["source"];

            // Clear errors for processing next file.
            $this->sftp->clearError();

            try {
                $connection = $this->sftp->connected();
                if (!$connection) {
                    Utils::log()->error("The sftp client is not connected, upload stopped.");
                    return self::UPLOAD_ERROR;
                }

                // Changing remote directory if required.
                if (($target_dir = dirname($file["target"])) !== $remote_path) {
                    $absolute_target_dir = $this->sftp->resolve($target_dir, $remote_base_path);
                    Utils::requireThat(
                        $absolute_target_dir && strpos($absolute_target_dir, $remote_base_path) === 0,
                        "Illegal target directory '$absolute_target_dir' is not below '$remote_base_path'"
                    );

                    $dir_names = preg_split('-/+-', substr($absolute_target_dir, strlen($remote_base_path)), 0, PREG_SPLIT_NO_EMPTY);
                    if (count($dir_names) == 1) {
                        $dir_names[0] = $absolute_target_dir; // Single dir: Use absolute path
                    } else {
                        $this->sftp->cd($remote_base_path); // None or multiple directories: Start from base path and create one by one as needed
                    }

                    foreach ($dir_names as $dir) {
                        if ($error = $this->sftp->cd($dir)->lastError()) {
                            if ($error["file_not_found"]) {
                                Utils::log()->info("Creating remote directory: $dir");
                                $this->sftp->clearError()
                                    ->mkdir($dir)
                                    ->cd($dir);
                            } else {
                                break;
                            }
                        }
                    }

                    if ($error = $this->sftp->lastError()) {
                        Utils::log()->error("Failed to cd into '$target_dir'.", $error);
                        return self::UPLOAD_ERROR;
                    }

                    $remote_path = $target_dir;
                    $remote_files = [];
                }

                // Listing existing remote files
                if (empty($remote_files)) {
                    $remote_files = $this->sftp->clearError()->ls();
                    if ($error = $this->sftp->lastError()) {
                        Utils::log()->error("Failed listing remote files.", $error);
                        return self::UPLOAD_ERROR;
                    }
                }

                // Preparing upload
                $username = $connection["user"];
                $remote_filename = basename((empty($file["target"]) ? $local_file : $file["target"]));
                $remote_file = $remote_files[$remote_filename] ?? ["type" => "-", "owner" => $username];
                $remote_is_file = $remote_file["type"] === "-";
                $remote_is_readonly = preg_match('/^[^wW]+$/', $remote_file["permissions"] ?? "");

                // Check if a folder/socket/symlink, etc is in the way
                if (!$remote_is_file) {
                    Utils::log()->error("Failed uploading file '{$local_file}' as there is a non-file in the way at '{$file["target"]}'");
                    return self::UPLOAD_ERROR_NO_OVERWRITE;
                }

                $chgrp = $file["group"] ?? "";
                $chgrp = preg_match('/^\d+$/', $chgrp) ? (string)$chgrp : false;

                $chmod = $file["mode"] ?? "";
                $chmod = preg_match('/^0\d{3}$/', $chmod) ? (string)$chmod : false;


                // Initial upload when permissions are properly set.
                $should_upload_with_permission_change =
                    $chmod !== false
                    && isset($remote_files[$remote_filename]);

                if (!$remote_is_readonly) {
                    $preserve_times_and_mod = $chmod !== false;

                    if ($error = $this->sftp->put($local_file, $remote_filename, $preserve_times_and_mod)->lastError()) {
                        if ($error["permission_denied"] !== true) {
                            $should_upload_with_permission_change = false;
                        }

                        if ($should_upload_with_permission_change) {
                            $this->sftp->clearError();
                        } else {
                            Utils::log()->error("Failed uploading file '{$local_file}' to '{$file["target"]}'", $error);
                            return self::UPLOAD_ERROR_NO_PERMISSION;
                        }
                    } else {
                        $should_upload_with_permission_change = false;
                    }
                }

                // Second attempt when initial failed or was skipped due to write protection (only possible if we have chmod defined to reset permissions later)
                if ($should_upload_with_permission_change && $this->isFileOwnedByConnection($remote_file, $connection)) {
                    Utils::log()->info("Trying to upload file '{$local_file}' to '{$file["target"]}' with adjusted permissions");

                    if ($error = $this->sftp->chmod($remote_filename, '0600')->lastError()) {
                        Utils::log()->error("Failed changing permission to '0600' for '{$file["target"]}'. ", $error);
                        $this->sftp->clearError();
                    }

                    if ($error = $this->sftp->put($local_file, $remote_filename)->lastError()) {
                        Utils::log()->error("Failed uploading file (with adjusted permissions) '{$local_file}' to '{$file["target"]}'", $error);
                        return self::UPLOAD_ERROR_NO_PERMISSION;
                    }
                } elseif ($remote_is_readonly) {
                    Utils::log()->error("Failed uploading file '{$local_file}' to '{$file["target"]}'. Existing file is write protected.");
                    return self::UPLOAD_ERROR_NO_PERMISSION;
                }


                // Applying chmod / chgrp if requested.
                if ($chmod) {
                    if ($error = $this->sftp->chmod($remote_filename, $chmod)->lastError()) {
                        Utils::log()->error("Failed chmod ($chmod) for '{$file["target"]}'", $error);
                        return self::UPLOAD_ERROR_CHMOD_FAILED;
                    }
                }

                if ($chgrp) {
                    if ($error = $this->sftp->chgrp($remote_filename, $chgrp)->lastError()) {
                        Utils::log()->error("Failed chgrp ($chgrp) for '{$file["target"]}'", $error);
                        return self::UPLOAD_ERROR_CHGRP_FAILED;
                    }
                }
            } finally {
                $this->deleteSourceIfRequested($local_file);
            }

            unset($this->pending_files[$local_file]);
        }

        $this->current_file = null;

        if (empty($this->pending_files)) {
            $this->temporaryFile(true);
        }

        return self::UPLOAD_SUCCESS;
    }

    private function isFileOwnedByConnection(array $remote_file, array $connection): bool
    {
        if (isset($remote_file["owner"])) {
            // Direct match when file owner was returned as username
            if ($remote_file["owner"] === $connection["user"]) {
                return true;
            }

            // Detect file owner when it was returned as numeric value.
            if (preg_match('/^[0-9]+$/', $remote_file["owner"])) {
                // Uploading a temp file to see what owner this connection creates
                if ($this->sftp_connection_file_owner === -2) {
                    $local_test_file = $this->temporaryFile();
                    $remote_test_file = basename($local_test_file);

                    $this->sftp->clearError();

                    if ($error = $this->sftp->put($local_test_file, $remote_test_file)->lastError()) {
                        Utils::log()->error("Failed uploading test file to detect ownership. Next uploads may fail as well.", $error);
                    } else {
                        // Get owner of the test file
                        $file_info = $this->sftp->ls()[$remote_test_file] ?? ["owner" => -1];
                        // Cleanup
                        $this->sftp->rm($remote_test_file);
                        $this->sftp->clearError();
                        // Cache the result
                        $this->sftp_connection_file_owner = $file_info["owner"];
                    }
                }

                if ($remote_file["owner"] == $this->sftp_connection_file_owner) {
                    return true;
                }
            }
        }

        return false;
    }

    private function deleteSourceIfRequested($file)
    {
        if (
            isset($this->pending_files[$file])
            && is_array($existing = $this->pending_files[$file])
            && ($existing["delete_source"] ?? false) === true
        ) {
            unlink($existing["source"]);
        }
    }

    private function temporaryFile($delete_all = false)
    {
        static $shared_temporary_files;
        static $shared_temporary_files_index_sequence = 0;

        // Maintain all generated files statically to ensure they are removed even when the destructor isn't called.
        if (!is_array($shared_temporary_files)) {
            $shared_temporary_files = [];

            register_shutdown_function(function () use (&$shared_temporary_files) {
                $count = 0;
                foreach ($shared_temporary_files as $temporary_files) {
                    if (!is_iterable($temporary_files)) {
                        continue;
                    }
                    foreach ($temporary_files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                            $count++;
                        }
                    }
                }

                if ($count > 0) {
                    Utils::log()->info("Removed $count files in shutdown hook instead of object destruction.");
                }

                $shared_temporary_files = [];
            });
        }

        $index = $this->temporary_files_index;
        if ($index <= 0 || !is_array($shared_temporary_files[$index] ?? null)) {
            $index = $this->temporary_files_index = ++$shared_temporary_files_index_sequence;
            $shared_temporary_files[$index] = [];
        }

        $temporary_files = &$shared_temporary_files[$index];


        // Dealing with temp file creation or cleanup
        if ($delete_all) {
            foreach ($temporary_files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            unset($shared_temporary_files[$index]);
        } else {
            if ($file = tempnam(sys_get_temp_dir(), "sftp-upload-")) {
                $file = realpath($file);
                $temporary_files[] = $file;
                Utils::requireThat(chmod($file, 0600), "failed setting user-only permissions on '$file'.");
                return $file;
            }
        }

        return false;
    }
}
