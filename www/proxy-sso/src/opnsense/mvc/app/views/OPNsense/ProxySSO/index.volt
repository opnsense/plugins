<script type="text/javascript">
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
            ajaxCall(url="/api/proxysso/service/createkeytab", sendData={
                        "admin_login":$("#admin_username").val(),
                        "admin_password":$("#admin_password").val()}, callback=function(data,status) {
                $("#kerberos_output").html(data['response']);
            });
        });

        $("#TestKerbLogin").click(function() {
            ajaxCall(url="/api/proxysso/service/testkerblogin", sendData={
                        "login":$("#username").val(),
                        "password":$("#password").val()}, callback=function(data,status) {
                $("#kerberos_output").html(data['response']);
            });
        });

        // link save button to API set action
        $("#applyAct").click(function(){
            $("#responseMsg").html('');
            $("#applyAct_progress").addClass("fa fa-spinner fa-pulse");
            $("#applyAct").addClass("disabled");
            saveFormToEndpoint(url="/api/proxysso/settings/set",formid='frm_GeneralSettings',callback_ok=function(){

                ajaxCall(url="/api/proxysso/service/reconfigure", sendData={},callback=function(data,status) {
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
                $(".help-block[for='" + index + "']").html("");

                if(value.status == "ok") {
                    jQuery('<div/>', {
                        id: index + '_indicator',
                        class: 'fa fa-check-circle text-success',
                    }).appendTo("#" + index);
                    if(value.message) {
                        $(".help-block[for='" + index + "']").html(value.message);
                    }
                }
                else if(value.status == "failure") {
                    jQuery('<div/>', {
                        id: index + '_indicator',
                        class: 'fa fa-times-circle text-danger',
                    }).appendTo("#" + index);
                    if(value.message) {
                        $(".help-block[for='" + index + "']").html(value.message);
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
                    }).appendTo(".help-block[for='" + index + "']");
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
        <button class="btn btn-primary"  id="applyAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="applyAct_progress" class=""></i></button>
    </div>

    <div class="tab-pane fade" id="testing">

        {{ partial("layout_partials/base_form",['fields':checkListForm,'id':'frm_CheckList'])}}
        <hr/>
        <button class="btn btn-primary" id="RefreshCheckList" type="button"><b>{{ lang._('Refresh') }}</b> <i id="refresh_progress" class=""></i></button>

        {{ partial("layout_partials/base_form",['fields':testingCreateForm,'id':'frm_TestingCreate'])}}
        <button class="btn btn-primary" id="CreateKeytab" type="button"><b>{{ lang._('Create keytab') }}</b></button>
        <button class="btn btn-primary" id="DeleteKeytab" type="button"><b>{{ lang._('Delete keytab') }}</b></button>
        <button class="btn btn-primary" id="ShowKeytab" type="button"><b>{{ lang._('Show keytab') }}</b></button>
        <br/>
        <br/>

        {{ partial("layout_partials/base_form",['fields':testingTestForm,'id':'frm_TestingTest'])}}
        <button class="btn btn-primary" id="TestKerbLogin" type="button"><b>{{ lang._('Test Keberos login') }}</b></button>
        <br/>
        <br/>

        <hr/>
        <p><b>{{ lang._('Output') }}</b></p>
        <pre id="kerberos_output"></pre>
        <br/>
    </div>
</div>
