import KeyValueMapField from './controller/KeyValueMapField';
import UpstreamCollection from './models/UpstreamCollection';
import {
    KeyValueMapFieldEntryACL,
    KeyValueMapFieldEntryUpstreamMap} from "./controller/KeyValueMapFieldEntry";
import SNIHostnameUpstreamCollection from "./models/SNIHostnameUpstreamCollection";
import SNIHostnameUpstreamModel from "./models/SNIHostnameUpstreamModel";
import IPACLModel from "./models/IPACLModel";
import IPACLCollection from "./models/IPACLCollection";

const uc = new UpstreamCollection();
const actioncollection = new Backbone.Collection([
    {
        'name': 'Deny',
        'value': 'deny'
    },
    {
        'name': 'Allow',
        'value': 'allow'
    }
]);

function bind_save_buttons() {
// form save event handlers for all defined forms
    $('[id*="save_"]').each(function () {
        $(this).click(function () {
            let frm_id = $(this).closest("form").attr("id");
            let frm_title = $(this).closest("form").attr("data-title");
            // save data for General TAB
            saveFormToEndpoint("/api/nginx/settings/set", frm_id, function () {
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#" + frm_id + "_progress").addClass("fa fa-spinner fa-pulse");

                ajaxCall("/api/nginx/service/reconfigure", {}, function (data, status) {
                    // when done, disable progress animation.
                    $("#" + frm_id + "_progress").removeClass("fa fa-spinner fa-pulse");

                    if (data !== undefined && (status !== "success" || data['status'] !== 'ok')) {
                        // fix error handling
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: frm_title,
                            message: JSON.stringify(data),
                            draggable: true
                        });
                    } else {
                        updateServiceControlUI('nginx');
                    }
                });
            });
        });
    });
}

function init_grids() {
    ['upstream',
        'upstreamserver',
        'location',
        'credential',
        'userlist',
        'httpserver',
        'streamserver',
        'httprewrite',
        'custompolicy',
        'security_header',
        'ipacl',
        'limit_zone',
        'cache_path',
        'limit_request_connection',
        'snifwd',
        'tls_fingerprint',
        'syslog_target',
        'naxsirule'].forEach(function (element) {
        $("#grid-" + element).UIBootgrid(
            {
                'search': '/api/nginx/settings/search' + element,
                'get': '/api/nginx/settings/get' + element + '/',
                'set': '/api/nginx/settings/set' + element + '/',
                'add': '/api/nginx/settings/add' + element + '/',
                'del': '/api/nginx/settings/del' + element + '/',
                'options': {selection: false, multiSelect: false}
            }
        );
    });
}

function initSNIFieldComponent() {
    let snifield = new KeyValueMapField({
        dataField: document.getElementById('snihostname.data'),
        upstreamCollection: uc,
        entryclass: KeyValueMapFieldEntryUpstreamMap,
        collection: new SNIHostnameUpstreamCollection(),
        createModel: function () {
            return new SNIHostnameUpstreamModel({
                hostname: 'localhost',
            });
        }
    });
    window.snifield = snifield;
    snifield.render();
    $("#grid-upstream").on("loaded.rs.jquery.bootgrid", function () {
        /* we always have to reload too after bootgrid reloads */
        uc.fetch();
    });
    uc.fetch();
}

$( document ).ready(function() {

    let data_get_map = {'frm_nginx':'/api/nginx/settings/get'};

    // load initial data
    mapDataToFormUI(data_get_map).done(function(){
        formatTokenizersUI();
        $('select[data-allownew="false"]').selectpicker('refresh');
        updateServiceControlUI('nginx');
    });

    // update history on tab state and implement navigation
    if(window.location.hash !== "") {
        $('a[href="' + window.location.hash + '"]').click();
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });

    $('.reload_btn').click(function() {
        $(".reloadAct_progress").addClass("fa-spin");
        ajaxCall("/api/nginx/service/reconfigure", {}, function() {
            $(".reloadAct_progress").removeClass("fa-spin");
        });
    });


    bind_save_buttons();
    init_grids();
    bind_naxsi_rule_dl_button();
    initSNIFieldComponent();
    let ipaclfield = new KeyValueMapField({
        dataField: document.getElementById('ipacl.data'),
        upstreamCollection: actioncollection,
        entryclass: KeyValueMapFieldEntryACL,
        collection: new IPACLCollection(),
        createModel: function () {
            return new IPACLModel({
                network: '::',
                action: 'deny'
            });
        }
    });
    window.ipaclfield = ipaclfield;
    ipaclfield.render();
});
