{#
 # Copyright (C) 2018 Smart-Soft
 # Copyright (C) 2014 Deciso B.V.
 # Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
 # Copyright (C) 2006 Eric Friesen
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 # this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 # notice, this list of conditions and the following disclaimer in the
 # documentation and/or other materials provided with the distribution.
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

<script type="text/javascript">

 // Highlights the words "PASSED", "FAILED", and "WARNING".
 function add_colors(text) {
     return text
	 .replace(/PASSED/g, '<span class="text-success">{{ lang._('PASSED') }}</span>')
	 .replace(/FAILED/g, '<span class="text-danger">{{ lang._('FAILED') }}</span>')
	 .replace(/WARNING/g, '<span class="text-warning">{{ lang._('WARNING') }}</span>');
 };

 // Appends options to select device.
 function appendDeviceSelectOptions(deviceSelect, devices) {
     $.each(devices, function(index, value) {
	 deviceSelect
	     .append($("<option></option>")
		 .attr("value", value)
		 .text(value));
     });
 };

 $( document ).ready(function() {
     ajaxCall("/api/smart/service/list", {}, function(data, status) {
	 // action to run after reload
	 var devices = data.devices;

	 if (Array.isArray(devices) && devices.length > 0) {
	     $("#noDevicesMsg").addClass("hidden");
	     $("#genericActions").removeClass("hidden");

	     appendDeviceSelectOptions($("#device1"), devices);
	     appendDeviceSelectOptions($("#device2"), devices);
	     appendDeviceSelectOptions($("#device3"), devices);
	     appendDeviceSelectOptions($("#device4"), devices);

	     $("#viewInfoAct").click(function() {
		 var type = $("input[name='type']:checked").val();
		 var device = $("#device1").val();

		 ajaxCall("/api/smart/service/info", {
		     "type" : type,
		     "device" : device
		 }, function(data, status) {
		     $("#infoMsg").html(add_colors(data['output']));
		     $("#infoMsg").removeClass("hidden");
		 });
	     });

	     $("#testAct").click(function() {
		 var type = $("input[name='testType']:checked").val();
		 var device = $("#device2").val();

		 ajaxCall("/api/smart/service/test", {
		     "type" : type,
		     "device" : device
		 }, function(data, status) {
		     $("#testMsg").html(add_colors(data['output']));
		     $("#testMsg").removeClass("hidden");
		 });
	     });

	     $("#viewLogsAct").click(function() {
		 var type = $("input[name='logType']:checked").val();
		 var device = $("#device3").val();

		 ajaxCall("/api/smart/service/logs", {
		     "type" : type,
		     "device" : device
		 }, function(data, status) {
		     $("#logsMsg").html(add_colors(data['output']));
		     $("#logsMsg").removeClass("hidden");
		 });
	     });

	     $("#abortTestAct").click(function() {
		 if (!confirm("{{ lang._('Do you really want to abort the test?') }}"))
		     return;

		 var device = $("#device4").val();

		 ajaxCall("/api/smart/service/abort", {
		     "device" : device
		 }, function(data, status) {
		     $("#abortTestMsg").html(data['output']);
		     $("#abortTestMsg").removeClass("hidden");
		 });
	     });
	 } else {
	     $("#noDevicesMsg").removeClass("hidden");
	     $("#genericActions").addClass("hidden");
	 }
     })
 });
</script>

<div class="alert alert-info hidden" role="alert" id="noDevicesMsg">
{{ lang._('No SMART devices.') }}
</div>
<div class="row hidden" id="genericActions">
    <section class="col-xs-12">
	<div class="content-box tab-content table-responsive">
	    <table class="table table-striped __nomb">
		<tr>
		    <th colspan="2" style="vertical-align:top" class="listtopic">{{ lang._('Info') }}</th>
		</tr>
		<tr>
		    <td>{{ lang._('Info type') }}</td>
		    <td><div class="radio">
			<label><input type="radio" name="type" value="i" />{{ lang._('Info') }}</label>&nbsp;
			<label><input type="radio" name="type" value="H" checked="checked" />{{ lang._('Health') }}</label>&nbsp;
			<label><input type="radio" name="type" value="c" />{{ lang._('SMART Capabilities') }}</label>&nbsp;
			<label><input type="radio" name="type" value="A" />{{ lang._('Attributes') }}</label>&nbsp;
			<label><input type="radio" name="type" value="a" />{{ lang._('All') }}</label>
		    </div>
		    </td>
		</tr>
		<tr>
		    <td><label for="device1">{{ lang._('Device: /dev/') }}</label></td>
		    <td >
			<select id="device1" name="device" class="form-control">
			</select>
		    </td>
		</tr>
		<tr>
		    <td style="width:22%; vertical-align:top">&nbsp;</td>
		    <td style="width:78%">
			<input type="button" name="submit" class="btn btn-primary" value="{{ lang._('View') }}" id="viewInfoAct" />
		    </td>
		</tr>
	    </table>
	</div>
	<pre class="hidden" id="infoMsg">
	</pre>
    </section>

    <section class="col-xs-12">
        <div class="content-box tab-content table-responsive">
            <table class="table table-striped __nomb">
		<tr>
                    <th colspan="2" style="vertical-align:top" class="listtopic">{{ lang._('Perform Self-tests') }}</th>
                </tr>
                <tr>
                    <td>{{ lang._('Test type') }}</td>
                    <td>
			<div class="radio">
			    <label><input type="radio" name="testType" value="offline" />{{ lang._('Offline') }}</label>&nbsp;
			    <label><input type="radio" name="testType" value="short" checked="checked" />{{ lang._('Short') }}</label>&nbsp;
			    <label><input type="radio" name="testType" value="long" />{{ lang._('Long') }}</label>&nbsp;
			    <label><input type="radio" name="testType" value="conveyance" />{{ lang._('Conveyance (ATA Disks Only)') }}</label>
			</div>
                    </td>
                </tr>
		<tr>
                    <td><label for="device2">{{ lang._('Device: /dev/') }}</label></td>
                    <td>
			<select id="device2" name="device" class="form-control">
			</select>
                    </td>
		</tr>
		<tr>
                    <td style="width:22%; vertical-align:top">&nbsp;</td>
                    <td style="width:78%">
			<input type="button" name="submit" class="btn btn-primary" value="{{ lang._('Test') }}" id="testAct" />
                    </td>
		</tr>
            </table>
        </div>
	<pre class="hidden" id="testMsg">
	</pre>
    </section>

    <section class="col-xs-12">
        <div class="content-box tab-content table-responsive">
            <table class="table table-striped __nomb">
		<tr>
                    <th colspan="2" style="vertical-align:top" class="listtopic">{{ lang._('View Logs') }}</th>
		</tr>
		<tr>
                    <td>{{ lang._('Log type') }}</td>
                    <td>
			<div class="radio">
			    <label><input type="radio" name="logType" value="error" checked="checked" />{{ lang._('Error') }}</label>&nbsp;
			    <label><input type="radio" name="logType" value="selftest" />{{ lang._('Self-test') }}</label>
			</div>
                    </td>
		</tr>
		<tr>
                    <td><label for="device3">{{ lang._('Device: /dev/') }}</label></td>
                    <td >
			<select id="device3" name="device" class="form-control">
			</select>
                    </td>
		</tr>
		<tr>
                    <td style="width:22%; vertical-align:top">&nbsp;</td>
                    <td style="width:78%">
			<input type="button" name="submit" class="btn btn-primary" value="{{ lang._('View') }}" id="viewLogsAct" />
                    </td>
		</tr>
            </table>
        </div>
	<pre class="hidden" id="logsMsg">
	</pre>
    </section>

    <section class="col-xs-12">
        <div class="content-box tab-content table-responsive">
            <table class="table table-striped __nomb">
		<tr>
                    <th colspan="2" style="vertical-align:top" class="listtopic">{{ lang._('Abort tests') }}</th>
                </tr>
		<tr>
                    <td><label for="device4">{{ lang._('Device: /dev/') }}</label></td>
                    <td >
			<select id="device4" name="device" class="form-control">
			</select>
                    </td>
		</tr>
		<tr>
                    <td style="width:22%; vertical-align:top">&nbsp;</td>
                    <td style="width:78%">
			<input type="button" name="submit" value="{{ lang._('Abort') }}" class="btn btn-primary" id="abortTestAct" />
                    </td>
		</tr>
            </table>
        </div>
	<pre class="hidden" id="abortTestMsg">
	</pre>
    </section>
</div>
