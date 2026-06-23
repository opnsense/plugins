{#
 # Copyright (c) 2019-2025 Deciso B.V.
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
    $(document).ready(function() {
        // set up the UIBootgrid API endpoints for the base_bootgrid_table
        $("#{{formGridAddress['table_id']}}").UIBootgrid(
            {   search:'/api/gridexample/settings/search_item/',
                get:'/api/gridexample/settings/get_item/',
                set:'/api/gridexample/settings/set_item/',
                add:'/api/gridexample/settings/add_item/',
                del:'/api/gridexample/settings/del_item/',
                toggle:'/api/gridexample/settings/toggle_item/'
            }
        );

        // use SimpleActionButton() to call /api/gridexample/service/reconfigure as example
        $("#reconfigureAct").SimpleActionButton();
    });

</script>

<div class="content-box">
    <!-- auto creates a bootgrid from the data in formGridAddress -->
    {{ partial('layout_partials/base_bootgrid_table', formGridAddress) }}
</div>
<!-- general purpose apply button, used to trigger reconfigureAct -->
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/gridexample/service/reconfigure'}) }}
<!-- base_dialog used by the base_bootgrid_table -->
{{ partial("layout_partials/base_dialog",['fields':formDialogAddress,'id':formGridAddress['edit_dialog_id'],'label':lang._('Edit address')])}}
