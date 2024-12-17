<script>
    $(document).ready(function() {
        function nl2br (str, is_xhtml) {
            if (typeof str === 'undefined' || str === null) {
                return '';
            }
            var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
            return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
        }

	function updateNetInfo() {
            ajaxGet(url = "/api/tailscale/status/net/", sendData={},
                callback = function (data, status) {
                    if (status == "success") {
                        if (data.result) {
                            var html = nl2br(data.result);
                            html = html.replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
			    $('#net_info').html(html);
                        }
                    } else {
                        $('#net_info').html('Error');
                    }
                }
            );
        }

        function updatePeerInfo(peers) {
            $.each(peers, function(peer, data) {
                let tailscaleIp = '';
                if (data.TailscaleIPs !== null && data.TailscaleIPs !== undefined) {
                    tailscaleIp = data.TailscaleIPs.join(', ');

                    console.log(tailscaleIp);
                }

                let row = '<tr><td>' + data.HostName;
                row += '</td><td>' + tailscaleIp;
                row += '</td><td>' + data.LastSeen;
                row += '</td><td>' + data.OS;
                row += '</td></tr>';

                $('#peerInfo > tbody').append(row);
            });
        }

        function updateStatusInfo() {
            ajaxGet(url = "/api/tailscale/status/status/", sendData={},
                callback = function (data, status) {
                    $('#statusList > tbody').empty();
                    $('#statusList > thead').hide();
                    if (status == "success") {
                        $('#statusList > thead').show();

                        let skipKeys = [
                            'CertDomains',
                            'ClientVersion',
                            'CurrentTailnet',
                            'Health',
                            'Self',
                            'User'
                        ];

                        $.each(data, function (key, value) {
                            if (skipKeys.includes(key)) {
                                return true;
                            }

                            if (key === 'Peer') {
                                updatePeerInfo(data.Peer);
                                return true;
                            }

                            $('#statusList > tbody').append('<tr><td>' + key + '</td>' +
                            '<td>' + value + '</td></tr>');
                        });
                    } else {
                        $('#statusList > tbody').append('<tr><td colspan=2>Unable to fetch status, is Tailscale running?</td></tr>');
                    }

                    updateServiceControlUI('tailscale');
                }
            );
        }

        updateNetInfo();
        updateStatusInfo();
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="tab_general" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" id="tab_peers" href="#peers">{{ lang._('Peers') }}</a></li>
    <li><a data-toggle="tab" id="tab_net_info" href="#net_info">{{ lang._('Net Check') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div id="status_info">
            <table id="statusList" class="table table-striped table-condensed table-responsive">
                <thead>
                    <tr>
                        <th>{{ lang._('Name') }}</th>
                        <th>{{ lang._('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <div id="peers" class="tab-pane fade in">
        <table id="peerInfo" class="table table-striped table-condensed table-responsive">
            <thead>
                <tr>
                    <th>{{ lang._('Name') }}</th>
                    <th>{{ lang._('Tailscale IPs') }}</th>
                    <th>{{ lang._('Last Seen') }}</th>
                    <th>{{ lang._('OS') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>

    <div id="net_info" class="tab-pane fade in">
    </div>
</div>
