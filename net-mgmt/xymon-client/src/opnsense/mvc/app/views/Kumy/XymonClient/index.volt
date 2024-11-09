<script type="text/javascript">
    $(document).ready(function() {
        function spinStart(selector) {
            $(selector).addClass("fa fa-spinner fa-pulse");
        }
        function spinStop(selector) {
            $(selector).removeClass("fa fa-spinner fa-pulse");
        }

        mapDataToFormUI({"frm_Settings": "/api/xymonclient/settings/get"}).done(function(data) {
            if ('frm_Settings' in data) {
                updateServiceControlUI('xymonclient');
                formatTokenizersUI();
            }
        });

        // Handle save button
        $("body").on("click", ".save-button", function() {
            spinStart("#button-save_progress");
            saveFormToEndpoint("/api/xymonclient/settings/set", "frm_Settings", function() {
                ajaxCall("/api/xymonclient/service/reload")
                .done(function() {
                    setTimeout(function() {
                        window.location.reload(true)
                    }, 300);
                })
                .always(function() {
                    spinStop("#button-save_progress");
                });
            });
        });
    });
</script>

{%- macro action_button(button, text, class="btn-default") %}
    <button class="btn btn-{{ class }} {{ button }}-button" type="button">
        <b>{{ text }}</b>
        <i id="button-{{ button }}_progress"></i>
    </button>
{%- endmacro %}

<section class="page-content-main">
    <div class="content-box">
        {{ partial("layout_partials/base_form", ["fields": formSettings, "id": "frm_Settings"]) }}
    </div>
    <br><br>

    <div class="content-box">
        <div class="col-md-12">
            <br/>
            {{ action_button("save", lang._("Save"), "primary") }}
            <br/><br/>
        </div>
    </div>
</section>
