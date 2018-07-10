{#
 # Copyright (C) 2017 Smart-Soft
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
    $( document ).ready(function() {

        /*************************************************************************************************************
         * link general actions
         *************************************************************************************************************/

        var data_get_map = {'frm_GeneralSettings':"/api/proxysso/settings/get"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // load checklist data
        updateKerberosChecklist();

        $("#RefreshCheckList").click(function() {
            updateKerberosChecklist();
        });

        $("#ShowKeytab").click(function() {
            ajaxCall(url="/api/proxysso/service/showkeytab", sendData={}, callback=function(data,status) {
                $("#kerberos_output").html(data['response']);
            });
        });

        $("#DeleteKeytab").click(function() {
            ajaxCall(url="/api/proxysso/service/deletekeytab", sendData={}, callback=function(data,status) {
                $("#kerberos_output").html(data['response']);
            });
        });

        $("#CreateKeytab").click(function() {
            ajaxCall(
                url="/api/proxysso/service/createkeytab",
                sendData={"admin_login":$("#admin_username").val(), "admin_password":$("#admin_password").val()},
                callback=function(data,status) { $("#kerberos_output").html(data['response']); }
            );
        });

        $("#TestKerbLogin").click(function() {
            ajaxCall(
                url="/api/proxysso/service/testkerblogin",
                sendData={"login":$("#username").val(), "password":$("#password").val()},
                callback=function(data,status) { $("#kerberos_output").html(data['response']); });
        });

        // link save button to API set action
        $("#applyAct").click(function(){
            $("#responseMsg").html('');
            $("#applyAct_progress").addClass("fa fa-spinner fa-pulse");
            $("#applyAct").addClass("disabled");
            saveFormToEndpoint(url="/api/proxysso/settings/set",formid='frm_GeneralSettings',callback_ok=function(){

                ajaxCall(url="/api/proxy/service/reconfigure", sendData={},callback=function(data,status) {
                    if(data.status == "ok") {
                        $("#responseMsg").html("{{lang._('Proxy reconfigured')}}");
                        $("#responseMsg").removeClass("hidden");
                    }

                    $("#applyAct_progress").removeClass("fa fa-spinner fa-pulse");
                    $("#applyAct").removeClass("disabled");
                });
            });
        });
    });

    function showDump(fieldname)
    {
        $("#kerberos_output").html($("#" + fieldname + "_dump").html());
        $("#kerberos_output")[0].scrollIntoView(true);
    }

    function updateKerberosChecklist()
    {
        $("#refresh_progress").addClass("fa fa-spinner fa-pulse");
        $("#RefreshCheckList").addClass("disabled");

        var checklist_get_map = {'frm_CheckList':"/api/proxysso/service/getchecklist"};
        mapDataToFormUI(checklist_get_map).done(function(data){

            $("#refresh_progress").removeClass("fa fa-spinner fa-pulse");
            $("#RefreshCheckList").removeClass("disabled");

            $.each(data.frm_CheckList, function(index, value){

                // clear data
                $("#" + index).html("");
                $(".help-block[id*='" + index + "']").html("");

                if(value.status == "ok") {
                    jQuery('<div/>', {
                        id: index + '_indicator',
                        class: 'fa fa-check-circle text-success',
                    }).appendTo("#" + index);
                    if(value.message) {
                        $(".help-block[id*='" + index + "']").html(value.message);
                    }
                }
                else if(value.status == "failure") {
                    jQuery('<div/>', {
                        id: index + '_indicator',
                        class: 'fa fa-times-circle text-danger',
                    }).appendTo("#" + index);
                    if(value.message) {
                        $(".help-block[id*='" + index + "']").html(value.message);
                    }
                }
                else {
                    $("#" + index).html(value);
                }

                if(value.dump) {
                    jQuery('<div/>', {
                        id: index + '_dump',
                        text: htmlDecode(value.dump),
                        class: 'hidden',
                    }).appendTo(".help-block[id*='" + index + "']");
                    jQuery('<a/>', {
                        text: "{{ lang._('Show dump') }}",
                        href: 'javascript:showDump("' + index + '");',
                        style: 'padding-left: 20px;',
                    }).appendTo("#" + index);
                }
            });
        });
    }

</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">
</div>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general"><b>{{ lang._('General') }}</b></a></li>
    <li><a data-toggle="tab" href="#testing"><b>{{ lang._('Kerberos Authentication') }}</b></a></li>
</ul>

<div class="tab-content content-box">

    <div class="tab-pane fade in active" id="general">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}

        <hr/>
        <button class="btn btn-primary __mb"  id="applyAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="applyAct_progress" class=""></i></button>
    </div>

    <div class="tab-pane fade" id="testing">

        {{ partial("layout_partials/base_form",['fields':checkListForm,'id':'frm_CheckList'])}}
        <hr/>
        <button class="btn btn-primary __mb" id="RefreshCheckList" type="button"><b>{{ lang._('Refresh') }}</b> <i id="refresh_progress" class=""></i></button>

        <div class="__mb">
            {{ partial("layout_partials/base_form",['fields':testingCreateForm,'id':'frm_TestingCreate'])}}
            <button class="btn btn-primary" id="CreateKeytab" type="button"><b>{{ lang._('Create Key Table') }}</b></button>
            <button class="btn btn-primary" id="DeleteKeytab" type="button"><b>{{ lang._('Delete Key Table') }}</b></button>
            <button class="btn btn-primary" id="ShowKeytab" type="button"><b>{{ lang._('Show Key Table') }}</b></button>
        </div>

        {{ partial("layout_partials/base_form",['fields':testingTestForm,'id':'frm_TestingTest'])}}
        <button class="btn btn-primary" id="TestKerbLogin" type="button"><b>{{ lang._('Test Kerberos login') }}</b></button>

        <hr/>
        <p><b>{{ lang._('Output') }}</b></p>
        <pre id="kerberos_output"></pre>
    </div>
</div>
