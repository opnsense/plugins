<script>
    $( document ).ready(function() {
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $("#apply_div").show();
            if (e.target.id == 'policies_tab') {
                $("#grid-policies").UIBootgrid(
                    {   search:'/api/proxy/acl/searchPolicy/',
                        get:'/api/proxy/acl/getPolicy/',
                        set:'/api/proxy/acl/setPolicy/',
                        add:'/api/proxy/acl/addPolicy/',
                        del:'/api/proxy/acl/delPolicy/',
                        toggle:'/api/proxy/acl/togglePolicy/'
                    }
                );
            } else if (e.target.id == 'custom_policies_tab') {
                $("#grid-custom_policies").UIBootgrid(
                    {   search:'/api/proxy/acl/searchCustomPolicy/',
                        get:'/api/proxy/acl/getCustomPolicy/',
                        set:'/api/proxy/acl/setCustomPolicy/',
                        add:'/api/proxy/acl/addCustomPolicy/',
                        del:'/api/proxy/acl/delCustomPolicy/',
                        toggle:'/api/proxy/acl/toggleCustomPolicy/'
                    }
                );
            } else if (e.target.id == 'policy_tester_tab') {
                $("#apply_div").hide();
            }
        });

        $("#reconfigureAct").SimpleActionButton();
        $("#tester_exec").click(function(){
            $("#tester_exec_spinner").show();
            ajaxCall('/api/proxy/acl/test', {'user': $("#tester_name").val(), 'uri': $("#tester_uri").val(), 'src': $("#tester_src").val()}, function(data, status){
                $("#policy_tester_result").empty();
                $("#policy_tester_result").append($("<span/>").text("{{lang._('Result')}}"));
                if (data.user !== undefined || data.message !== undefined) {
                    $("#policy_tester_result").append($("<pre  style='white-space: pre-wrap; word-break: keep-all;'/>").text(JSON.stringify(data, null, 2)));
                } else {
                    $("#policy_tester_result").append($("<span/>").text("-"));
                }
                $("#tester_exec_spinner").hide();
            });
        });

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click();
        } else {
            $('a[href="#policies"]').click();
        }

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        // Extended policies depend on redis
        ajaxGet('/api/redis/service/status', {}, function(data, status){
            if (data.status !== "running") {
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_WARNING,
                    title: "{{ lang._('ACL')}}",
                    message: $("#redis_message").html()
                });
            }
        });

    });
</script>
<style>
  #custom_policy\.content {
      white-space: nowrap;
      height: 300px;
  }
</style>
<div id="redis_message" style="display:none">
    {{ lang._('The Redis service is not active, make sure to configure it via : %s Services -> Redis %s first')|format('<a href="/ui/redis">', '</a>') }}
</div>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" id="policies_tab" href="#policies">{{ lang._('Default Policies') }}</a></li>
    <li><a data-toggle="tab" id="custom_policies_tab" href="#custom_policies">{{ lang._('Custom policies') }}</a></li>
    <li><a data-toggle="tab" id="policy_tester_tab" href="#policy_tester">{{ lang._('Policy tester') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="policies" class="tab-pane fade in">
        <!-- tab page "standard policies" -->
        <table id="grid-policies" class="table table-condensed table-hover table-striped" data-editDialog="DialogDefaultPolicy" data-editAlert="PolicyChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="action" data-type="string">{{ lang._('Action') }}</th>
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
    </div>
    <div id="custom_policies" class="tab-pane fade in">
        <!-- tab page "custom_policies" -->
        <table id="grid-custom_policies" class="table table-condensed table-hover table-striped" data-editDialog="DialogCustomPolicy" data-editAlert="PolicyChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="action" data-type="string">{{ lang._('Action') }}</th>
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
    </div>
    <div id="policy_tester" class="tab-pane fade in">
        <div class="col-md-12">
            <table class="table table-condensed table-striped">
                <thead>
                  <tr>
                    <th>{{ lang._('Property') }}</th>
                    <th>{{ lang._('Value') }}</th>
                  </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ lang._('Username') }}</td>
                        <td><input type="text" id='tester_name'></td>
                    </tr>
                    <tr>
                        <td>{{ lang._('Source') }}</td>
                        <td><input type="text"  id='tester_src'></td>
                    </tr>
                    <tr>
                        <td>{{ lang._('Uri') }}</td>
                        <td><input type="text"  id='tester_uri'></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td>
                            <button class="btn btn-primary" id="tester_exec">
                              {{ lang._('Test') }}
                              <i id="tester_exec_spinner" class="fa fa-spinner fa-pulse" aria-hidden="true" style="display:none;"></i>
                            </button>
                        </td>
                    </tr>
                </tfoot>
              </table>
              <div id="policy_tester_result">
              </div>
            </table>
        </div>
    </div>
    <div class="col-md-12" id="apply_div">
        <div id="PolicyChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/proxy/acl/apply'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error configuring policies') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogDefaultPolicy,'id':'DialogDefaultPolicy','label':lang._('Edit List')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogCustomPolicy,'id':'DialogCustomPolicy','label':lang._('Edit List')])}}
