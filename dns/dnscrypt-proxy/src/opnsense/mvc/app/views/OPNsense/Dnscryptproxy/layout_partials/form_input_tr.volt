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
 #
 # -----------------------------------------------------------------------------
 #}

{##-
 # This is a partial for building an HTML table row within a tab (form).
 #
 # This gets called by base_form.volt, and base_dialog.volt.
 #
 # Expects to receive an array by the name of this_field.
 #
 # The following keys may be used in this partial:
 #
 # this_field['id']                : unique id of the attribute
 # this_field['type']              : type of input or field. Valid types are:
 #           text                    single line of text
 #           password                password field for sensitive input. The contents will not be displayed.
 #           textbox                 multiline text box
 #           checkbox                checkbox
 #           dropdown                single item selection from dropdown
 #           select_multiple         multiple item select from dropdown
 #           hidden                  hidden fields not for user interaction
 #           info                    static text (help icon, no input or editing)
 #           command                 command button, with optional input field
 #           radio                   radio buttons
 #           managefile              upload/download/remove box for a file
 #           startstoptime           time input for a start time, and stop time.
 # this_field['label']             : attribute label (visible text)
 # this_field['size']              : size (width in characters) attribute if applicable
 # this_field['height']            : height (length in characters) attribute if applicable
 # this_field['help']              : help text
 # this_field['advanced']          : property "is advanced", only display in advanced mode
 # this_field['hint']              : input control hint
 # this_field['style']             : css class to add
 # this_field['width']             : width in pixels if applicable
 # this_field['allownew']          : allow new items (for list) if applicable
 # this_field['readonly']          : if true, input fields will be readonly
 # this_field['start_hour_id']     : id for the start hour field
 # this_field['start_min_id']      : id for the start minute field
 # this_field['stop_hour_id']      : id for the stop hour field
 # this_field['stop_min_id']       : id for the stop minute field
 # this_field['button_label']      : label for the command button
 # this_field['input']             : boolean field to enable input on command field
 # this_field['buttons']['button'] : array of buttons for radio button field
 #}

<tr id="row_{{ this_field['id'] }}" {% if this_field['advanced']|default(false)=='true' %} data-advanced="true"{% endif %}{% if this_field['hidden']|default('false') == 'true' %}style="display: none;"{% endif %}>
{# ------ Column 1 - Item label ----- #}
    <td>
        <div class="control-label" id="control_label_{{ this_field['id'] }}">
{%  if this_field['help']|default(false) %}
                <a id="help_for_{{ this_field['id'] }}" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
{%  elseif this_field['help']|default(false) == false %}
                <i class="fa fa-info-circle text-muted"></i>
{%  endif %}
{%  if this_field['label']|default('') != '' %}
                <b>{{ this_field['label'] }}</b>
{%  endif %}
        </div>
    </td>
{# ----- Column 2 - Item field + help message. ----- #}
    <td>
{%  if this_field['type']|default('') == "text" %}
            <input type="text" class="form-control {{ this_field['style']|default('') }}" size="{{ this_field['size']|default("50") }}" id="{{ this_field['id'] }}" {{ this_field['readonly']|default(false) ? 'readonly="readonly"' : '' }}{% if this_field['hint']|default(false) %} placeholder="{{ this_field['hint'] }}"{% endif %}>
{%  elseif this_field['type']|default('') == "hidden" %}
            <input type="hidden" id="{{ this_field['id'] }}" class="{{ this_field['style']|default('') }}" >
{%  elseif this_field['type']|default('') == "checkbox" %}
            <input type="checkbox"  class="{{ this_field['style']|default('') }}" id="{{ this_field['id'] }}" {% if this_field['onclick']|default(false) %}onclick="{{ this_field['onclick'] }}"{% endif %}>
{%  elseif this_field['type']|default('') == "select_multiple" %}
            <select multiple="multiple"
{%      if this_field['size']|default(false) %}data-size="{{ this_field['size'] }}"{% endif %}
                    id="{{ this_field['id'] }}"
                    class="{{ this_field['style']|default('selectpicker') }}"
{%      if this_field['hint']|default(false) %}data-hint="{{ this_field['hint'] }}"{% endif %}
                    data-width="{{ this_field['width']|default("334px") }}"
                    data-allownew="{{ this_field['allownew']|default("false") }}"
                    data-sortable="{{ this_field['sortable']|default("false") }}"
                    data-live-search="true"
                    {%- if this_field['separator']|default(false) %}data-separator="{{ this_field['separator'] }}"{% endif %}
            ></select>{% if this_field['style']|default('selectpicker') != "tokenize" %}<br />{% endif %}
            <a href="#" class="text-danger" id="clear-options_{{ this_field['id'] }}"><i class="fa fa-times-circle"></i> <small>{{ lang._('Clear All') }}</small></a>
{%  elseif this_field['type']|default('') == "dropdown" %}
            <select data-size="{{ this_field['size']|default(10) }}" id="{{ this_field['id'] }}" class="{{ this_field['style']|default('selectpicker') }}" data-width="{{ this_field['width']|default("334px") }}"></select>
{%  elseif this_field['type']|default('') == "password" %}
            <input type="password" class="form-control {{ this_field['style']|default('') }}" size="{{ this_field['size']|default("43") }}" id="{{ this_field['id'] }}" {{ this_field['readonly']|default(false) ? 'readonly="readonly"' : '' }} >
{%  elseif this_field['type']|default('') == "textbox" %}
            <textarea class="{{ this_field['style']|default('') }}" rows="{{ this_field['height']|default("5") }}" id="{{ this_field['id'] }}" {{ this_field['readonly']|default(false) ? 'readonly="readonly"' : '' }}{% if this_field['hint']|default(false) %} placeholder="{{ this_field['hint'] }}"{% endif %}></textarea>
{%  elseif this_field['type']|default('') == "info" %}
            <span  class="{{ this_field['style']|default('') }}" id="{{ this_field['id'] }}"></span>
{%  elseif this_field['type']|default('') == "managefile" %}
            <input id="{{ this_field['id'] }}" type="text" class="form-control hidden">{% if this_field['style']|default('') == "classic" %}<label id="lbl_{{ this_field['id'] }}"></label><br>{% endif %}
            <label class="input-group-btn form-control" style="display: inline;">
                <label class="btn btn-default" id="btn_{{ this_field['id'] }}_select" {% if this_field['style']|default('') == "classic" %}style="padding: 2px;padding-bottom: 3px;max-width: 348px;width: 100%;"{% endif %}> {# Figure out how to attach a tooltip here #}
                    {%- if this_field['style']|default("") != "classic" %} {# if we're using classic style, don't add icons. field may be overloaded, supposed to be css class(es) for other fields #}
                    <i class="fa fa-fw fa-folder-o" id="inpt_{{ this_field['id'] }}_icon"></i>
                    <i id="inpt_{{ this_field['id'] }}_progress"></i>
                    {%- endif %}
                    <input type="file" class="form-control {% if this_field['style']|default("") != "classic" %}hidden{% endif %}" for="{{ this_field['id'] }}" style="display: block;" accept="text/plain">
                </label>
            </label>
{%      if this_field['style']|default("") != "classic" %}{# if we're using classic style, no need to display this box #}
{#          # Explicit style is used here for alignment with the downloadbox button, and matching the size of the button.
            # This input element gets no id to prevent getFormData() from picking it up, using for attr to identify. #}
            <input class="form-control" type="text" readonly="" size="{{ this_field['size']|default("36") }}" for="{{ this_field['id'] }}" style="height: 34px;padding-left:11px;display: inline;">
{%      endif %}
            {# This if statement is just to get the spacing between the download/upload buttons to be consistent #}
{%      if this_field['style']|default("") == "classic" %}&nbsp{% endif %}<button class="btn btn-default" type="button" id="btn_{{ this_field['id'] }}_upload" title="{{ lang._('Upload selected file')}}" data-toggle="tooltip" style="display:inline;">
                <i class="fa fa-fw fa-upload"></i>
            </button>
            <button class="btn btn-default" type="button" id="btn_{{ this_field['id'] }}_download" title="{{ lang._('Download')}}" data-toggle="tooltip">
                <i class="fa fa-fw fa-download"></i>
            </button>
            <button class="btn btn-danger" type="button" id="btn_{{ this_field['id'] }}_remove" title="{{ lang._('Remove')}}" data-toggle="tooltip">
                <i class="fa fa-fw fa-trash-o"></i>
            </button>
{%  elseif this_field['type']|default('') == "radio" %}
            {# We define a hidden input to hold the value of the setting from the config #}
            <input type="text" class="form-control {{ this_field['style']|default('') }} hidden" size="{{ this_field['size']|default("50") }}" id="{{ this_field['id'] }}" readonly="readonly">
            <div class="radio">
{# XXX Need some validation here #}
{%      for button in this_field['buttons']['button']|default({}) %}
                    &nbsp;&nbsp;&nbsp;<label><input
                    type="radio"
                    name="rdo_{{ this_field['id'] }}"
                    value="{{ button['@attributes']['value'] }}"
                    />{{ lang._('%s')| format (button[0]) }}</label>
{%      endfor %}
            </div>
{%  elseif this_field['type']|default('') == "command" %}
{%      if this_field['input']|default('') == "true" %}
                <input id="inpt_{{ this_field['id'] }}_command"
                       class="form-control"
                       type="text"
                       size="{{this_field['size']|default("36")}}"
                       style="height: 34px;padding-left:11px;display: inline;"
                >
{%      endif %}
            <button id="btn_{{ this_field['id'] }}_command"
                    class="btn btn-primary"
                    type="button"
            >{{ lang._('%s') | format(this_field['button_label']) }}&nbsp;<i id="btn_{{ this_field['id'] }}_progress"></i></button>
{%  elseif this_field['type']|default('') == "startstoptime" %} {# Not sure of a really good name #}
{#          The structure and elements mostly came from the original firewall_schedule_edit.php #}
{#          We define a hidden input to hold the value of the setting from the config #}
{%      if this_field['start_hour_id']|default('') != '' and
            this_field['start_min_id']|default('') != '' and
            this_field['stop_hour_id']|default('') != '' and
            this_field['stop_min_id']|default('') != '' %}
            <table style="background-color: inherit;"> {# Make the background inherit from the row. #}
                <tr>
                    <td>{{ lang._('Start Time') }}</td>
                    <td>{{ lang._('Stop Time') }}</td>
                </tr>
                <tr>
{%          for time, ids in {"start":[this_field['start_hour_id'], this_field['start_min_id']], "stop":[this_field['stop_hour_id'], this_field['stop_min_id']]} %}
                    <td>
                        <div> {# Original div used input-group class, but this causes
                               # z-index issues with the dropdown menu appearing behind boxes below it. So it's been removed. #}
{#                          These <select> elements will trigger dropdown boxes getting added. #}
                            <select class="selectpicker form-control" data-width="55" data-size="10" data-live-search="true" id="{{ ids[0] }}">
{#                          # The setFormData() assumes all <selects> are backed by an array datatype like an OptionField type in the model.
                            # When retreiving the data through the search API, it expects to receive an array. That array should be the OptionValues
                            # described in the model. It will then sift through the array, and locate any with the selected=>1 and mark them as such.
                            # When this field is erroneously backed by a non-array type field, it results in one option being added to the list:
                            #  <option value="resolve" selected="selected"></option>
                            # This is a result of a "bug" in jQuery in the .each() function. Attempting to iterate through an empty string will result
                            # in only the word 'resolve' being returned. The following code demonstrates this behavior:
                            #  var r = 0;
                            #  var str = '';
                            #  for (r in str) {
                            #     console.log(r);
                            #  } #}
                            </select>
                            <select class="selectpicker form-control" data-width="55" data-size="10" data-live-search="true" id="{{ ids[1] }}">
                            </select>
                        </div>
                    </td>
{%          endfor %}
                </tr>
            </table>
{%      endif %}
{%  endif %}
{%  if this_field['help']|default(false) %}
            <div class="hidden" data-for="help_for_{{ this_field['id'] }}">
                <small>{{ this_field['help'] }}</small>
            </div>
{%  endif %}
    </td>
{# ------ Column 3 - Used to show validation failure messages ------ #}
    <td>
        <span class="help-block" id="help_block_{{ this_field['id'] }}"></span>
    </td>
</tr>
