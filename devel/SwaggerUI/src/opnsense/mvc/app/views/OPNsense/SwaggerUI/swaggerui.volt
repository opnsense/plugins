<link rel="stylesheet" type="text/css" href="/ui/css/swaggerui/swagger-ui.css" >
<script src="/ui/js/swaggerui/swagger-ui-bundle.js"></script>
<script src="/ui/js/swaggerui/swagger-ui-standalone-preset.js"></script>

<style>
    .swagger-ui {
        margin:0;
        background: #fafafa;
    }

    div.topbar-wrapper > a.link > img[width="30"] {
                    display: none
    }
    div.opblock-body > img {
                    display: none
    }

/*    *,
    *:before,
    *:after
    {
        box-sizing: inherit;
    }
*/
    {% if hideServers %}
        .global-server-container {
            display: none
        }
    {% endif %}

    // hide section from view but scheme-container must remain visible for the login popup to work
    {% if hideAuthorize %}
        .authorize {
            display: none
        }
        .scheme-container {
	    position: absolute;
            top: -9999px;
            left: -9999px;
            // clip-path: polygon(0px 0px,0px 0px,0px 0px,0px 0px);
        }
    {% endif %}

    {% if hideTitlebar %}
        .topbar {
            display: none
        }
    {% endif %}

//      .download-url-input {
//         display: none
//      }

    {% if hideHeader %}
        .information-container {
            display: none
        }
    {% endif %}
</style>

{% if debug %}
    {{ data }}
{% endif %}

<!-- The Swagger IO container -->
    <div id="swagger-ui" class="container-fluid"></div>

<!-- The constructor for the Swagger IO container -->
<script>
    window.onload = function() {

         // Custom plugin to hide the API definition URL
        const HideInfoUrlPartsPlugin = () => {
            return {
                wrapComponents: {
                    InfoUrl: () => () => null
                }
            }
        }  

         // Custom plugin to disable and hide the Try-out buttons
        const DisableTryItOutPlugin = function() {
            return {
                statePlugins: {
                    spec: {
                        wrapSelectors: {
                            allowTryItOutFor: () => () => false
                        }
                    }
                }
            }
        }

        // Build a system
        const ui = SwaggerUIBundle({
            validatorUrl: null,             // Prevent XSS
            url: "{{ json }}",              // Link to api
            dom_id: '#swagger-ui',

            {% if collapsed %}
                docExpansion: 'none',
            {% endif %}

            booleanValues: Array(0, 1),
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                // DisableTryItOutPlugin,     // Apply this plugin
                // HideInfoUrlPartsPlugin,    // Apply this plugin
                SwaggerUIBundle.plugins.DownloadUrl
             ],
             layout: "StandaloneLayout"
        })
        window.ui = ui
    }
</script>
