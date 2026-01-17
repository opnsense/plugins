{#
 # Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
 #
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
    $(document).ready(function() {
        $("#{{formGridUser['table_id']}}").UIBootgrid(
            {
                search:'/api/nut/users/search_user/',
                get:'/api/nut/users/get_user/',
                set:'/api/nut/users/set_user/',
                add:'/api/nut/users/add_user/',
                del:'/api/nut/users/del_user/',
                toggle:'/api/nut/users/toggle_user/',
            }
        );

        $("#reconfigureAct").SimpleActionButton();

        updateServiceControlUI('nut');
    });
</script>

<div class="content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridUser) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/nut/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogUser,'id':formGridUser['edit_dialog_id'],'label':lang._('Edit user')])}}
