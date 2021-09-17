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
 # This is the template for the diagnostics page.
 #
 # Variables sent in by the controller:
 # plugin_name  string  name of this plugin, used for API calls
 # this_form    array   the form XML in an array
 #}

{# Pull in our macro definitions #}
{{ partial("OPNsense/Dnscryptproxy/+macros") }}

{# Build the entire page including:
    tab headers,
    tabs content (include fields and bootgrids),
    and all bootgrid dialogs #}
{{ build_page(this_form['tabs'],this_form['activetab']) }}


<script>

$( document ).ready(function() {
{#/* Define this object now so we can push tabs to it later. */ #}
    var data_get_map = {};

{# /* Define this object now so we can push tabs to it later. */ #}
{{  partial("OPNsense/Dnscryptproxy/layout_partials/base_script_content") }}

    mapDataToFormUI(data_get_map).done(function(){
{#/*    # Update the fields using the tokenizer style. */#}
        formatTokenizersUI();
{#/*    # Refresh the data for the select picker fields. */#}
        $('.selectpicker').selectpicker('refresh');
    });


});
</script>
