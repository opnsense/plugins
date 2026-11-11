{#
/*
 * Copyright (C) 2026 Farzad Sadeghi <devi+github@terminaldweller.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
#}
<script>
$(document).ready(function() {
    function updateInterfaceNameVisibility() {
        const custom = $("#yggdrasil\\.general\\.IfNameMode").val() === "custom";
        $("#yggdrasil\\.general\\.IfNameCustom").closest("tr").toggle(custom);
    }

    $("#yggdrasil\\.general\\.IfNameMode").on(
        "change",
        updateInterfaceNameVisibility
    );

    mapDataToFormUI({
        'frm_GeneralSettings': '/api/yggdrasil/settings/get'
    }).done(function() {
        $('.selectpicker').selectpicker('refresh');
        updateInterfaceNameVisibility();
    });

    updateServiceControlUI('yggdrasil');

    $("#reconfigureAct").SimpleActionButton({
        onPreAction: function() {
            const dfObj = $.Deferred();
            saveFormToEndpoint(
                '/api/yggdrasil/settings/set',
                'frm_GeneralSettings',
                dfObj.resolve,
                true,
                dfObj.reject
            );
            return dfObj;
        },
        onAction: function() {
            updateServiceControlUI('yggdrasil');
        }
    });

    $("#gridPeers").UIBootgrid({
        search: '/api/yggdrasil/settings/search_peer/',
        get: '/api/yggdrasil/settings/get_peer/',
        set: '/api/yggdrasil/settings/set_peer/',
        add: '/api/yggdrasil/settings/add_peer/',
        del: '/api/yggdrasil/settings/del_peer/'
    });

    $("#gridInterfacePeers").UIBootgrid({
        search: '/api/yggdrasil/settings/search_interface_peer/',
        get: '/api/yggdrasil/settings/get_interface_peer/',
        set: '/api/yggdrasil/settings/set_interface_peer/',
        add: '/api/yggdrasil/settings/add_interface_peer/',
        del: '/api/yggdrasil/settings/del_interface_peer/'
    });

    $("#gridListeners").UIBootgrid({
        search: '/api/yggdrasil/settings/search_listener/',
        get: '/api/yggdrasil/settings/get_listener/',
        set: '/api/yggdrasil/settings/set_listener/',
        add: '/api/yggdrasil/settings/add_listener/',
        del: '/api/yggdrasil/settings/del_listener/'
    });

    $("#gridMulticastInterfaces").UIBootgrid({
        search: '/api/yggdrasil/settings/search_multicast_interface/',
        get: '/api/yggdrasil/settings/get_multicast_interface/',
        set: '/api/yggdrasil/settings/set_multicast_interface/',
        add: '/api/yggdrasil/settings/add_multicast_interface/',
        del: '/api/yggdrasil/settings/del_multicast_interface/'
    });

    $("#gridAllowedPublicKeys").UIBootgrid({
        search: '/api/yggdrasil/settings/search_allowed_public_key/',
        get: '/api/yggdrasil/settings/get_allowed_public_key/',
        set: '/api/yggdrasil/settings/set_allowed_public_key/',
        add: '/api/yggdrasil/settings/add_allowed_public_key/',
        del: '/api/yggdrasil/settings/del_allowed_public_key/'
    });
});
</script>

<div class="content-box">
    {{ partial('layout_partials/base_form', ['fields': general, 'id': 'frm_GeneralSettings']) }}
</div>

<div class="content-box">
    <h3>{{ lang._('Peers') }}</h3>
    {{ partial('layout_partials/base_bootgrid_table', [
        'table_id': 'gridPeers',
        'edit_dialog_id': 'dialogPeer',
        'fields': [
            [
                'column-id': 'uri',
                'label': lang._('Peer URI')
            ]
        ]
    ]) }}
</div>

<div class="content-box">
    <h3>{{ lang._('Interface Peers') }}</h3>
    {{ partial('layout_partials/base_bootgrid_table', [
        'table_id': 'gridInterfacePeers',
        'edit_dialog_id': 'dialogInterfacePeer',
        'fields': [
            [
                'column-id': 'Interface',
                'label': lang._('Interface')
            ],
            [
                'column-id': 'URI',
                'label': lang._('Peer URI')
            ]
        ]
    ]) }}
</div>

<div class="content-box">
    <h3>{{ lang._('Listen') }}</h3>
    {{ partial('layout_partials/base_bootgrid_table', [
        'table_id': 'gridListeners',
        'edit_dialog_id': 'dialogListener',
        'fields': [
            [
                'column-id': 'URI',
                'label': lang._('Listen URI')
            ]
        ]
    ]) }}
</div>

<div class="content-box">
    <h3>{{ lang._('Multicast Interfaces') }}</h3>
    {{ partial('layout_partials/base_bootgrid_table', [
        'table_id': 'gridMulticastInterfaces',
        'edit_dialog_id': 'dialogMulticastInterface',
        'fields': [
            [
                'column-id': 'Regex',
                'label': lang._('Interface Pattern')
            ],
            [
                'column-id': 'Beacon',
                'label': lang._('Beacon')
            ],
            [
                'column-id': 'Listen',
                'label': lang._('Listen')
            ],
            [
                'column-id': 'Port',
                'label': lang._('Port')
            ],
            [
                'column-id': 'Priority',
                'label': lang._('Priority')
            ]
        ]
    ]) }}
</div>

<div class="content-box">
    <h3>{{ lang._('Allowed Public Keys') }}</h3>
    {{ partial('layout_partials/base_bootgrid_table', [
        'table_id': 'gridAllowedPublicKeys',
        'edit_dialog_id': 'dialogAllowedPublicKey',
        'fields': [
            [
                'column-id': 'PublicKey',
                'label': lang._('Public Key')
            ]
        ]
    ]) }}
</div>

{{ partial('layout_partials/base_apply_button', {
    'data_endpoint': '/api/yggdrasil/service/reconfigure',
    'data_service_widget': 'yggdrasil'
}) }}

{{ partial('layout_partials/base_dialog', [
    'fields': peerForm,
    'id': 'dialogPeer',
    'label': lang._('Edit Peer')
]) }}

{{ partial('layout_partials/base_dialog', [
    'fields': interfacePeerForm,
    'id': 'dialogInterfacePeer',
    'label': lang._('Edit Interface Peer')
]) }}

{{ partial('layout_partials/base_dialog', [
    'fields': listenerForm,
    'id': 'dialogListener',
    'label': lang._('Edit Listener')
]) }}

{{ partial('layout_partials/base_dialog', [
    'fields': multicastInterfaceForm,
    'id': 'dialogMulticastInterface',
    'label': lang._('Edit Multicast Interface')
]) }}

{{ partial('layout_partials/base_dialog', [
    'fields': allowedPublicKeyForm,
    'id': 'dialogAllowedPublicKey',
    'label': lang._('Edit Allowed Public Key')
]) }}
