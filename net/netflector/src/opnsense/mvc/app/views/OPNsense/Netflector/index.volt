{#
 # Copyright (C) 2026 Sergii Bogomolov
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
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

<script>
    $(document).ready(function() {
        var gridParams = {
            search:  '/api/netflector/settings/search_reflector',
            get:     '/api/netflector/settings/get_reflector/',
            set:     '/api/netflector/settings/set_reflector/',
            add:     '/api/netflector/settings/add_reflector/',
            del:     '/api/netflector/settings/del_reflector/',
            toggle:  '/api/netflector/settings/toggle_reflector/'
        };

        $("#grid-reflectors").UIBootgrid(gridParams);

        mapDataToFormUI({'frm_GeneralSettings': "/api/netflector/settings/get"}).done(function() {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('netflector');
        });

        // Both ways an Apply is refused end here. The lines are set as text, not markup: they carry
        // configured values, and the daemon's own wording, back to the page.
        function refuseApply(dfObj, lines) {
            const body = $('<div/>');
            $.each(lines, function(index, line) {
                body.append($('<p/>').text(line));
            });
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DANGER,
                title: '{{ lang._('Netflector') }}',
                message: body,
                buttons: [{
                    label: '{{ lang._('Close') }}',
                    action: function(dialog) { dialog.close(); }
                }]
            });
            dfObj.reject();
        }

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = $.Deferred();
                saveFormToEndpoint("/api/netflector/settings/set", 'frm_GeneralSettings',
                    function() {
                        // The model's rules mirror the daemon's and can drift from them, and the
                        // reconfigure that follows stops the running daemon before starting the new
                        // one. Ask the daemon about the candidate first, so a configuration it
                        // rejects leaves the old one running instead of nothing at all.
                        ajaxCall("/api/netflector/service/check", {}, function(data) {
                            // Only a rejection blocks: "idle" means nothing is enabled, which has to
                            // apply, or the last reflector could never be removed.
                            if (data['status'] === 'failed') {
                                refuseApply(dfObj, (data['message'] || '{{ lang._('The daemon rejected the configuration.') }}').split('\n'));
                            } else {
                                dfObj.resolve();
                            }
                        });
                    },
                    true,
                    function(data) {
                        let messages = [];
                        $.each(data['validations'] || {}, function(field, message) {
                            messages.push(message);
                        });
                        refuseApply(dfObj, messages.length
                            ? messages
                            : ['{{ lang._('The configuration could not be saved.') }}']);
                    }
                );
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('netflector');
            }
        });

        // Ask the daemon to validate the configuration it would actually load. The model's rules mirror
        // the daemon's, but the daemon is the authority.
        $("#checkAct").click(function() {
            $("#checkResult").removeClass("text-danger text-success text-muted").html('...');
            ajaxCall("/api/netflector/service/check", {}, function(data, status) {
                // Three states, three colours. "idle" (switched off, nothing to validate) is not a
                // failure and must not be red.
                var colours = {'ok': 'text-success', 'idle': 'text-muted', 'failed': 'text-danger'};
                $("#checkResult")
                    .addClass(colours[data['status']] || 'text-danger')
                    .text(data['message'] || '');
            });
        });

        updateServiceControlUI('netflector');
    });
</script>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <h2 style="margin-top: 15px;">{{ lang._('General') }}</h2>
        </div>
        {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_GeneralSettings']) }}
    </div>

    <div class="content-box" style="margin-top: 20px;">
        <div class="col-md-12">
            <h2 style="margin-top: 15px;">{{ lang._('Reflectors') }}</h2>
        </div>
        <table id="grid-reflectors" class="table table-condensed table-hover table-striped table-responsive"
               data-editDialog="DialogEdit" data-editAlert="netflectorChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="source_if" data-type="string">{{ lang._('Source') }}</th>
                    <th data-column-id="target_if" data-type="string">{{ lang._('Target') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <!-- What an entry reflects, which otherwise means opening every row in turn.
                         Read-only: the protocols constrain each other (dial needs ssdp and an
                         IPv4-capable family), so a single cell is the wrong place to change one. -->
                    <th data-column-id="wol" data-type="string" data-width="5em" data-formatter="boolean">{{ lang._('WoL') }}</th>
                    <th data-column-id="mdns" data-type="string" data-width="5em" data-formatter="boolean">{{ lang._('mDNS') }}</th>
                    <th data-column-id="ssdp" data-type="string" data-width="5em" data-formatter="boolean">{{ lang._('SSDP') }}</th>
                    <th data-column-id="dial" data-type="string" data-width="5em" data-formatter="boolean">{{ lang._('DIAL') }}</th>
                    <th data-column-id="wsd" data-type="string" data-width="5em" data-formatter="boolean">{{ lang._('WSD') }}</th>
                    <th data-column-id="address_family" data-type="string" data-width="8em">{{ lang._('IP family') }}</th>
                    <th data-column-id="uuid" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td colspan="6"><button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus"></span></button></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <div id="netflectorChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below.') }}
            </div>
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint="/api/netflector/service/reconfigure"
                    data-label="{{ lang._('Apply') }}"
                    data-service-widget="netflector"
                    data-error-title="{{ lang._('Netflector could not be reconfigured') }}"
                    type="button">
                {{ lang._('Apply') }}
            </button>
            <button class="btn" id="checkAct" type="button">{{ lang._('Validate configuration') }}</button>
            <span id="checkResult" style="margin-left: 1em;"></span>
            <br/><br/>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog", ['fields': formDialogEdit, 'id': 'DialogEdit', 'label': lang._('Edit reflector')]) }}
