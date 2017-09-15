{#

Copyright © 2017 Giuseppe De Marco <giuseppe.demarco@unical.it>
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

<section class="col-xs-12">
    <div class="content-box">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
        
        <div class="col-md-12">
            <button class="btn btn-primary"  id="startAct" type="button"><b>{{ lang._('Start') }}</b></button>
        </div>
        
    </div>
</section>

<script type="text/javascript">
</script>

<!--
Start scann and get response
-->
<script type="text/javascript">
$( document ).ready(function() {
    $("#view").click(function(){
      $.ajax("diag_packet_capture.php",{
          type: 'get',
          cache: false,
          dataType: "json",
          data: {view: 'view', 'dnsquery': $("#dnsquery:checked").val() ,'detail': $("#detail").val()},
          success: function(response) {
            var html = [];
            $.each(response, function(idx, line){
                html.push('<tr><td>'+line+'</td></tr>');
            });
            $("#capture_output").html(html.join(''));
            $("#capture").removeClass('hidden');
            // scroll to capture output
            $('html, body').animate({
              scrollTop: $("#capture").offset().top
            }, 2000);
          }
      });
    });
});
</script>
