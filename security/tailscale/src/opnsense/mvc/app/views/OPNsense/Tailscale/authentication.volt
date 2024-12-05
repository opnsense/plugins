<script type="text/javascript">
    $( document ).ready(function() {
        mapDataToFormUI({'frmAuthentication':"/api/tailscale/authentication/get"}).done(function(data) {
            // place actions to run after load, for example update form styles.
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/tailscale/authentication/set", 'frmAuthentication', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
        });

    });
</script>

<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':authenticationForm,'id':'frmAuthentication']) }}
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="keaChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/tailscale/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring Tailscale') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>
