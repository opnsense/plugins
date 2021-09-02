{##
 #
 # OPNsense® is Copyright © 2014 – 2018 by Deciso B.V.
 # This file is Copyright © 2018 by Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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

{##
 # This is the template for the settings page.
 #
 # This is the main page for this plugin.
 #
 # Variables sent in by the controller:
 # plugin_name  string  name of this plugin, used for API calls
 # this_form    array   the form XML in an array
 #}


<?php ob_start(); ?>

{# Pull in our macros for use throughout #}
{{ partial("OPNsense/Dnscryptproxy/+macros") }}

{# Build the entire page including:
    tab headers,
    tabs content (include fields and bootgrids),
    and all bootgrid dialogs #}
{{ build_page(this_form['tabs'],this_form['activetab']) }}

<script>

$( document ).ready(function() {
{#/* global Object container, used by file upload/download functions.*/#}
    window.this_namespace = {};
{#/* Define this object now so we can push tabs to it later. */#}
    var data_get_map = {};

{#/* Attachment to trigger restoring the default sources via api. */#}
    $("#btn_restoreSourcesAct").SimpleActionButton({
{#/*    We're defining onPreAction here in order to display a confirm dialog
        before executing the button's API call. */#}
        onPreAction: function () {
{#/*        We create a defferred object here to hold the function
            from completing before input is received from the user. */#}
            const dfObj = new $.Deferred();
{#/*        stdDialogConfirm() doesn't return the result, i.e. cal1back()
            If the user clicks cancel it doesn't execute callback(), so
            so it never comes back to this function. There is no way to
            clean up the spinner on the button if the user clicks cancel.
            So we're using the wrapper BootstrapDialog.confirm() directly. */#}
            BootstrapDialog.confirm({
                title: '{{ lang._('Confirm restore sources to default') }} ',
                message: '{{ lang._('Are you sure you want to remove all sources, and restore the defaults?') }}',
                type: BootstrapDialog.TYPE_WARNING,
                btnOKLabel: '{{ lang._('Yes') }}',
                callback: function (result) {
                    if (result) {
{#/*                    User answered yes, we can return dfObj now. */#}
                        dfObj.resolve();
                    } else {
{#/*                    User answered no, clean up the spinner added by SimpleActionButton(), and then do nothing. */#}
                        $("#btn_restoreSourcesAct").find('.reload_progress').removeClass("fa fa-spinner fa-pulse");
                    }
                }
            });
{#/*        This is used to prevent the function from completeing before
            getting input from the user first. Only gets returned after
            the dialog box has been dismissed. */#}
            return dfObj;
        },
        onAction: function(data, status){
{#/*        This executes after the API call is complete.
             We need to refresh the grid since the data has changed. */#}
            std_bootgrid_reload("bootgrid_settings.sources.source"); {#/* id attribute of the bootgrid HTML element. */#}
        }
    });

{#/* Dynamically build all attachments using the form data */#}
{{ partial("OPNsense/Dnscryptproxy/layout_partials/base_script_content") }}

});

</script>

{# Clean up the blank lines, probably inefficient, but makes things look nice. #}
<?php  echo join("\n", array_filter(array_map(function ($i) { $o = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $i); if (!empty(trim($o))) {return $o;} }, explode("\n", ob_get_clean()))));  ?>
