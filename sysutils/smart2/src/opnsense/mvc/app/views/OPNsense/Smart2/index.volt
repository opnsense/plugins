<script type="text/javascript">
 $( document ).ready(function () {
     ajaxCall (url="/api/smart2/service/list", sendData={}, callback=function(data, status) {
	 // action to run after reload
	 var devices = data['devices'];

	 if (Array.isArray(devices) && devices.length > 0) {
	     $("#noDevicesMsg").addClass("hidden");
	 } else {
	     $("#noDevicesMsg").removeClass("hidden");
	 }
     })
 });
</script>

<div class="alert alert-info hidden" role="alert" id="noDevicesMsg">
{{ lang._('No SMART devices.') }}
</div>
