<script>
    $( document ).ready(function() {
        mapDataToFormUI({'frm_GeneralSettings':"/api/openapi/settings/get"}).done(function(data){
            // place actions to run after load, for example update form styles.
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint("/api/openapi/settings/set", 'frm_GeneralSettings', function(){
                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/openapi/service/reconfigure", sendData={}, callback=function(data,status) {
                    // action to run after reload
                });
            });
        });

        // use a SimpleActionButton() to call /api/openapi/service/test
        $("#testAct").SimpleActionButton({
            onAction: function(data) {
                $("#responseMsg").removeClass("hidden").html(data['message']);
            }
        });
    });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">

</div>

<div class="content-box __mb">
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/openapi/service/reconfigure'}) }}
