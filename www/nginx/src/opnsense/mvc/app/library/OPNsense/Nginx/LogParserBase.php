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

    function __construct($file_name, $page, $per_page, $query)
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

    private function parse_file()
    {
        $handle = @fopen($this->file_name, 'r');
        if ($handle) {
            $cnt = 0;
            $total = 0;
            for ($line = '', $pos = -2; fseek($handle, $pos, SEEK_END) !== -1; $pos--) {
                $char = fgetc($handle);
                if ($char === "\n") {
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

                    $line = '';
                    $total++;
                }
                else {
                    $line = $char . $line;
                }
            }
            fclose($handle);

            $this->page_count = floor($cnt / $this->per_page);
            $this->total_lines = $total;
            $this->query_lines = $cnt;
        }
    }

    abstract protected function parse_line($line);

    public function get_result()
    {
        return $this->result;
    }
}

