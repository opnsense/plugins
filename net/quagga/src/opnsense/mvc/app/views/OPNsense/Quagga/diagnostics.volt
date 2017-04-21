{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 Michael Muenz
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

#}{{ partial("layout_partials/base_form",['fields':diagnosticsForm,'id':'frm_diagnostics_settings'])}}


<script type="text/javascript">
$(document).ready(function() {
  var data_get_map = {'frm_diagnostics_settings':"/api/quagga/diagnostics/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status']);
  });

  // link save button to API set action  //FIXME Change save to show ...
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/quagga/bgp/set",formid='frm_bgp_settings',callback_ok=function(){
        ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
          ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
          });
        });
      });
  });
  $('#showIpBgp').click(function(){
      $('#responseMsg').removeClass("hidden");
      ajaxCall(url="/api/quagga/service/diag-bgp2", sendData={}, callback=function(data,status) {
          $("#responseMsg").text(data['result']);
          BootstrapDialog.show({
              type: BootstrapDialog.TYPE_INFO,
              title: "{{ lang._('Output of: show ip bgp') }}",
              message: data['result'],
              draggable: true
          });
      });
  });
    

    });
</script>
    
<div class="col-md-12">
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
</div>
    
<div class="col-md-12">
    <button class="btn btn-primary"  id="showIpBgp" type="button"><b>{{ lang._('Output of: show ip bgp') }}</b></button>
</div> 
