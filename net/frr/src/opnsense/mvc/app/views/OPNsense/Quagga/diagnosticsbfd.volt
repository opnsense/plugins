{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
Copyright (C) 2023 Mark Stitson <mark@stitson.com>
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

{#
{{ partial("layout_partials/base_form",['fields':diagnosticsForm,'id':'frm_diagnostics_settings'])}}
#}

<script type="text/javascript" src="/ui/js/quagga/lodash.js"></script>
<script type="text/javascript">

$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/bfdneighbors/plain", sendData={}, callback=function(data, status) {
    $('#neighborscontent').text(data['response']);
  });

  ajaxCall(url="/api/quagga/diagnostics/bfdsummary/plain", sendData={}, callback=function(data, status) {
    $("#summarycontent").text(data['response']);
  });

  ajaxCall(url="/api/quagga/diagnostics/bfdcounters/plain", sendData={}, callback=function(data, status) {
    $("#counterscontent").text(data['response']);
  });
});
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
  <li><a data-toggle="tab" href="#neighbors">{{ lang._('Neighbors') }}</a></li>
  <li class="active"><a data-toggle="tab" href="#summary">{{ lang._('Summary') }}</a></li>
  <li><a data-toggle="tab" href="#counters">{{ lang._('Counters') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
  <div id="neighbors" class="tab-pane fade in">
    <pre id="neighborscontent"></pre>
  </div>
  <div id="summary" class="tab-pane fade in active">
    <pre id="summarycontent"></pre>
  </div>
  <div id="counters" class="tab-pane fade in">
    <pre id="counterscontent"></pre>
  </div>
</div>
