<script>
    $(document).ready(function() {
        $('#dialogEditBindReverseDomain').on('shown.bs.modal', function() {
            ajaxCall('/api/bind/general/listsubnets/', {}, function(data) {
                if (data && data.subnets) {
                    var select = $('#domain\\.source_subnet');
                    var selected = select.val();
                    select.empty().append('<option value="">Select a subnet...</option>');
                    data.subnets.forEach(function(subnet) {
                        select.append($('<option></option>').val(subnet.value).text(subnet.label));
                    });
                    if (selected) {
                        select.val(selected);
                    }
                    select.selectpicker('refresh');
                }
            });
        });

        var grid = $('#grid-reverse-domains').UIBootgrid({
            search: '/api/bind/domain/search_reverse_domain',
            get: '/api/bind/domain/get_domain/',
            set: '/api/bind/domain/set_domain/',
            add: '/api/bind/domain/add_reverse_domain/',
            del: '/api/bind/domain/del_domain/',
            toggle: '/api/bind/domain/toggle_domain/',
            options: {
                selection: true,
                multiSelect: false,
                rowSelect: true,
                formatters: {
                    commands: function(column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-delete" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button> ' +
                            '<button type="button" class="btn btn-xs btn-default command-toggle" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-' + (row.enabled == '1' ? 'check-square-o' : 'square-o') + '"></span></button> ' +
                            '<button type="button" class="btn btn-xs btn-default bind-checkzone" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-stethoscope"></span></button>';
                    }
                }
            }
        });
        grid.on('click', '.bind-checkzone', function() {
            var uuid = $(this).data('row-id');
            ajaxGet('/api/bind/domain/get_domain/' + uuid, {}, function(data) {
                if (data && data.domain) {
                    zone_test(data.domain.domainname);
                }
            });
        });
    });
</script>
<div class="content-box"><div class="table-responsive">
    <table id="grid-reverse-domains" class="table table-striped table-bordered" data-editDialog="dialogEditBindReverseDomain">
        <thead><tr>
            <th data-column-id="domainname" data-type="string">{{ lang._('Zone Name') }}</th>
            <th data-column-id="source_subnet" data-type="string">{{ lang._('Subnet') }}</th>
            <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr></thead><tbody></tbody>
        <tfoot><tr><td colspan="5"><button type="button" class="btn btn-xs btn-primary" data-action="add" data-target="#dialogEditBindReverseDomain"><span class="fa fa-fw fa-plus"></span> {{ lang._('Add') }}</button></td></tr></tfoot>
    </table>
</div></div>
{{ partial("layout_partials/base_dialog", ['fields': formDialogEditBindReverseDomain, 'id': 'dialogEditBindReverseDomain', 'label': lang._('Edit Reverse Zone')]) }}
{{ partial("OPNsense/Bind/zone_check") }}
