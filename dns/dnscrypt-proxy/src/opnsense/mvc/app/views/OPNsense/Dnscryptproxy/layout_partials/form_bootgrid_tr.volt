{#
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
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
 # This is a partial for building the bootgrid HTML table row.
 #
 # This is called by base_form.volt for field types 'bootgrid'
 #
 # Expects to receive an array by the name of this_field.
 #
 # The following keys may be used in this partial:
 #
 # this_field['target']            : target for this bootgrid
 # this_field['type']              : type of input or field. Valid types are:
 #           bootgrid                bootgrid field
 # this_field['api']['add']        : API for bootgrid to add entries
 # this_field['api']['del']        : API for bootgrid to delete entries
 # this_field['api']['set']        : API for bootgrid to set entries (edit/update)
 # this_field['api']['get']        : API for bootgrid to get entries (edit/read)
 # this_field['api']['toggle']     : API for bootgrid to toggle entries (enable/disable)
 # this_field['api']['export']     : API for bootgrid to export entries
 # this_field['api']['import']     : API for bootgrid to import entries
 # this_field['columns']['column'] : array of columns for the bootgrid
 # this_field['dialog']            : array containing the fields for the dialog
 # this_field['label']             : attribute label (visible text)
 # this_field['help']              : help text
 # this_field['advanced']          : property "is advanced", only display in advanced mode
 # this_field['style']             : css class to add
 #}
{%  if this_field['target'] is defined %} {# Only do this if we have a target. #}
{#      Create an id derived from the target, escaping periods. #}
<?php $safe_id = preg_replace('/\./','_',$this_field['target']); ?>
<tr
    id="row_{{ safe_id }}"
    {{ this_field['advanced']|default(false) ? 'data-advanced="true"' : '' }}
>
    <td colspan="3">
{%      if this_field['label']|default('') != '' %}
        <div class="control-label" id="control_label_{{ safe_id }}">
{%          if this_field['help']|default(false) %}
            <a
                id="help_for_{{ safe_id }}"
                href="#"
                class="showhelp"
            >
                    <i class="fa fa-info-circle"></i>
            </a>
{%          elseif this_field['help']|default(false) == false %}
            <i class="fa fa-info-circle text-muted"></i>
{%          endif %}
            <b>{{ lang._('%s')|format(this_field['label']) }}</b>
        </div>
{%          if this_field['help']|default(false) %}
        <div class="hidden" data-for="help_for_{{ safe_id }}">
            <small>{{ lang._('%s')|format(this_field['help']) }}</small>
        </div>
{%          endif %}
{%      endif %}
{#  # data-editDialog value must match button values on the edit dialog.
    # The bootgrid plugin uses it like:
    # $("#btn_"+editDlg+"_save").unbind('click');
    # $("#"+editDlg).modal('hide');
    # so this means that the dialog can't have
    # a . (or other unsafe) in the name since
    # it isn't escaped before using the var in a selector
    # in opnsense_bootgrid_plugin.js #}
        <table
            id="bootgrid_{{ safe_id }}"
            class="
                table
                table-condensed
                table-hover
                table-striped
                table-responsive
                bootgrid-table"
            data-editDialog="{{ this_field['dialog']|default(false) ? 'bootgrid_dialog_'~safe_id : '' }}"
        >
            <thead>
                <tr>
{#  # There are four formatters defined in opnsense_bootgrid_plugin.js,
    # two of them are already used here, another is commandsWithInfo
    # Need to add visibleInSelection #}
{%      if this_field['api']['toggle']|default('') != '' %}
                    <th
                        data-column-id="enabled"
                        data-width="0em"
                        data-type="string"
                        data-formatter="rowtoggle"
                    >
                        {{ lang._('%s')|format('Enabled') }}
                    </th>
{%      endif %}
{%      if this_field['columns']['column'][0] is not iterable %}
{%          set columns_var = [this_field['columns']['column']] %}
{%      elseif this_field['columns']['column'][0] is iterable %}
{%          set columns_var = this_field['columns']['column'] %}
{%      endif %}
{%      for index, column in columns_var %}
{%          set data_formatter = "" %}
                    <th
                        data-column-id="{{ column['@attributes']['id']|default('') }}"
                        data-width="{{ column['@attributes']['width']|default('') }}"
                        data-size="{{ column['@attributes']['size']|default('') }}"
                        data-type="{{ column['@attributes']['type']|default('string') }}"
                        data-visible="{{ column['@attributes']['visible']|default('true') }}"
                        data-formatter="{{ column['@attributes']['data-formatter']|default('') }}"
                    >
{%          if column is type('array') %}
                        {{ lang._('%s')|format(column[0]|default('')) }}
{%          endif %}
                    </th>
{%      endfor %}
{%      if (this_field['api']['del']|default('') != '' and
            this_field['api']['set']|default('') != '' and
            this_field['api']['get']|default('') != '' and
            this_field['api']['add']|default('') != '') %}
                    <th
                        data-column-id="commands"
                        data-formatter="commands"
                        data-sortable="false"
                        data-width="7em"
                    >
                        {{ lang._('%s')|format('Commands') }}
                    </th>
{%      endif %}
{#                  # Column to house the UUID of each row
                    # in the table. Hidden form the user. #}
                    <th
                        data-column-id="uuid"
                        data-type="string"
                        data-identifier="true"
                        data-visible="false"
                        visibleInSelection="false"
                    >
                        {{ lang._('%s')|format('ID') }}
                    </th>
                </tr>
            </thead>
            <tbody
{%      if this_field['style']|default('') == 'log' %}
{#  # style field is probably overloaded here,
    # supposed to also be used for css class(es) #}
                    {# This is a style for displaying log files.
                       It will respect whitespace and use a fixed-width font
                       for better readability. We override the style and
                       font of the tbody element. #}
                style="
                    white-space: pre;
                    font-family: Menlo, Monaco, Consolas, 'Courier New', monospace;
                    font-size: small;"
{%      elseif this_field['style']|default('') != '' %}
{#              # This is for if another style is specified in the form data. #}
                style="{{ this_field['style'] }}"
{%      endif %}
            ></tbody>
            <tfoot>
                <tr>{# Start a new row for our buttons. #}
{%      if (this_field['api']['add']|default('') != '' or
            this_field['api']['del']|default('') != '') %}
{#  We use the index from the foreach loop above
    to put the commands one column after the last field. #}
                    <td colspan="{{ (index + 1) }}"></td>
                    <td>
{%          if this_field['api']['add']|default('') != '' %}
                        <button
                            data-action="add"
                            type="button"
                            class="btn btn-xs btn-default"
                        >
                            <span class="fa fa-plus"></span>
                        </button>
{%          endif %}
{%          if this_field['api']['del']|default('') != '' %}
                        <button
                            data-action="deleteSelected"
                            type="button"
                            class="btn btn-xs btn-default"
                        >
                            <span class="fa fa-trash-o"></span>
                        </button>
{%          endif %}
                    </td>
{%      endif %}
{%      if (this_field['api']['export']|default('') != '' or
            this_field['api']['export']|default('') != '') %}
                </tr>
{#  # Close the previous row, and start a new one.
    # This still looks good even when there isn't an add/del button. #}
                <tr>
{#  # We use the index from the foreach loop above
    # to put the commands one column after the last field. #}
                    <td colspan="{{ (index + 1) }}"></td>
                    <td>
{%          if this_field['api']['export']|default('') != '' %}
                        <button
                            id="btn_bootgrid_{{ safe_id }}_export"
                            data-toggle="tooltip"
                            title="{{ lang._('%s')|format('Download') }}"
                            type="button"
                            class="btn btn-xs btn-default"
                        >
                            <span class="fa fa-cloud-download"></span>
                        </button>
{%          endif %}
{%          if this_field['api']['import']|default('') != '' %}
                        <button
                            id="btn_bootgrid_{{ safe_id }}_import"
                            data-toggle="tooltip"
                            title="{{ lang._('%s')|format('Upload') }}"
                            type="button"
                            class="btn btn-xs btn-default"
                        >
                            <span class="fa fa-cloud-upload"></span>
                        </button>
{%          endif %}
                    </td>
{%      endif %}
                </tr>
            </tfoot>
        </table>
    </td>
</tr>
{%  endif %}
