<script type="text/javascript">
    $( document ).ready(function() {
        var data_get_map = {'frm_GeneralSettings':"/api/radsecproxy/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
        });
        
        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/radsecproxy/general/set",formid='frm_GeneralSettings',callback_ok=function(){
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/radsecproxy/service/reconfigure", sendData={},callback=function(data,status) {
                    // action to run after reload
                });
            });
        });
        
    });
</script>

{{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}

<div class="col-md-12">
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
</div>
