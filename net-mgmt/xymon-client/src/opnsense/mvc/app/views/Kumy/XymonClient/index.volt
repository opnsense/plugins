<script type="text/javascript">
    $(function() {
        mapDataToFormUI({"frm_Settings": "/api/xymonclient/settings/get"}).done(function(data) {
            if ('frm_Settings' in data) {
                updateServiceControlUI('xymonclient');
                formatTokenizersUI();
            }
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/xymonclient/settings/set", "frm_Settings", function() {
                    dfObj.resolve();
                });
                return dfObj;
            }
        });
    });
</script>

<section class="page-content-main">
    <div class="content-box">
        {{ partial("layout_partials/base_form", ["fields": formSettings, "id": "frm_Settings"]) }}
    </div>
    <br><br>

    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/xymonclient/settings/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-service-widget="xymonclient"
                    data-error-title="{{ lang._('Error reconfiguring Xymon Client') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>
