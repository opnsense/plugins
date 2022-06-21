{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Michael Muenz <m.muenz@gmail.com>
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
 #}
<script>
    $( document ).ready(function() {
    updateServiceControlUI('freeradius');
    
        $("#grid-sessions").UIBootgrid(
            {   'search':'/api/freeradius/service/realtimesessions',
                'get':'/api/freeradius/service/realtimesessions'
            }
        );
    });
</script>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#sessions">{{ lang._('Sessions') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="sessions" class="tab-pane fade in active">
        <!-- tab page "sessions" -->
    <table class="table table-condensed">
        <tbody>
            <tr>
                <td style="width:30%">Users connected:</td>
                <td>
                    <div id="total-users">0</div>
                </td>
            </tr>
        </tbody>
    </table>
    <table id="grid-sessions" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="username"  data-type="string" data-visible="true">{{ lang._('Username') }}</th>
                <th data-column-id="realm" data-type="string" data-visible="true">{{ lang._('Realm') }}</th>
                <th data-column-id="nasporttype" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                <th data-column-id="acctstarttime" data-type="string" data-visible="true">{{ lang._('Start Time GMT') }}</th>
                <th data-column-id="clientshortname" data-type="string" data-visible="true">{{ lang._('Client') }}</th>
                <th data-column-id="callingstationid" data-type="string" data-visible="true">{{ lang._('Mac Address') }}</th>
                <th data-column-id="ipaddress" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                <th data-column-id="networkname" data-type="string" data-visible="true">{{ lang._('Wireless\LAN Network Name') }}</th>
            </tr>
        </thead>
        <tbody id="log_block">
        </tbody>
            <tfoot>
            </tfoot>
    </table>
</div>
</div>
