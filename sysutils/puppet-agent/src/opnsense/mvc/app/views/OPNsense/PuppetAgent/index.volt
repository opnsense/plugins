{#

OPNsense® is Copyright © 2021-2026 Frank Wall
OPNsense® is Copyright © 2021 Jan Winkler
OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
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
    $(document).ready(function() {
        mapDataToFormUI({'frm_GeneralSettings': "/api/puppetagent/settings/get"}).done(function() {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('puppetagent');
        });
        $("#saveAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint(
                    "/api/puppetagent/settings/set",
                    'frm_GeneralSettings',
                    function() {
                        dfObj.resolve();
                    },
                    true,
                    function() {
                        dfObj.reject();
                    }
                );
                return dfObj.promise();
            }
        });
    });
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" id="settings-introduction" href="#subtab_settings-introduction">{{ lang._('Introduction') }}</a></li>
    <li><a data-toggle="tab" id="settings-tab" href="#settings">{{ lang._('Settings') }}</a></li>
</ul>

<div class="content-box tab-content">

    <div id="subtab_settings-introduction" class="tab-pane fade in active">
        <div class="col-md-12">
            <h1>{{ lang._('Quick Start Guide') }}</h1>
            <p>{{ lang._("Welcome to the Puppet Agent plugin! This plugin allows you to integrate OPNsense with your Puppet/OpenVox environment.") }}</p>
            <p>{{ lang._("Keep in mind that you should not treat OPNsense like any other operating system. Most notably you should not modify system files or packages. Instead use the OPNsense API to make configuration changes and to manage plugins. The following tools are a good starting point when trying to automate OPNsense with Puppet:") }}</p>
            <ul>
              <li>{{ lang._("%sopn_api:%s A command line client to configure OPNsense core and plugin components through their respective APIs.") | format('<a href="https://github.com/markt-de/opn-api" target="_blank">', '</a>') }}</li>
              <li>{{ lang._("%spuppet/opn:%s A read-to-use Puppet module for automating the OPNsense firewall.") | format('<a href="https://github.com/markt-de/puppet-opn" target="_blank">', '</a>') }}</li>
            </ul>
            <p>{{ lang._("Note that these tools are not directly related to this plugin. Please report issues and missing features directly to the author.") }}</p>
        </div>
    </div>

    <div id="settings" class="tab-pane fade">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}

        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary"  id="saveAct"
                data-endpoint='/api/puppetagent/service/reconfigure'
                data-label="{{ lang._('Save') }}"
                data-service-widget="puppetagent"
                data-error-title="{{ lang._('Error reconfiguring puppetagent') }}"
                type="button">
            </button>
        </div>
    </div>

</div>
