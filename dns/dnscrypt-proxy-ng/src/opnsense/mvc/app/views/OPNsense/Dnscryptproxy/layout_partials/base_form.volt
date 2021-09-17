{#
 # Copyright (c) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
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
 # This partial is for building a tab, including all fields on the tab.
 #
 # Expects to receive an array containing the elements from a tab.
 #
 # The following keys are used in this partial:
 #
 # this_tab[0]          : form id, used as unique id for this form.
 # this_tab[1]          : data-title to set on form (tab description)
 # this_tab[2]          : array of elements on this tab
 # this_tab[2]['field'] : array of fields on this tab
 #}

{# Find if there are help supported or advanced field on this page #}
{%  set base_form_id = this_tab[0] %}
{%  set help = false %}
{%  set advanced = false %}
{%  set fields = get_fields(this_tab[2]|default({})) %}
{%  for field in fields %}
{%      if field is iterable %}
{%          for name,element in field %}
{%              if name == 'help' %}
{%                  set help = true %}
{%              endif %}
{%              if name == 'advanced' %}
{%                  set advanced = true %}
{%              endif %}
{%          endfor %}
{%          if help|default(false) and advanced|default(false) %}
{%              break %}
{%          endif %}
{%      endif %}
{%  endfor %}
<form id="frm_tab_{{ base_form_id }}" class="form-inline" data-title="{{ this_tab[1]|default('') }}" data-model="{{ this_tab[2]['model']|default('') }}">
  <div class="table-responsive">
    <table class="table table-striped table-condensed">
        <colgroup>
            <col class="col-md-3"/>
            <col class="col-md-4"/>
            <col class="col-md-5"/>
        </colgroup>
        <tbody>
{%  if advanced|default(false) or help|default(false) %}
        <tr>
            <td style="text-align:left">{% if advanced|default(false) %}<a href="#"><i class="fa fa-toggle-off text-danger" id="show_advanced_frm_{{ base_form_id }}"></i></a> <small>{{ lang._('advanced mode') }}</small>{% endif %}</td>
            <td colspan="2" style="text-align:right">
                {% if help|default(false) %}<small>{{ lang._('full help') }}</small> <a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_frm_{{ base_form_id }}"></i></a>{% endif %}
            </td>
        </tr>
{%  endif %}
{%  for field in fields %}
{%      if field is iterable %} {# Check what we have is an array #}
{%          if field['type']|default('') != '' %}
{%              if field['type'] == 'header' %}
{#              close table and start new one with header #}

{# macro base_dialog_header(field) #}
  </tbody>
</table>
</div>
<div class="table-responsive {{field['style']|default('')}}">
<table class="table table-striped table-condensed table-responsive">
    <colgroup>
        <col class="col-md-3"/>
        <col class="col-md-4"/>
        <col class="col-md-5"/>
    </colgroup>
    <thead>
      <tr {% if field['advanced']|default(false)=='true' %} data-advanced="true"{% endif %}>
        <th colspan="3">
            <h2>
{%                  if field['help']|default(false) %}
{%                      if field['id'] is not defined and field['label'] is defined %} {# Use the header or label, whichever is defined #}
{# Swap out all non-valid characters for an underscorw, hopefully the result will be unique. #}
<?php $safe_label = preg_replace('/[^a-zA-Z0-9_-]/','_',$field['label']); ?>
{%                          set header_id = safe_label %}
{%                      elseif field['id'] is defined %}
{%                          set header_id = field['id'] %}
{%                      endif %}
                    <a id="help_for_{{ header_id|default('') }}" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
{%                  elseif field['help']|default(false) == false %}
                    <i class="fa fa-info-circle text-muted"></i>
{%                  endif %}
                {{ field['label']|default('') }}
            </h2>
{%                  if field['help']|default(false) %}
                <div class="hidden" data-for="help_for_{{ header_id|default('') }}">
                    <small>{{field['help']}}</small>
                </div>
{%                  endif %}
        </th>
      </tr>
    </thead>
    <tbody>
{# endmacro #}
{%              elseif field['type'] == 'separator' %}
{#              # close the table that was started earlier,
                # start a new table, and put an empty row #}
            </tbody>
        </table>
    </div>
    <div class="table-responsive {{field['style']|default('')}}">
        <table class="table table-striped table-condensed table-responsive">
            <colgroup>  {# We need to define again the column groups #}
                <col class="col-md-3"/>
                <col class="col-md-4"/>
                <col class="col-md-5"/>
            </colgroup>
        <thead>
        <tr {% if field['advanced']|default(false)=='true' %} data-advanced="true"{% endif %}>
            <th colspan="3"> {# This header should span all three columns #}
                <br> {# This is just an empty header to create a visual space #}
            </th>
        </tr>
    </thead>
    <tbody>
{%              elseif field['type'] == 'bootgrid' %}
{#              # We hijack the type field for the bootgrid so we can inject it
                # as a whole row instead of with form_intput_tr
                # Technically doesn't have to be a separate partial, but just
                # keeping it separate for now since it's so large.
                # Load in our bootgrid partial #}
{{              partial("OPNsense/Dnscryptproxy/layout_partials/form_bootgrid_tr",['this_field':field]) }}
{%              elseif field['type'] == 'button' %}
{#              # We hijack the type field again for injecting a button
                # Validate that the necessary fields are set #}
{%                  if field['id']|default('') != '' %}
{%                      if field['label']|default('') != '' %}
                        <tr>
                            <td colspan="3">
                                <button
                                    class="btn btn-primary" id="{{ field['id']|default('') }}"
                                    data-label="{{ lang._('%s') | format(field['label']) }}"
                                    {# /usr/local/opnsense/www/js/opnsense_ui.js:SimpleActionButton() #}
                                    {# These fields are expected by the SimpleActionButton() to label, and attach click event. #}
                                    data-endpoint="{{ field['api']|default('') }}"
                                    data-error-title="{{ lang._('%s') | format(field['error']|default('')) }}"
                                    data-service-widget="{{field['widget']|default('')}}"
                                    type="button"
                                ></button>
                            </td>
                        </tr>
{%                      endif %}
{%                  endif %}
{%              elseif field['type'] == 'commandoutput' %}
{#                  We're putting this here because we need the command output to be wider than any single column. #}
{%                  if field['id']|default('') != '' %}
                        <tr>
                            <td colspan="3">
                                <pre id="pre_{{ field['id']|default('') }}_output" style="white-space: pre-wrap;">{{ field['text']|default('') }}</pre>
                            </td>
                        </tr>
{%                  endif %}
{%              else %}
{{                  partial("OPNsense/Dnscryptproxy/layout_partials/form_input_tr",['this_field':field]) }}
{%              endif %}
{%          endif %}
{%      endif %}
{%  endfor %}
{%  if this_tab[2]['apply']|default(false) == "true" %}
        <tr>
            <td colspan="3">
                <button class="btn btn-primary" id="save_frm_{{ base_form_id }}" type="button"><b>{{ lang._('Apply') }} </b><i id="frm_tab_{{ base_form_id }}_progress" class=""></i></button>
            </td>
        </tr>
{%  endif %}
        </tbody>
    </table>
  </div>
</form>
