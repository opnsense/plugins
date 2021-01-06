<?php

/*

    Copyright (C) 2018-2020 Fabian Franz
    Copyright (C) 2020 Manuel Faux
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Nginx;

abstract class LogParserBase
{
    protected $result;

    /**
     * Constructs a new LogParserBase instance.
     *
     * @param $file_name string path to log file of the HTTP server to be parsed
     * @param $page int pagination page to retrieve
     * @param $per_page int number of entries per page
     * @param $query string filter string to apply
     */
    function __construct($file_name, $page = 0, $per_page = 1, $query = array())
    {
        $this->file_name = $file_name;
        $this->page = $page;
        $this->per_page = $per_page;
        $this->query = $query;
        $this->page_count = 0;
        $this->total_lines = 0;
        $this->query_lines = 0;
        $this->result = array();

        $this->parse_file();
    }

    /**
     * Forward read complete gz compressed logfile into memory, reverse file,
     * count lines, save lines which match filter and count matching lines.
     */
    private function parse_file()
    {
        $lines = gzfile($this->file_name);
        if ($lines !== false && count($lines) > 0) {
            $lines = array_reverse($lines);
            
            $cnt = 0;
            foreach ($lines as $line) {
                $pass = true;
                $parsed_line = $this->parse_line($line);
                if (count($this->query) > 0) {
                    foreach ($this->query as $key => $val) {
                        $val = (string)$val;
                        if (!empty($val) && strpos($parsed_line->{$key}, (string)$val) === false) {
                            $pass = false;
                        }
                    }
                }

                if ($pass) {
                    if (floor($cnt / $this->per_page) == $this->page) {
                        $this->result[] = $parsed_line;
                    }
                    $cnt++;
                }
            }

            $this->page_count = floor(count($lines) / $this->per_page);
            $this->total_lines = count($lines);
            $this->query_lines = $cnt;
        }
    }

    abstract protected function parse_line($line);

    public function get_result()
    {
        return $this->result;
    }
}

