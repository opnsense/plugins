{# SPDX-License-Identifier: MIT #}
{# SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net> #}

<script src="/ui/js/moment-with-locales.min.js"></script>
<script src="/ui/js/CrowdSec/crowdsec.js"></script>

<script>
    $(function() {
        CrowdSec.init();
    });
</script>

<style type="text/css">
.content-box table {
  table-layout: auto;
}

table.bootgrid-table tr .btn-sm {
  padding: 2px 6px;
}

table.bootgrid-table tr > td {
  padding: 3px;
}

li.spaced {
  margin-left: 15px;
}

ul.nav>li>a {
  padding: 6px;
}
</style>

<div>
  Service status: crowdsec <span id="crowdsec-status">...</span> - firewall bouncer <span id="crowdsec-firewall-status">...</span>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" id="machines_tab" href="#machines">Machines</a></li>
    <li><a data-toggle="tab" id="bouncers_tab" href="#bouncers">Bouncers</a></li>
    <li class="spaced"><a data-toggle="tab" id="collections_tab" href="#collections">Collections</a></li>
    <li><a data-toggle="tab" id="scenarios_tab" href="#scenarios">Scenarios</a></li>
    <li><a data-toggle="tab" id="parsers_tab" href="#parsers">Parsers</a></li>
    <li><a data-toggle="tab" id="postoverflows_tab" href="#postoverflows">Postoverflows</a></li>
    <li class="spaced"><a data-toggle="tab" id="alerts_tab" href="#alerts">Alerts</a></li>
    <li><a data-toggle="tab" id="decisions_tab" href="#decisions">Decisions</a></li>
    <li class="pull-right"><a data-toggle="tab" id="debug_tab" href="#debug" style="display:none">Debug</a></li>
</ul>

<div class="tab-content content-box">

    <div id="machines" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="name">Name</th>
                  <th data-column-id="ip_address">IP Address</th>
                  <th data-column-id="last_update" data-formatter="datetime">Last Update</th>
                  <th data-column-id="validated" data-formatter="yesno" data-searchable="false">Validated?</th>
                  <th data-column-id="version">Version</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="bouncers" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="name">Name</th>
                  <th data-column-id="ip_address">IP Address</th>
                  <th data-column-id="valid" data-formatter="yesno" data-searchable="false">Valid</th>
                  <th data-column-id="last_pull" data-formatter="datetime">Last API Pull</th>
                  <th data-column-id="type">Type</th>
                  <th data-column-id="version">Version</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="collections" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="name">Name</th>
                  <th data-column-id="status">Status</th>
                  <th data-column-id="local_version">Version</th>
                  <th data-column-id="local_path">Local Path</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="scenarios" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="name">Name</th>
                  <th data-column-id="status">Status</th>
                  <th data-column-id="local_version">Version</th>
                  <th data-column-id="local_path">Path</th>
                  <th data-column-id="description">Description</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="parsers" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="name">Name</th>
                  <th data-column-id="status">Status</th>
                  <th data-column-id="local_version">Version</th>
                  <th data-column-id="local_path">Local Path</th>
                  <th data-column-id="description">Description</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="postoverflows" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="name">Name</th>
                  <th data-column-id="status">Status</th>
                  <th data-column-id="local_version">Version</th>
                  <th data-column-id="local_path">Local Path</th>
                  <th data-column-id="description">Description</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="alerts" class="tab-pane fade in">
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="id" data-type="numeric">ID</th>
                  <th data-column-id="value">Value</th>
                  <th data-column-id="reason">Reason</th>
                  <th data-column-id="country">Country</th>
                  <th data-column-id="as">AS</th>
                  <th data-column-id="decisions">Decisions</th>
                  <th data-column-id="created_at" data-formatter="datetime">Created At</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="decisions" class="tab-pane fade in">
        Note: the decisions coming from the CAPI (signals collected by the CrowdSec users) do not appear here.
        To show them, use <code>cscli decisions list -a</code> in a shell.
        <table class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                  <th data-column-id="delete" data-formatter="delete" data-visible-in-selection="false"></th>
                  <th data-column-id="id" data-identifier="true" data-type="numeric">ID</th>
                  <th data-column-id="source">Source</th>
                  <th data-column-id="scope_value">Scope:Value</th>
                  <th data-column-id="reason">Reason</th>
                  <th data-column-id="action">Action</th>
                  <th data-column-id="country">Country</th>
                  <th data-column-id="as">AS</th>
                  <th data-column-id="events_count" data-type="numeric">Events</th>
                  <th data-column-id="expiration" data-formatter="duration">Expiration</th>
                  <th data-column-id="alert_id" data-type="numeric">Alert&nbsp;ID</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="debug" class="tab-pane fade in">
        <pre>
        </pre>
    </div>

    <!-- Modal popup to confirm decision deletion -->
    <div class="modal fade" id="delete-decision-modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="modalLabel">Modal Title</h4>
                </div>
                <div class="modal-body">
                    Modal content...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">No, cancel</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal" id="delete-decision-confirm">Yes, delete</button>
                </div>
            </div>
        </div>
    </div>

</div>
