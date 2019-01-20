{#
 # Copyright (C) 2017-2019 Fabian Franz
 # Copyright (C) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 #  1. Redistributions of source code must retain the above copyright notice,
 #   this list of conditions and the following disclaimer.
 #
 #  2. Redistributions in binary form must reproduce the above copyright
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
    function show_error(message) {
        BootstrapDialog.show({
            type: BootstrapDialog.TYPE_DANGER,
            title: "{{ lang._('Error') }}",
            message: message,
            buttons: [{
                label: '{{ lang._('Close') }}',
                action: function (dlg) {
                    dlg.close();
                }
            }]
        });
    }
    $(function () {
        let login_active = false;
        const sb = $('.statusbox');
        const lb = $('.loginbox');
        const lib = $('#loginbtn');
        const lob = $('#logoutbtn');
        function get_credentials() {
            return {
                'Authorization': 'Basic ' + btoa($('#username').val() + ':' + $('#password').val())
            }
        }
        let perform_login = function (type) {
            // perform the event only if we are already authenticated or if the user clicked the button
            if (login_active === true || type === 'btn') {
                $.ajax({
                    type: "GET",
                    url: '/api/usermapping/session/login',
                    headers: get_credentials(),
                    complete: function(data, status) {
                        if (status === 'success') {
                            if ('error' in data.responseJSON) {
                                show_error(data.responseJSON.error);
                            }
                            else {
                                login_active = true;
                                lb.addClass('hidden');
                                sb.removeClass('hidden');
                                let rj = data.responseJSON;
                                Object.keys(rj).forEach(function (obj_key) {
                                    if (rj.hasOwnProperty(obj_key)) {
                                        $('#' + obj_key + '-val').text(rj[obj_key]);
                                    }
                                });
                            }
                        }
                    }
                });
            }
        };
        let perform_logout = function () {
            if (login_active) {
                login_active = false;
                $.ajax({
                    type: "GET",
                    url: '/api/usermapping/session/logout',
                    headers: get_credentials(),
                    complete: function(data, status) {
                        if (status === 'success') {
                            lb.removeClass('hidden');
                            sb.addClass('hidden');
                        }
                    }
                });
            }
        };
        setInterval(perform_login, 5000, ["cron"]);

        lob.on('click', perform_logout);
        lib.on('click', function () {
            perform_login("btn");
        });

    })
</script>
<style>
    .hidden { display: none; }
    .statusbox > table tr td:first-child {
        font-family: 'SourceSansProSemibold', sans-serif;
    }
    .btn_container {
        margin: 10px;
    }
</style>

<div class="alert alert-danger" role="alert">
    {{ lang._('If you are logged in, do not close this page or you will get logged out automatically.') }}
</div>

<div class="content-box">
    <div class="loginbox">
{{ partial("layout_partials/base_form",['fields':user_mapping,'id':'login'])}}
        <div class="btn_container">
            <button id="loginbtn" class="btn btn-primary">{{ lang._('Log In') }}</button>
        </div>

    </div>
    <div class="statusbox hidden">
        <table class="table table-striped">
            <tr>
                <td>{{ lang._('Username') }}</td>
                <td id="username-val"></td>
            </tr>
            <tr>
                <td>{{ lang._('Groups') }}</td>
                <td id="groups-val"></td>
            </tr>
            <tr>
                <td>{{ lang._('Valid Until') }}</td>
                <td id="valid_until-val"></td>
            </tr>
            <tr>
                <td>{{ lang._('IP Address') }}</td>
                <td id="ip_address-val"></td>
            </tr>
        </table>
        <div class="btn_container">
            <button id="logoutbtn" class="btn btn-primary">{{ lang._('Log Out') }}</button>
        </div>
    </div>
</div>