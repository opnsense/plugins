{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 David Harrigan

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

{% set networksFirstRow = true, peersFirstRow = true %}

<script>
    $(document).ready(function() {
        $("#network_details_collapse_all").click(function() {
            $(".network_details").collapse('toggle');
        });

        $("#peer_details_collapse_all").click(function() {
            $(".peer_details").collapse('toggle');
        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li id="ztInformation" class="active"><a data-toggle="tab" href="#information">{{ lang._('Information') }}</a></li>
    <li id="ztNetworks"><a data-toggle="tab" href="#networks">{{ lang._('Networks') }}</a></li>
    <li id="ztPeers"><a data-toggle="tab" href="#peers">{{ lang._('Peers') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="information" class="tab-pane fade in active">
        <table class="table table-striped">
            <tbody>
                {% for key, value in information %}
                {% set value = value | default('null') %}
                <tr>
                    <td style="width:22%">{{ key | e }}</td>
                    {% if value is type ('boolean') %}
                        <td style="width:78%">{{ value ? 'true' : 'false' }}</td>
                    {% elseif key == "config" %}
                        {% set config = value %}
                        {% set settings = config["settings"] %}
                        <td style="width:78%">
                            <table class="table">
                                <tr>
                                    <td><b>{{ lang._('physical') }}</b></td>
                                    <td colspan="4">{{ config["physical"] ? 'true' : 'false' }}</td>
                                </tr>
                                <tr>
                                    <td><b>{{ lang._('settings') }}</b></td>
                                    <td><b>{{ lang._('portMappingEnabled') }}</b></td>
                                    <td><b>{{ lang._('primaryPort') }}</b></td>
                                    <td><b>{{ lang._('softwareUpdate') }}</b></td>
                                    <td><b>{{ lang._('softwareUpdateChannel') }}</b></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                    <td>{{ settings["portMappingEnabled"] ? 'true' : 'false' }}</td>
                                    <td>{{ settings["primaryPort"] | default('null') | e }}</td>
                                    <td>{{ settings["softwareUpdate"] | default('null') | e }}</td>
                                    <td>{{ settings["softwareUpdateChannel"] | default('null') | e }}</td>
                                </tr>
                            </table>
                        </td>
                    {% else %}
                        <td style="width:78%">{{ value | e}}</td>
                    {% endif %}
                </tr>
                {% elsefor %}
                    <tr><td>{{ lang._('Unable to retrieve Zerotier information. Is the service enabled and is there internet connectivity?') }}</td></tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
    <div id="networks" class="tab-pane fade in">
        <section class="page-content-main">
            <div class="container-fluid">
                <section class="col-xs-12">
                    {% for network in networks %}
                    <div class="tab-content content-box col-xs-12 __mb">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th colspan="2">
                                            <i class="fa fa-chevron-down" style="cursor: pointer;" data-toggle="collapse" data-target="#{{ network['nwid'] | e }}"></i>
                                            {{ lang._('Network Id') }} : {{ network['nwid'] | e }} ({{ network['name'] | e }})
                                            {% if networksFirstRow == true %}
                                            {% set networksFirstRow = false %}
                                            <div class="pull-right">
                                                <i class="fa fa-expand" id="network_details_collapse_all" style="cursor: pointer;" data-toggle="tooltip" title="{{ lang._('collapse/expand all') }}"></i> &nbsp;
                                            </div>
                                            {% endif %}
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="network_details collapse table-responsive" id="{{ network['nwid'] | e }}">
                            <table class="table table-striped">
                                <tbody>
                                    {% for key, value in network %}
                                    {% set value = value | default('null') %}
                                    <tr>
                                        <td style="width:22%">{{ key | e }}</td>
                                        {% if value is type('boolean') %}
                                            <td style="width:78%">{{ value == true ? 'true' : 'false' }}</td>
                                        {% elseif value is iterable %}
                                            <td style="width:78%">
                                                <table class="table">
                                                    {% if key == "assignedAddresses" %}
                                                        <thead>
                                                            <tr>
                                                                <td>{{ lang._('Addresses') }}</td
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {% if value|length > 0 %}
                                                                {% for index in 0..value|length - 1 %}
                                                                    <tr>
                                                                        <td>{{ value[index] | e }}</td>
                                                                    </tr>
                                                                {% endfor %}
                                                            {% else %}
                                                                <tr>
                                                                    <td>{{ lang._('No addresses currently defined.') }}</td>
                                                                </tr>
                                                            {% endif %}
                                                        <tbody>
                                                    {% elseif key == "routes" %}
                                                        <thead>
                                                            <tr>
                                                                <td>{{ lang._('target') }}</td>
                                                                <td>{{ lang._('via') }}</td>
                                                                <td>{{ lang._('metric') }}</td>
                                                                <td>{{ lang._('flags') }}</td>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {% if value|length > 0 %}
                                                                {% for index in 0..value|length - 1 %}
                                                                    {% set route = value[index] %}
                                                                    <tr>
                                                                        <td>{{ route["target"] | e }}</td>
                                                                        <td>{{ route["via"] | e }}</td>
                                                                        <td>{{ route["metric"] | e }}</td>
                                                                        <td>{{ route["flags"] | e }}</td>
                                                                    </tr>
                                                                {% endfor %}
                                                            {% else %}
                                                                <tr><td colspan="4">{{ lang._('No routes currently defined.') }}</td></tr>
                                                            {% endif %}
                                                        </tbody>
                                                    {% endif %}
                                                </table>
                                            </td>
                                        {% else %}
                                            <td style="width:78%">{{ value | e }}</td>
                                        {% endif %}
                                    </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    {% elsefor %}
                        {{ lang._('Unable to retrieve Zerotier network information. Is the service enabled, do you have enabled networks and is there internet connectivity?') }}
                    {% endfor %}
                </section>
            </div>
        </section>
    </div>
    <div id="peers" class="tab-pane fade in">
        <section class="page-content-main">
            <div class="container-fluid">
                <section class="col-xs-12">
                    {% for peer in peers %}
                    <div class="tab-content content-box col-xs-12 __mb">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th colspan="2">
                                            <i class="fa fa-chevron-down" style="cursor: pointer;" data-toggle="collapse" data-target="#{{ peer['address'] | e }}"></i>
                                            {{ lang._('Peer') }} : {{ peer['address'] | e }} ({{ peer['role'] | e }})
                                            {% if peersFirstRow == true %}
                                            {% set peersFirstRow = false %}
                                            <div class="pull-right">
                                                <i class="fa fa-expand" id="peer_details_collapse_all" style="cursor: pointer;" data-toggle="tooltip" title="{{ lang._('collapse/expand all') }}"></i> &nbsp;
                                            </div>
                                            {% endif %}
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="peer_details collapse table-responsive" id="{{ peer['address'] | e }}">
                            <table class="table table-striped">
                                <tbody>
                                    {% for key, value in peer %}
                                    {% set value = value | default('null') %}
                                    <tr>
                                        <td style="width:22%">{{ key | e }}</td>
                                        {% if value is type('boolean') %}
                                            <td style="width:78%">{{ value == true ? 'true' : 'false' }}</td>
                                        {% elseif value is iterable %}
                                            <td style="width:78%">
                                                <table class="table">
                                                    {% if key == "paths" %}
                                                        <thead>
                                                            <tr>
                                                                <td>{{ lang._('active') }}</td>
                                                                <td>{{ lang._('address') }}</td>
                                                                <td>{{ lang._('expired') }}</td>
                                                                <td>{{ lang._('lastReceive') }}</td>
                                                                <td>{{ lang._('lastSend') }}</td>
                                                                <td>{{ lang._('linkQuality') }}</td>
                                                                <td>{{ lang._('preferred') }}</td>
                                                                <td>{{ lang._('trustedPathId') }}</td>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {% if value|length > 0 %}
                                                                {% for index in 0..value|length - 1 %}
                                                                    {% set path = value[index] %}
                                                                    <tr>
                                                                        <td>{{ path["active"] | e }}</td>
                                                                        <td>{{ path["address"] | e }}</td>
                                                                        <td>{{ path["expired"] ? 'true' : 'false' }}</td>
                                                                        <td>{{ path["lastReceive"] | e }}</td>
                                                                        <td>{{ path["lastSend"] | e }}</td>
                                                                        <td>
                                                                        {% if path["linkQuality"] is defined %}
                                                                            {{ path["linkQuality"] | e }}
                                                                        {% endif %}
                                                                        </td>
                                                                        <td>{{ path["preferred"] | e }}</td>
                                                                        <td>{{ path["trustedPathId"] | e }}</td>
                                                                    </tr>
                                                                {% endfor %}
                                                            {% else %}
                                                                <tr>
                                                                    <td colspan="4">{{ lang._('No routes currently defined.') }}</td>
                                                                </tr>
                                                            {% endif %}
                                                        </tbody>
                                                    {% endif %}
                                                </table>
                                            </td>
                                        {% else %}
                                            <td style="width:78%">{{ value | e }}</td>
                                        {% endif %}
                                    </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    {% elsefor %}
                        {{ lang._('Unable to retrieve Zerotier peer information. Is the service enabled and is there internet connectivity?') }}
                    {% endfor %}
                </section>
            </div>
        </section>
    </div>
</div>
