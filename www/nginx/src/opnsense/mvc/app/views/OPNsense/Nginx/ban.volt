{#
 # Copyright (C) 2017-2018 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 #  1. Redistributions of source code must retain the above copyright notice,
 #   this list of conditions and the following disclaimer.
 #
 #  2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<div class="content-box">
    <table id="grid-ban" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="limit_request_connectiondlg">
        <thead>
            <tr>
                <th data-column-id="ip" data-type="string" data-sortable="true" data-visible="true">{{ lang._('IP Address / Network') }}</th>
                <th data-column-id="time" data-type="timestamp" data-sortable="true" data-visible="true">{{ lang._('Time') }}</th>
                <th data-column-id="button" data-width="7em" data-formatter="delbtn" data-sortable="false">{{ lang._('Unlock') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
<script>
$(function () {
    $("#grid-ban").UIBootgrid(
        { 'search':'/api/nginx/bans/searchban',
            'del':'/api/nginx/bans/delban/',
            'options': {
                selection:false,
                multiSelect:false,
                converters: {
                    timestamp: {
                        to: function (value) { return (new Date(value*1000)).toLocaleString(); }
                    }
                },
                formatters: {
                    "delbtn": function (column, row) {
                        return `<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="${row.uuid}"><span class=\"fa fa-unlock-alt\"></span></button>`;
                    }
                },
            }
        }
    );

});

</script>
