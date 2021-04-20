{#
 # Copyright (c) 2017 Franco Fichtner <franco@opnsense.org>
 # Copyright (c) 2014-2015 Deciso B.V.
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
 # }

{##
 # This is a partial used to populate the <script> section of a page.
 #
 # Expects to have in the environment (scope) an array by the name of this_form.
 # This should contain an array of form XML data, created by the controller using
 # getForm().
 #
 # Expects to have all macros available in the environment.
 # views/OPNsense/Dnscryptproxy/+macros.volt
 #
 # Includes several universal functions, and attachments for convenience.
 #
 # All comments encapsulated in Javascript friendly notation so JS syntax
 # highlighting works correctly.
 #}

{#/*
    Call the attachments macro to build all of our attachments using the form data */#}
{{  build_attachments(this_form['tabs'], lang, plugin_name) }}

{#/*
    # Toggle function is for enabling or disabling field(s)
    # This will disable an entire row (make things greyed out)
    # takes care of at least text boxes, checkboxes, and dropdowns.
    # It uses the *= wildcard, so take care with the field name.
    # Field should be the id of an object or the prefix/suffix
    # for a set of objects. */#}
    function toggleFields (field,toggle) {
        var efield = $.escapeSelector(field);
        if (toggle == "disabled") {
{#/*        # This might need further refinement, selects the row matching field id,
            # uses .find() to select descendants, .addBack() to select itself. */#}
            var selected = $('tr[id=row_' + efield + ']').find('div,[id*=' + efield + '],[data-id*=' + efield + '],[name*=' + efield + '],[class^="select-box"],[class^="btn"],ul[class^="tokens-container"]').addBack();
{#/*        # Disable entire row related to a field */#}
            selected.addClass("disabled");
            selected.prop({
                "readonly": true,
                "disabled": true
            });
{#/*        # This element needs to be specially hidden because it is for some reason
            # hidden when tokenizer creates the element. This is the target element
            # <li class="token-search" style="width: 15px; display: none;"><input autocomplete="off"></li> */#}
            selected.find('li[class="token-search"]').hide();
{#/*        # Disable the Clear All link below dropdown boxes,
            # the toggle column on grids (Enabled column),
            # and the tokens in a tokenized field. */#}
            selected.find('a[id^="clear-options_"],[class*="command-toggle"],li[class="token"]').css("pointer-events","none");
            $('input[id=' + efield + ']').trigger("change");
        } else if (toggle == "enabled") {
{#/*        # This might need further refinement, selects the row matching field id,
            # uses .find() to select descendants, .addBack() to select itself. */#}
            var selected = $('tr[id=row_' + efield + ']').find('div,[id*=' + efield + '],[data-id*=' + efield + '],[name*=' + efield + '],[class^="select-box"],[class^="btn"],ul[class^="tokens-container"]').addBack();
{#/*        # Disable entire row related to a field */#}
            selected.removeClass("disabled");
            selected.prop({
                "readonly": false,
                "disabled": false
            });
{#/*        # This element needs to be specially shown because it is for some reason
            # hidden when tokenizer creates the element. This is the target element
            # <li class="token-search" style="width: 15px; display: none;"><input autocomplete="off"></li>*/#}
            selected.find('li[class="token-search"]').show();
{#/*        # Enable the Clear All link below dropdown boxes,
            # the toggle column on grids (Enabled column),
            # and the tokens in a tokenized field.*/#}
            selected.find('a[id^="clear-options_"],[class*="command-toggle"],li[class="token"]').css("pointer-events","auto");
{#/*        # Trigger a field change to trigger a toggle of any dependent fields (i.e. fields that this field enables) */#}
            var selected_field = $('input[id=' + efield + ']')
            $('input[id=' + efield + ']').trigger("change");
        }
    }

{#/*
    # Basic function to save the form, and reconfigure after saving
    # displays a dialog if there is some issue */#}
    function saveFormAndReconfigure(element){
        const dfObj = new $.Deferred();
        var this_frm = $(element).closest("form");
        var frm_id = this_frm.attr("id");
        var frm_title = this_frm.attr("data-title");
        var frm_model = this_frm.attr("data-model");
        var api_url="/api/{{ plugin_name }}/" + frm_model + "/set";

{#/*    # set progress animation when saving */#}
        $("#" + frm_id + "_progress").addClass("fa fa-spinner fa-pulse");

        saveFormToEndpoint(url=api_url, formid=frm_id, callback_ok=function(){
            ajaxCall(url="/api/{{ plugin_name }}/service/reconfigure", sendData={}, callback=function(data,status){
{#/*            # when done, disable progress animation. */#}
                $("#" + frm_id + "_progress").removeClass("fa fa-spinner fa-pulse");

                if (status != "success" || data['status'] != 'ok' ) {
{#/*                # fix error handling */#}
                    if (data['message'] != '' ) {
                        var message = data['message']
                    } else {
                        var message = JSON.stringify(data)
                    }
                    BootstrapDialog.show({
                        type:BootstrapDialog.TYPE_WARNING,
                        title: frm_title,
                        message: message,
                        draggable: true
                    });
                } else {
                    ajaxCall(url="/api/{{ plugin_name }}/service/status", sendData={}, callback=function(data,status) {
                        updateServiceStatusUI(data['status']);
                        dfObj.resolve();
                    });
                }
            });
        });
        return dfObj;
    }


{#/*
    # Adds a hash tag to the URL, for example: http://opnsense/ui/dnscryptproxy/settings#subtab_schedules
    # update history on tab state and implement navigation
    # From the firewall plugin */#}
    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click();
    }

    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });

{#/*
    # Save event handlers for all defined forms
    # This uses jquery selector to match all button elements with id starting with "save_frm_" */#}
    $('button[id^="save_frm_"]').each(function(){
        $(this).click(function() {
            saveFormAndReconfigure($(this));
        });
    });

{#/*
    # Update the service controls any time the page is loaded. */#}
    updateServiceControlUI('{{ plugin_name }}');
