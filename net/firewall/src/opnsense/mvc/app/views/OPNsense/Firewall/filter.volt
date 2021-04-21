<script>
    $( document ).ready(function() {
        let initial_load = true;
        let grid = $("#grid-rules").UIBootgrid({
            search:'/api/firewall/{{ruleController}}/searchRule/',
            get:'/api/firewall/{{ruleController}}/getRule/',
            set:'/api/firewall/{{ruleController}}/setRule/',
            add:'/api/firewall/{{ruleController}}/addRule/',
            del:'/api/firewall/{{ruleController}}/delRule/',
            toggle:'/api/firewall/{{ruleController}}/toggleRule/'
        });

        // open edit dialog when opened with a uuid reference
        if (window.location.hash !== "" && window.location.hash.split("-").length >= 4) {
            grid.on('loaded.rs.jquery.bootgrid', function(){
                if (initial_load) {
                    $(".command-edit:eq(0)").clone(true).data('row-id', window.location.hash.substr(1)).click();
                    initial_load = false;
                }
            });
        }

        $("#reconfigureAct").SimpleActionButton();
        $("#savepointAct").SimpleActionButton({
            onAction: function(data, status){
                stdDialogInform(
                    "{{ lang._('Savepoint created') }}",
                    data['revision'],
                    "{{ lang._('Close') }}"
                );
            }
        });

        $("#revertAction").on('click', function(){
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DEFAULT,
                title: "{{ lang._('Revert to savepoint') }}",
                message: "<p>{{ lang._('Enter a savepoint to rollback to.') }}</p>" +
                    '<div class="form-group" style="display: block;">' +
                    '<input id="revertToTime" type="text" class="form-control"/>' +
                    '<span class="error text-danger" id="revertToTimeError"></span>'+
                    '</div>',
                buttons: [{
                    label: "{{ lang._('Revert') }}",
                    cssClass: 'btn-primary',
                    action: function(dialogRef) {
                        ajaxCall("/api/firewall/{{ruleController}}/revert/" + $("#revertToTime").val(), {}, function (data, status) {
                            if (data.status !== "ok") {
                                $("#revertToTime").parent().addClass("has-error");
                                $("#revertToTimeError").html(data.status);
                            } else {
                                std_bootgrid_reload("grid-rules");
                                dialogRef.close();
                            }
                        });
                    }
                }],
                onshown: function(dialogRef) {
                    $("#revertToTime").parent().removeClass("has-error");
                    $("#revertToTimeError").html("");
                    $("#revertToTime").val("");
                }
            });
        });
    });
</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="rules" class="tab-pane fade in active">
        <!-- tab page "rules" -->
        <table id="grid-rules" class="table table-condensed table-hover table-striped" data-editDialog="DialogFilterRule" data-editAlert="FilterRuleChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="sequence" data-type="string">{{ lang._('Sequence') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
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
        <div id="FilterRuleChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/firewall/{{ruleController}}/apply'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Filter load error') }}"
                type="button"
        ></button>

        <div class="pull-right">
            <button class="btn" id="savepointAct"
                    data-endpoint='/api/firewall/{{ruleController}}/savepoint'
                    data-label="{{ lang._('Savepoint') }}"
                    data-error-title="{{ lang._('snapshot error') }}"
                    type="button"
            ></button>
            <button  class="btn" id="revertAction">
                {{ lang._('Revert') }}
            </button>
        </div>
        <br/><br/>
    </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':'DialogFilterRule','label':lang._('Edit rule')])}}
