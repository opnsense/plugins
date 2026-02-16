{#

OPNsense® is Copyright © 2025 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script>
    $( document ).ready(function() {
        let data_get_map = {'frm_settings':"/api/q_feeds/settings/get"};
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/q_feeds/settings/set", 'frm_settings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'feeds_tab') {
                if (!$("#grid-feeds").hasClass('tabulator')) {
                    $("#grid-feeds").UIBootgrid({
                        'search': '/api/q_feeds/settings/search_feeds/'
                    });
                } else {
                    $("#grid-feeds").bootgrid("reload");
                }
            } else if (e.target.id === 'events_tab') {
                if (!$("#grid-events").hasClass('tabulator')) {
                    $("#grid-events").UIBootgrid({
                        'search': '/api/q_feeds/settings/search_events/',
                        'options': {
                            formatters: {
                                'source': function(column, row) {
                                    if (!row.source) return '';
                                    return `<div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>${row.source}</span>
                                        <button type="button" class="btn btn-xs btn-default threat-lookup-btn bootgrid-tooltip"
                                                data-ip="${row.source}"
                                                title="Lookup Source IP in Threat Intelligence Portal">
                                            <span class="fa fa-fw fa-search"></span>
                                        </button>
                                    </div>`;
                                },
                                'destination': function(column, row) {
                                    if (!row.destination) return '';
                                    return `<div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>${row.destination}</span>
                                        <button type="button" class="btn btn-xs btn-default threat-lookup-btn bootgrid-tooltip"
                                                data-ip="${row.destination}"
                                                title="Lookup Destination IP in Threat Intelligence Portal">
                                            <span class="fa fa-fw fa-search"></span>
                                        </button>
                                    </div>`;
                                }
                            }
                        }
                    });

                    // Add click handler for threat lookup button
                    $(document).on('click', '.threat-lookup-btn', function() {
                        const ip = $(this).data('ip');
                        if (ip) {
                            const tipUrl = 'https://tip.qfeeds.com/views/threat-lookup/index.php?q=' + encodeURIComponent(ip);
                            window.open(tipUrl, '_blank');
                        }
                    });
                } else {
                    $("#grid-events").bootgrid("reload");
                }
            }
        });

        $("#connect\\.general\\.enable_unbound_bl").change(function(){
            if ($(this).is(':checked')) {
                $(".unbound_options").closest('table').show();
            } else {
                $(".unbound_options").closest('table').hide();
            }
        });

        let selected_tab = window.location.hash != "" ? window.location.hash : "#settings";
        $('a[href="' +selected_tab + '"]').tab('show');
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });

    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#feeds" id="feeds_tab">{{ lang._('Feeds') }}</a></li>
    <li><a data-toggle="tab" href="#events" id="events_tab">{{ lang._('Events') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="settings"  class="tab-pane fade in">
        {{ partial("layout_partials/base_form",['fields':formSettings,'id':'frm_settings'])}}
    </div>
    <div id="feeds"  class="tab-pane fade in">
        <table id="grid-feeds" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                    <th data-column-id="updated_at" data-type="string">{{ lang._('Updated at') }}</th>
                    <th data-column-id="next_update" data-type="string">{{ lang._('Next update') }}</th>
                    <th data-column-id="licensed" data-type="boolean" data-formatter="boolean">{{ lang._('Licensed') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div id="events"  class="tab-pane fade in">

        <table id="grid-events" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="timestamp" data-type="string">{{ lang._('Timestamp') }}</th>
                    <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="direction" data-type="string">{{ lang._('Direction') }}</th>
                    <th data-column-id="source" data-type="string" data-formatter="source">{{ lang._('Source') }}</th>
                    <th data-column-id="source_port" data-type="string">{{ lang._('Source Port') }}</th>
                    <th data-column-id="destination" data-type="string" data-formatter="destination">{{ lang._('Destination') }}</th>
                    <th data-column-id="destination_port" data-type="string">{{ lang._('Destination Port') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div class="pull-right">{{ lang._('Collected events from the firewall log for QFeed aliases') }}</div>
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/q_feeds/settings/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring QFeeds connect') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>
