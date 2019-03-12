{#

OPNsenseÂ® is Copyright 2014 - 2018 by Deciso B.V.
This file is Copyright 2019 by Alec Samuel Armbruster <alectrocute@gmail.com>
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED â€œAS ISâ€ AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<style>
.fa-pulse {
	margin-left: 8px
}
#responseMsgInner {
	white-space: pre
}
#console-dnsbl {
	margin-top: 1em
}
.btn-dnsbl {
	margin-bottom: 15px;
	margin-left: 15px
}

.tokenize > .tokens-container {	
	min-width:530px !important;
	min-height: 100px !important
}

.dnsbl-code {
	font-family: Courier;
	font-size: 12px;
	border-radius: 5px 5px 5px 5px;
	background-color: #f3f3f3;
	color: #000;
	padding: 5px
}

</style>

<div id="responseMsg" class="alert alert-info" role="alert">{{ lang._('Loading') }}...</div>
<div class="row">
   <div class="col-12 col-lg-8 col-md-12">
      <div class="content-box">
         {{ partial("layout_partials/base_form",['fields':general,'id':'frm_general_settings'])}}
         <hr />
         <button class="btn btn-primary btn-dnsbl" id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i>
         </button>
      </div>
      <div id="console-dnsbl" class="col-12">
         <p id="responseMsgDsc">{{ lang._('Console') }}:</p>
         <pre id="responseMsgInner"></pre>
      </div>
   </div>
   <div class="col-lg-4 col-md-12">
      <div class="content-box" style="padding-left: 15px; padding-right: 15px">
         <h3><i class="fa fa-thermometer" style="margin-right: 7px;"></i> {{ lang._('Overview') }}</h3>
         {{ lang._('Total domains on blocklist') }}:<br>
         <span id="statsInner" style="font-size: 2em;"></span>
         <hr />
         <h3><i class="fa fa-wrench" style="margin-right: 7px;"></i> {{ lang._('Configuration') }}</h3>
         <p>{{ lang._('To activate DNSBL, go to:') }}</p>
         <p><a href="/services_unbound.php">{{ lang._('Unbound DNS') }}</a> &rarr; {{ lang._('General') }} &rarr; {{ lang._('Custom options') }} </p>
         <p>{{ lang._('And add the following configuration line:') }}</p>
         <p><span class="dnsbl-code">include:/var/unbound/dnsbl.conf</span> <a style="cursor: pointer;" onClick="copyToClipboard(); this.innerHTML = '<i class=&quot;fa fa-check&quot; style=&quot;margin-left: 5px; margin-right: 3px;&quot;></i>{{ lang._('Copied') }}!'"><i class="fa fa-paste" style="margin-left: 5px; margin-right: 3px;"></i>{{ lang._('Copy') }}</a> </p>
         <h3><i class="fa fa-question" style="margin-right: 7px;"></i> {{ lang._('Troubleshooting') }}</h3>
         <p>{{ lang._('Only use blocklist files which will appear to be lists of domain names with or without HOSTS-styled columns (eg. those compatible with') }} Pi-hole<span style="font-size: 8px;">(R)</span> {{ lang._(' will work fine). Do not enter specific hostnames, IP address nor domains in the blocklist field - only full path external URLs.') }}</p>
         <p>{{ lang._('For whitelisting, enter a specific domain names (such as ') }}  <span class="dnsbl-code">doubleclick.net</span> {{ lang._(') which you would like ignored from the configured blocklist(s).') }}</p>
         <br>
      </div>
   </div>
</div>

<script>
function getStats(){ajaxCall(url="/api/unboundbl/service/stats",sendData={},callback=function(e,n){$("#statsInner").text(e.message)})}$(document).ready(function(){var e={frm_general_settings:"/api/unboundbl/general/get"};mapDataToFormUI(e).done(function(e){formatTokenizersUI(),$(".selectpicker").selectpicker("refresh")}),getStats(),$("#responseMsg").addClass("hidden"),$("#console-dnsbl").addClass("hidden"),mapDataToFormUI(e).done(function(e){}),$("#saveAct").click(function(){saveFormToEndpoint(url="/api/unboundbl/general/set",formid="frm_general_settings",callback_ok=function(){$("#saveAct_progress").addClass("fa fa-spinner fa-pulse"),$("#console-dnsbl").removeClass("hidden"),$("#responseMsgInner").text("Working..."),ajaxCall(url="/api/unboundbl/service/refresh",sendData={},callback=function(e,n){ajaxCall(url="/api/unboundbl/service/reload",sendData={},callback=function(n,t){$("#responseMsg").removeClass("hidden"),$("#responseMsg").text("Settings changed! Please restart your DNS resolver."),$("#responseMsgInner").text(e.message),getStats()}),$("#saveAct_progress").removeClass("fa fa-spinner fa-pulse")})})})});const copyToClipboard=e=>{const n=document.createElement("textarea");n.value="include:/var/unbound/dnsbl.conf",n.setAttribute("readonly",""),n.style.position="absolute",n.style.left="-9999px",document.body.appendChild(n);const t=document.getSelection().rangeCount>0&&document.getSelection().getRangeAt(0);n.select(),document.execCommand("copy"),document.body.removeChild(n),t&&(document.getSelection().removeAllRanges(),document.getSelection().addRange(t))};
</script>
