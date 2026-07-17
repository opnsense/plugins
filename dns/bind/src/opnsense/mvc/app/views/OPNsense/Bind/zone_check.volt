<style>
    #zone-content {
        overflow-x: auto;
    }
    #zone-table {
        display: grid;
        max-height: 400px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        padding-right: 20px;
    }
    .l-number {
        position: relative;
        width: 1%;
        min-width: 40px;
        padding-right: 20px;
        padding-left: 1px;
        font-family: ui-monospace,monospace;
        text-align: right;
        white-space: nowrap;
        vertical-align: top;
        user-select: none;
        filter: brightness(2.0);
        filter: contrast(0.3);
    }
    .long-str {
        word-break: break-word;
    }
    .copy-button {
        display: none;
    }
</style>
<script>
function zone_test(zonename) {
    let payload = {
        'zone': zonename,
    };
    ajaxCall(url = "/api/bind/general/zonetest/", payload, callback = function(data, status) {
        if (data['response'].indexOf('Zone check completed successfully') == -1) {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DANGER,
                closeByBackdrop: false,
                title: "{{ lang._('Zone check failed') }}",
                message: data['response'],
                buttons: [{
                        label: "{{ lang._('Show zone content') }}",
                        action: function(dlg) {
                            $(this).closest(".modal-dialog").find("div.bootstrap-dialog-body").append('<div id="zone-wait">{{ lang._("Loading zone content..") }}<div>');
                            zone_show(payload);
                        },
                    },
                    {
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        },
                    }
                ]
            });
        } else {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: "{{ lang._('Zone check completed successfully') }}",
                message: data['response'],
                buttons: [{
                        label: "{{ lang._('Show zone content') }}",
                        action: function(dlg) {
                            $(this).closest(".modal-dialog").find("div.bootstrap-dialog-body").append('<div id="zone-wait">{{ lang._("Loading zone content..") }}<div>');
                            zone_show(payload);
                        },
                    },
                    {
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        },
                    }
                ]
            });
        }
    });
}

function zone_show(payload) {
    ajaxCall(url = "/api/bind/general/zoneshow/", payload, callback = function(data, status) {
        if (data['time'] && data['zone_content']) {
            $("#zone-wait").remove();
            let L = 0;
            let content = [];
            content.push('<tr><td class="l-number"></td><td class="conf-line">; zone file dump from ' + data['path'] + '</td></tr>');
            content.push('<tr><td class="l-number"></td><td class="conf-line">; zone file created at ' + data['time'] + '</td></tr>');
            $.each(data['zone_content'], function(index, line) {
                L += 1;
                content.push('<tr><td class="l-number">' + L.toString() + '</td><td class="conf-line">' + line + '</td></tr>');
            });
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: "{{ lang._('Zone loaded successfully') }}",
                message: '<div id="zone-content"><table><tbody id="zone-table">' + content.join('') + '</tbody></table></div>',
                onshown: function(dialogRef) {
                    if ((typeof navigator.clipboard === 'object') && (typeof navigator.clipboard.writeText === 'function')) {
                        $(".copy-button").show();
                    }
                },
                buttons: [{
                        label: '<i id="copy-progress" class="fa fa-spinner fa-pulse" style="display: none"></i> {{ lang._("Copy to clipboard") }}',
                        cssClass: 'copy-button',
                        action: function() {
                            zone_copy();
                        }
                    },
                    {
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        }
                    }
                ]
            });
        } else {
            $("#zone-wait").text("{{ lang._('Empty response from the backend. Please check logs.') }}");
        }
    });
}

function zone_copy(dlg) {
    $('#copy-progress').show();
    let conf_to_clipboard = [];
    $('.conf-line').each(function() {
        conf_to_clipboard.push($(this).text())
    });
    navigator.clipboard.writeText(conf_to_clipboard.join('\n'));
    setTimeout(() => {
        $("#copy-progress").hide();
    }, 1000);
}
</script>
