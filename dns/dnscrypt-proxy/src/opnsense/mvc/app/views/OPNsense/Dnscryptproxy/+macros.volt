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
 #}


{##
 # Macro to look for fields in an array of elements, and wrap a single field in
 # in an array if needed.
 #
 # This addresses the corner case of only a single 'field' on a tab. In that
 # case, the 'field' array is not nested in an array like it is where there are
 # multiple fields.
 #
 # @returns array   the fields
 #}
{%  macro get_fields(elements) %}
{%      set fields_found = 0 %}
{%      set field_array = [] %}
{%      if elements['field'] is defined %}
{%          for key, value in elements['field']|default({})|keys %} {# Get a list of the elements in 'field' #}
{%              if value is numeric %}  {# An array of fields will not have names, instead they are indexed. #}
{%                  set fields_found = 1 %}
{%                  break %} {# once we found a number, that means there are multiple fields. break out #}
{%              endif %}
{%          endfor %}
{%          if fields_found == 0 %} {# If there is more than 1 field, this will be 1. #}
{%              set field_array = [ elements['field'] ] %} {# Here we wrap our field in an iterable array #}
{%          else %}
{%              set field_array = elements['field']|default({}) %}
{%          endif %}
{%      endif %}
{%      return field_array %}
{%  endmacro %}

{##
 # Recursive macro for building bootgrid_dialogs for both subtabs and tabs.
 #
 # The recursion only occurs when a tab or subtab is found, so for each tab
 # the build_bootgrid_dialog is executed, sending the second index of the tab.
 #}
{%  macro build_bootgrid_dialogs(tabs) %}
{%      for tab in tabs|default([]) %}
{%          if tab['subtabs']|default(false) %}
{{              build_bootgrid_dialogs(tab['subtabs']) }}
{%              continue %}
{%          elseif tab['tabs']|default(false) %}
{{              build_bootgrid_dialogs(tab['tabs']) }}
{%              continue %}
{%          endif %}
{{          build_bootgrid_dialog(tab[2]) }}
{%      endfor %}
{%  endmacro %}

{##
 # This macro builds the bootgrid dialogs of a tab's fields.
 #
 # Searches through all of the fields looking for a bootgrid type. When found
 # execute the partial base_dialog sending the field as this_field.
 #}
{%  macro build_bootgrid_dialog(elements) %}
{%      for field in get_fields(elements|default({})) %}
{%          if field is iterable %}
{%              if field['type']|default('') != '' %}
{%                  if (field['type'] == 'bootgrid' and field['target']|default('') != '') %}
{%                      if field['dialog']|default(false) %}
{{                          partial("OPNsense/Dnscryptproxy/layout_partials/base_dialog",['this_field':field]) }}
{%                      endif %}
{%                  endif %}
{%              endif %}
{%          endif %}
{%      endfor %}
{%  endmacro %}

{##
 # This is basically the build_tabs_content partial in a macro.
 #
 # This iterates through the form, locating tabs and subtabs, and builds the
 # HTML headers.
 #}
{%  macro build_tabs_headers(tabs, active_tab = null) %}
<ul class="nav nav-tabs" role="tablist" id="maintabs">
{%      for tab in tabs|default([]) %}
{%          if tab['subtabs']|default(false) %}
{#              Tab with dropdown
                Find active subtab #}
{%              set active_subtab = "" %}
{%              for subtab in tab['subtabs']|default({}) %}
{%                  if subtab[0] == active_tab|default("") %}
{%                      set active_subtab = subtab[0] %}
{%                  endif %}
{%              endfor %}
    <li role="presentation" class="dropdown {% if active_tab|default('') == active_subtab %}active{% endif %}">
        <a data-toggle="dropdown"
            href="#"
            class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
            role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab"
            onclick="$('#subtab_item_{{ tab['subtabs'][0][0] }}').click();"
            class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
            style="border-right:0px;">
            <b>{{ tab[1] }}</b>
        </a>
        <ul class="dropdown-menu" role="menu">
{%              for subtab in tab['subtabs']|default({}) -%}
            <li class="{% if active_tab|default('') == subtab[0] %}active{% endif %}">
                <a data-toggle="tab"
                    id="subtab_item_{{ subtab[0] }}"
                    href="#subtab_{{ subtab[0] }}"
                    {% if subtab[2]['style']|default('') != '' %}
                        style="{{ subtab[2]['style'] }}"
                    {% endif %}>{{ subtab[1] }}</a>
            </li>
{%              endfor %}
        </ul>
    </li>
{%          else %}
        {# Standard Tab #}
        <li {% if active_tab|default('') == tab[0] %} class="active" {% endif %}>
            <a data-toggle="tab"
                id="tab_header_{{ tab[0] }}"
                href="#tab_{{tab[0]}}"
                {%- if tab[2]['style']|default('') != '' %}
                    style="{{ tab[2]['style'] }}"
                {% endif %}>
                <b>{{ tab[1] }}</b>
            </a>
        </li>
{%          endif %}
{%      endfor %}
</ul>
{%  endmacro %}

{##
 # This is a resursive matro to build the tab forms utilizing a partial.
 #
 # The recurstion happens for tabs and subtabs, and tab is build using the
 # base_form partial.
 #
 # A compromise was made in that the indention depth is the same for both
 # tab types, where is normally subtabs are one level further. The templates
 # mess up the indentation for everything though, it functions the same.
 #}
{%  macro build_tabs(tabs, active_tab = null, tab_type = null) %}
{%      for tab in tabs|default([]) %}
{%          if tab['subtabs']|default(false) %}
{{              build_tabs(tab['subtabs'], active_tab, 'subtab') }}
{%              continue %}
{%          elseif tab['tabs']|default(false) %}
{{              build_tabs(tab['tabs'], active_tab, 'tab') }}
{%              continue %}
{%          endif %}
        <div id="{{ tab_type|default('tab') }}_{{ tab[0] }}"
            class="tab-pane fade{% if active_tab|default('') == tab[0] %} in active {% endif %}">
{{          partial("OPNsense/Dnscryptproxy/layout_partials/base_form",['this_tab':tab]) }}
        </div>
{%      endfor %}
{%  endmacro %}

{##
 # This macro builds the entire page from the tabs provided as input.
 #
 # This is a super macro that builds the tab headers, tab contents, and the
 # bootgrid_dialogs all at once.
 #
 # This is to save on having to put three commands in the main volt, and to put
 # the div definition in the right place on the page.
 #}
{%  macro build_page(tabs,active_tab = null) %}
{{      build_tabs_headers(tabs, active_tab) }}
<div class="tab-content content-box tab-content">
{{      build_tabs(tabs, active_tab) }}
</div>
{{      build_bootgrid_dialogs(tabs) }}
{%  endmacro %}


{##
 # This is a super macro to be used in a <script> element to defined all of the
 # attachments necessary for opteration.
 #
 # Takes tabs as input, lang for some text fields, and plugin_name for some API
 # call definitions. Iterates trhough all of the fields utilizing a series of
 # if/elseif's to create attachments for those specific fields.
 #
 # Provides attachements for field types:
 #
 # bootgrid
 # command
 # checkbox
 # radio
 # managefile
 #}
{%  macro build_attachments(tabs, lang, plugin_name) %} {# Have to pass lang into the macro scope #}
{%      for tab in tabs|default([]) %}
{%          if tab['subtabs']|default(false) %}
{{              build_attachments(tab['subtabs'],lang, plugin_name) }}
{%              continue %}
{%          elseif tab['tabs']|default(false) %}
{{              build_attachments(tab['tabs'],lang, plugin_name) }}
{%              continue %}
{%          endif %}
{%          if tab[2]['model']|default('') != '' %}
                data_get_map['frm_tab_{{ tab[0] }}'] = '/api/{{ plugin_name }}/{{ tab[2]['model'] }}/get';
{%          endif %}
{%              for field in get_fields(tab[2]) %}
{%                  if field is iterable %}
{# =============================================================================
 # bootgrid: import button
 # =============================================================================
 # Allows importing a list into a field
 #}
{%                      if (field['type']|default('') == 'bootgrid' and
                            field['target']|default('') != '' and
                            field['api']['import']|default('') != '' and
                            field['label']|default('') != '') %}
{#  # From the Firewall alias plugin
    #   Since base_dialog() only has buttons for Save, Close, and Cancel,
    #   we build our own dialog using some wrapper functions, and
    #   perform validation on the data to be imported. #}
{#      Create an id derived from the target, escaping periods. #}
<?php $safe_id = preg_replace('/\./','_',$field['target']); ?>
    $('#btn_bootgrid_' + $.escapeSelector("{{ safe_id }}") + '_import').click(function(){
        let $msg = $("<div/>");
        let $imp_file = $("<input type='file' id='btn_bootgrid_{{ safe_id }}_select' />");
        let $table = $("<table class='table table-condensed'/>");
        let $tbody = $("<tbody/>");
        $table.append(
          $("<thead/>").append(
            $("<tr>").append(
              $("<th/>").text('{{ lang._("Source") }}')
            ).append(
              $("<th/>").text('{{ lang._("Message") }}')
            )
          )
        );
        $table.append($tbody);
        $table.append(
          $("<tfoot/>").append(
            $("<tr/>").append($("<td colspan='2'/>").text(
              '{{ lang._("Errors were encountered, no records were imported.") }}'
            ))
          )
        );

        $imp_file.click(function(){
{#          # Make sure upload resets when new file is provided
            # (bug in some browsers) #}
            this.value = null;
        });
        $msg.append($imp_file);
        $msg.append($("<hr/>"));
        $msg.append($table);
        $table.hide();
{#      # Show the dialog to the user for importing -#}
        BootstrapDialog.show({
          title: "{{ lang._('Import %s')|format(field['label']) }}",
          message: $msg,
          type: BootstrapDialog.TYPE_INFO,
          draggable: true,
          buttons: [{
              label: '<i class="fa fa-cloud-upload" aria-hidden="true"></i>',
              action: function(sender){
                  $table.hide();
                  $tbody.empty();
                  if ($imp_file[0].files[0] !== undefined) {
                      const reader = new FileReader();
                      reader.readAsBinaryString($imp_file[0].files[0]);
                      reader.onload = function(readerEvt) {
                          let import_data = null;
                          try {
                              import_data = JSON.parse(readerEvt.target.result);
                          } catch (error) {
                              $tbody.append(
                                $("<tr/>").append(
                                  $("<td>").text("*")
                                ).append(
                                  $("<td>").text(error)
                                )
                              );
                              $table.show();
                          }
                          if (import_data !== null) {
                              ajaxCall("{{ field['api']['import'] }}", {'data': import_data,'target': '{{ field['target'] }}' }, function(data,status) {
                                  if (data.validations !== undefined) {
                                      Object.keys(data.validations).forEach(function(key) {
                                          $tbody.append(
                                            $("<tr/>").append(
                                              $("<td>").text(key)
                                            ).append(
                                              $("<td>").text(data.validations[key])
                                            )
                                          );
                                      });
                                      $table.show();
                                  } else {
                                      std_bootgrid_reload('bootgrid_{{ safe_id }}')
                                      sender.close();
                                  }
                              });
                          }
                      }
                  }
              }
          },{
             label:  "{{ lang._('Cancel') }}",
             action: function(sender){
                sender.close();
             }
           }]
        });
    });
{%                          endif %}
{# =============================================================================
 # bootgrid: export button
 # =============================================================================
 # Allows exporting a list out for external storage or manupulation
 #
 # Mostly came from the firewall plugin.
 #}
{%                          if (field['type']|default('') == 'bootgrid' and
                                field['target']|default('') != '' and
                                field['api']['export']|default('') != '') %}
{#      Create an id derived from the target, escaping periods. #}
<?php $safe_id = preg_replace('/\./','_',$field['target']); ?>
    $("#btn_bootgrid_{{ safe_id }}_export").click(function(){
        ajaxGet("{{ field['api']['export'] }}", {"target": "{{ field['target'] }}"}, function(data, status){
            if (data) {
                let output_data = JSON.stringify(data, null, 2);
                let a_tag = $('<a></a>').attr('href','data:application/json;charset=utf8,' + encodeURIComponent(output_data))
                    .attr('download','{{ field['target'] }}_export.json').appendTo('body');

                a_tag.ready(function() {
                    if ( window.navigator.msSaveOrOpenBlob && window.Blob ) {
                        var blob = new Blob( [ output_data ], { type: "text/csv" } );
                        navigator.msSaveOrOpenBlob( blob, '{{ field['target'] }}_export.json' );
                    } else {
                        a_tag.get(0).click();
                    }
                });
            }
        });
    });
{%                          endif %}
{# =============================================================================
 # bootgrid: UIBootgrid attachments (API definition)
 # =============================================================================
 # Builds out the UIBootgrid attachments according to form definition
 #}
{%                          if (field['type']|default('') == 'bootgrid' and
                                field['target']|default('') != '') %}
{#      Create an id derived from the target, escaping periods. #}
<?php $safe_id = preg_replace('/\./','_',$field['target']); ?>
    $('#' + 'bootgrid_' + $.escapeSelector("{{ safe_id }}")).UIBootgrid(
        {
{%                              if (field['api']['search']|default('') != '') %}
            'search':'{{ field['api']['search'] }}/{{ field['target'] }}/',
{%                              endif %}
{%-                             if (field['api']['get']|default('') != '') %}
            'get':'{{ field['api']['get'] }}/{{ field['target'] }}/',
{%                              endif %}
{%-                             if (field['api']['set']|default('') != '') %}
            'set':'{{ field['api']['set'] }}/{{ field['target'] }}/',
{%                              endif %}
{%-                             if (field['api']['add']|default('') != '') %}
            'add':'{{ field['api']['add'] }}/{{ field['target'] }}/',
{%                              endif %}
{%-                             if (field['api']['del']|default('') != '') %}
            'del':'{{ field['api']['del'] }}/{{ field['target'] }}/',
{%                              endif %}
{%-                             if (field['api']['toggle']|default('') != '') %}
            'toggle':'{{ field['api']['toggle'] }}/{{ field['target'] }}/',
{%                              endif %}
            'options':{ 'selection':
{%-                             if (field['style']|default('') == 'log' or
                                    field['columns']['select']|default('true') == 'false') -%}
                            false
{%-                             else -%}
                            true
{%-                             endif %}
{%                              if (field['row_count']|default('') != '') %},
                        'rowCount':[{{ field['row_count'] }}]
{%                              endif %}
{%                              if (field['grid_options']|default('')) %},
                        {{- field['grid_options']|default('') }}
{%                              endif %}
            }
        }
    );
{%                          endif %} {#
{# =============================================================================
 # command: attachments for command field types
 # =============================================================================
 # Attachs to the command button sets up the classes and
 # defines the API to be called when clicked
 #}
{%                          if (field['type']|default('') == 'command' and
                                field['id']|default('') != '' and
                                field['api']|default('') != '' ) %}
    $('#btn_{{ field['id'] }}_command').click(function(){
        var command_input = $('#inpt_{{ field['id'] }}_command').val()
{%                              if field['output']|default('') != '' %}
        $('#pre_{{ field['output'] }}_output').text("Executing...");
{%                              endif %}
        $("#btn_{{ field['id'] }}_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url='{{ field['api'] }}', sendData={'command_input':command_input}, callback=function(data,status) {
            if (data['status'] != "ok") {
{%                              if field['output']|default('') != '' %}
                $('#pre_{{ field['output'] }}_output').text(data['status']);
{%                              endif %}
            } else {
{%                              if field['output']|default('') != '' %}
                $('#pre_{{ field['output'] }}_output').text(data['response']);
{%                              endif %}
            }
            $("#btn_{{ field['id'] }}_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });
{%                          endif %}
{# =============================================================================
 # checkbox, radio: toggle functionality
 # =============================================================================
 # A toggle function for both checkboxes and radio buttons.
 #}
{%                          if ((field['type']|default('') == 'checkbox' or field['type']|default('') == 'radio') and
                                field['id']|default('') != '' ) and
                                field['field_control'] is defined  -%}
{%                              if field['field_control']['field'] is defined %}
{# Attach to the checkbox input element, or the text field associated with the radio buttons #}
    $("#" + $.escapeSelector("{{ field['id'] }}")).change(function(e){
{#  This prevents the field from acting out if it is in a disabled state. #}
        if ($(this).hasClass("disabled") == false) {
{# This pulls the checked key values out of all of the field's attributes,
    and then creates an array of the unique values. #}
<?php $checked_list = array_unique(array_column(array_column($field['field_control']['field'],'@attributes'),'checked')) ?>
{#  Iterate through the values we found to start building our if blocks. #}
{%                              for checked in checked_list %}
{#  Start if statments looking at different value based on field type #}
{%                                  if field['type'] == 'checkbox' %}
            if ($(this).prop("checked") == {{ checked }} ) {
{%                                  elseif field['type'] == 'radio' %}
            if ($(this).val() == "{{ checked }}") {
{%                                  endif %}
{#  Iterate through the fields only if the "checked" value matches that of the current for loop's "checked" variable. #}
{%                                  for target_field in field['field_control']['field'] if target_field['@attributes']['checked'] == checked %}
{#  We use the field's value so we don't have to have a line of code for each version, check first that they're OK. #}
{%-                                     if target_field['@attributes']['state']|default('') == "disabled" or
                                            target_field['@attributes']['state']|default('')  == "enabled" %}
                toggleFields("{{ target_field[0] }}", "{{ target_field['@attributes']['state'] }}");
{#  Here we do the same thing as above but for hide/show, check the values first because was use them in our code. #}
{%                                      elseif target_field['@attributes']['state']|default('') == "hide" or
                                            target_field['@attributes']['state']|default('') == "show" %}
                $("#" + $.escapeSelector("{{ target_field[0] }}")).{{ target_field['@attributes']['state'] }}();
{%                                      endif %}
{%                                  endfor %}
            }
{%                              endfor %}
        }
    });
{%                          endif %}
{%                      endif %}
{# =============================================================================
 # radio: click activities
 # =============================================================================
 # Click event for radio type objects
 #}
{%                      if (field['type']|default('') == 'radio') and
                            (field['id']|default('') != '' ) %}
    $('input[name=rdo_' + $.escapeSelector("{{ field['id'] }}") + ']').click(function () {
{#      # Store which radio button was selected, since this value will be
        # dynamic depending on which radio button is clicked.
        # This looks a bit strange because all of the radio input tags have
        # the same name attribute, and differ in the content of the
        # surrounding <label> tag, and value attribute.
        # So when this is clicked, it sets the value of the field to be the same
        # same as the value of the radio button that was selected.
        # Then we trigger a change event to set any enable/disabled fields. #}
        $('#' + $.escapeSelector("{{ field['id'] }}")).val($(this).val());
        $('#' + $.escapeSelector("{{ field['id'] }}")).trigger("change");;
    });
{%                      endif %}
{# =============================================================================
 # radio: change activities
 # =============================================================================
 # Change function which updates the values of the approprite radio button.
 #}
{%                      if (field['type']|default('') == 'radio') %}
    $('#' + $.escapeSelector("{{ field['id'] }}")).change(function(e){
{#      # Set whichever radiobutton accordingly, may already be selected.
        # This covers the initial page load situation. #}
        var field_value = $('#' + $.escapeSelector("{{ field['id'] }}")).val();
        {# This catches the first pass, if change event is initiated before the
           value of the target field is set by mapDataToFormUI() #}
        if (field_value != "") {
            $('input[name=rdo_' + $.escapeSelector("{{ field['id'] }}") + '][value=' + field_value + ']').prop("checked", true);
        }
    });
{%                      endif %}
{# =============================================================================
 # managefile: file selection
 # =============================================================================
 # Catching when a file is selected for upload.
 #
 # Requires creation of "this_namespace" object earlier in script.
 #
 # I think this mostly came from the Web Proxy plugin.
 #}
{%                      if field['type']|default('') == 'managefile' and
                            field['id']|default('') != '' and
                            field['api']['upload']|default('') != '' %}
    $("input[for=" + $.escapeSelector("{{ field['id'] }}") + "][type=file]").change(function(evt) {
{#      Check browser compatibility #}
        if (window.File && window.FileReader && window.FileList && window.Blob) {
             var file_event = evt.target.files[0];
{#          If a file has been selected, let's get the content and file name. #}
            if (file_event) {
                var reader = new FileReader();
                reader.onload = function(readerEvt) {
{#                  Store these in our namespace for use in the upload function.
                    This namespace was created at the beginning of the script section. #}
                    this_namespace.upload_file_content = readerEvt.target.result;
                    this_namespace.upload_file_name = file_event.name;
{#                  Set the value of the input box we created to store the file name. #}
                    if ($("label[id='lbl_" + $.escapeSelector("{{ field['id'] }}") + "']").length){
                        $("label[id='lbl_" + $.escapeSelector("{{ field['id'] }}") + "']").text("Current: " + this_namespace.upload_file_name);
                    } else {
                        $("#" + $.escapeSelector("{{ field['id'] }}") + ",input[for=" + $.escapeSelector("{{ field['id'] }}") + "][type=text]").val(this_namespace.upload_file_name);
                    }
                };
{#              Actually get the file, explicitly reading as text. #}
                reader.readAsText(file_event);
            }
        } else {
{#          Maybe do something else if support isn't available for this API. #}
            alert("Your browser is too old to support HTML5 File's API.");
        }
    });
{# Attach to the ready event for the field to trigger and update to the value
   of the visible elements. #}
    $('#' + $.escapeSelector('{{ field['id'] }}')).change(function(e){
        var file_name = $('#' + $.escapeSelector('{{ field['id'] }}')).val();
{#      Modern style #}
        if ($('label[id="lbl_' + $.escapeSelector('{{ field['id'] }}') + '"]').length) {
            $('label[id="lbl_' + $.escapeSelector('{{ field['id'] }}') + '"]').text("Current: " + file_name);
        }
{#      Classic style #}
        if ($('input[for="' + $.escapeSelector('{{ field['id'] }}') + '"][type=text]').length) {
            $('input[for="' + $.escapeSelector('{{ field['id'] }}') + '"][type=text]').val(file_name);
        }
    });
{%                      endif %}
{# =============================================================================
 # managefile: file upload
 # =============================================================================
 # Upload activity of the selected file.
 #
 # Requires creation of "this_namespace" object earlier in script.
 #}
{%                      if field['type']|default('') == 'managefile' and
                            field['id']|default('') != '' and
                            field['api']['upload']|default('') != '' %}
    $("#btn_" + $.escapeSelector("{{ field['id'] }}" + "_upload")).click(function(){
{#      Check that we have the file content. #}
        if (this_namespace.upload_file_content) {
            ajaxCall("{{ field['api']['upload'] }}", {'content': this_namespace.upload_file_content,'target': '{{ field['id'] }}'}, function(data,status) {
                if (data['error'] !== undefined) {
{#                      error saving #}
                        stdDialogInform(
                            "Status: " + data['status'],
                            data['error'],
                            "OK",
                            function(){},
                            "warning"
                        );
                } else {
{#                  Clear the file content since we're done, then save, reload, and tell user. #}
                    this_namespace.upload_file_content = null;
                    saveFormAndReconfigure($("#btn_" + $.escapeSelector("{{ field['id'] }}" + "_upload")));
                    stdDialogInform(
                        "File Upload",
                        "Upload of "+ this_namespace.upload_file_name + " was successful.",
                        "Ok"
                    );
{#                  No error occurred, so let set the setting for storage in the config. #}
                    $("#" + $.escapeSelector("{{ field['id'] }}")).val(this_namespace.upload_file_name);
                }
            });
        }
    });
{%                      endif %}
{# =============================================================================
 # managefile: file download
 # =============================================================================
 # Download activity of the file that was uploaded.
 #}
{%                      if field['type']|default('') == 'managefile' and
                            field['id']|default('') != '' and
                            field['api']['download']|default('') != '' %}
    $("#btn_" + $.escapeSelector("{{ field['id'] }}") + "_download").click(function(){
       window.open('{{ field['api']['download'] }}/{{ field['id'] }}');
{#      # Use blur() to force the button to lose focus.
        # This addresses a UI bug where after clicking the button, and after dismissing
        # the save dialog (either save or cancel), upon returning to the browser window
        # the button lights up, and displays the tooltip. It then gets stuck like that
        # after the user clicks somewhere in the browser window.
        # This appears to only happen on the download activity. #}
        $(this).blur()
    });
{%                      endif %}
{# =============================================================================
 # managefile: file remove
 # =============================================================================
 # Removing a file that was uploaded.
 #
 # Dialog structure came from the web proxy plugin.
 #}
{%                      if field['type']|default('') == 'managefile' and
                            field['id']|default('') != '' and
                            field['api']['remove']|default('') != '' %}
        $("#btn_" + $.escapeSelector("{{ field['id'] }}") + "_remove").click(function() {
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: '{{ lang._('Remove File') }} ',
                message: '{{ lang._('Are you sure you want to remove this file?') }}',
                buttons: [{
                    label: '{{ lang._('Yes') }}',
                    cssClass: 'btn-primary',
                    action: function(dlg){
                        dlg.close();
                        ajaxCall("{{ field['api']['remove'] }}", {'field': '{{ field['id'] }}'}, function(data,status) {
                            if (data['error'] !== undefined) {
                                stdDialogInform(
                                    data['error'],
                                    "API Returned:\n" + data['status'],
                                    "OK",
                                    function(){},
                                    "warning"
                                );
                            } else {
                                if ($("label[id='lbl_" + $.escapeSelector("{{ field['id'] }}") + "']").length){
                                    $("label[id='lbl_" + $.escapeSelector("{{ field['id'] }}") + "']").text("Current: ");
                                } else {
                                    $("#" + $.escapeSelector("{{ field['id'] }}") + ",input[for=" + $.escapeSelector("{{ field['id'] }}") + "][type=text]").val("");
                                }
                                saveFormAndReconfigure($("#btn_" + $.escapeSelector("{{ field['id'] }}") + "_remove"));
                                stdDialogInform(
                                    "Remove file",
                                    "Remove file was successful.",
                                    "Ok"
                                );
                            }
                        });
                    }
                }, {
                    label: '{{ lang._('No') }}',
                    action: function(dlg){
                        dlg.close();
                    }
                }]
            });
        });

{%                      endif %}
{%                  endif %}
{%              endfor %}
{%      endfor %}
{%  endmacro %}
