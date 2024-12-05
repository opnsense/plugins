{#
# Copyright (C) 2024 Volodymyr Paprotski
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification,
# are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

    $( document ).ready(function() {
        updateServiceControlUI('stepca');

        $("#grid-addresses").UIBootgrid(
            {   search:'/api/stepca/settings/searchItem/',
                get:'/api/stepca/settings/getItem/',
                set:'/api/stepca/settings/setItem/',
                add:'/api/stepca/settings/addItem/',
                del:'/api/stepca/settings/delItem/',
                toggle:'/api/stepca/settings/toggleItem/'
            }
        );

        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            saveFormToEndpoint(url="/api/stepca/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                ajaxCall(url="/api/stepca/service/reconfigure", sendData={}, callback=function(data,status) {
                    // when done, disable progress animation.
                    $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
                    updateServiceControlUI('stepca');
                    if (status != "success" || data['status'] != 'ok') {
                        stdDialogInform("{{ lang._('Error reconfiguring StepCA') }}", data['status'], 'warning');
                    }
                });
            });
        });

        $("#provisioner\\.Provisioner").change(function() {
            var typeProv = this.value;
            const rowSelector = "tr[id^=row_provisioner\\.]";

            $(".prov-hide").parents(rowSelector).hide();
            if (typeProv == 'acme') {
                $('.acme').parents(rowSelector).show();
            } else if (typeProv == 'jwt') {
                $('.jwt').parents(rowSelector).show();
            }
        });

        $("#provisioner\\.CreateTemplate").css({"font-family": "'Courier New'"});
    });

</script>


<table id="grid-addresses" class="table table-condensed table-hover table-striped" data-editDialog="DialogAddress">
    <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="Enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
            <th data-column-id="Provisioner" data-type="string" data-formatter="provisioner_type">{{ lang._('Provisioner') }}</th>
            <th data-column-id="Name" data-type="string">{{ lang._('Name') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
            </td>
        </tr>
    </tfoot>
</table>
<div class="col-md-12">
    <hr/>
    <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
    <br/><br/>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogAddress,'id':'DialogAddress','label':lang._('Edit Provisioner')])}}
