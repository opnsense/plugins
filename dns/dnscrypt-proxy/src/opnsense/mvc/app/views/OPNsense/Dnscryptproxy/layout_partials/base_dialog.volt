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
 # Builds input dialog for bootgrids, uses the following parameters (as associative array):
 #
 # this_field['target']              :   id of the dialog's parent field.
 # this_field['dialog']          :   array dialog defined for the bootgrid
 # this_field['dialog']['label'] :   Label for the dialog
 # this_field['dialog']['field'] :   array of fields for this dialog
 #
 #}
{%  if this_field['target'] is defined %} {# Only do this if we have a target. #}
{# Create an id derived from the target, replace periods with underscores. #}
<?php $safe_id = preg_replace('/\./','_',$this_field['target']); ?>
{# Volt templates in php7 have issues with scope sometimes, copy input values to make them more unique #}
{%      set base_dialog_id = safe_id %}
{%      set base_dialog_elements = this_field['dialog'] %}
{%      set base_dialog_label = base_dialog_elements['label'] %}
{#      Find if there are help supported or advanced field on this page #}
{%      set base_dialog_help = false %}
{%      set base_dialog_advanced = false %}
{%      set fields = get_fields(base_dialog_elements|default({})) %}
{%      for field in fields %}
{%          if field is iterable %}
{%              for name,element in field %}
{%                  if name == 'help' %}
{%                      set base_dialog_help = true %}
{%                  endif %}
{%                  if name == 'advanced' %}
{%                      set base_dialog_advanced = true %}
{%                  endif %}
{%              endfor %}
{%              if base_dialog_help|default(false) and base_dialog_advanced|default(false) %}
{%                  break %}
{%              endif %}
{%          endif %}
{%      endfor %}
{# The id here has to match the same value as is populated in data-editDialog attribute on the bootgrid table. #}
<div class="modal fade" id="bootgrid_dialog_{{ base_dialog_id }}" tabindex="-1" role="dialog" aria-labelledby="{{ base_dialog_id }}_Label" aria-hidden="true">
    <div class="modal-backdrop fade in"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="{{ base_dialog_id }}_Label">{{ base_dialog_label|default('Edit') }}</h4>
            </div>
            <div class="modal-body">
{#              Must match what's defined in data-editDialog attribute on bootgrid table: params['set']+uuid, 'frm_' + editDlg, function(){ #}
                <form id="frm_bootgrid_dialog_{{ base_dialog_id }}">
                  <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <colgroup>
                            <col class="col-md-3"/>
                            <col class="col-md-{{ 12-3-msgzone_width|default(5) }}"/>
                            <col class="col-md-{{ msgzone_width|default(5) }}"/>
                        </colgroup>
                        <tbody>
{%      if base_dialog_advanced|default(false) or base_dialog_help|default(false) %}
                        <tr>
                            <td>{% if base_dialog_advanced|default(false) %}<a href="#"><i class="fa fa-toggle-off text-danger" id="show_advanced_formDialog_{{ base_dialog_id }}"></i></a> <small>{{ lang._('advanced mode') }}</small>{% endif %}</td>
                            <td colspan="2" style="text-align:right;">
                                {% if base_dialog_help|default(false) %}<small>{{ lang._('full help') }}</small> <a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_formDialog_{{ base_dialog_id }}"></i></a>{% endif %}
                            </td>
                        </tr>
{%      endif %}
{%      for field in fields|default({})%}
{%          if field is iterable %}
{%              if field['type']|default('') == 'header' %}
{#                      close table and start new one with header #}

{# macro base_dialog_header(field) #}
      </tbody>
    </table>
  </div>
  <div class="table-responsive {{field['style']|default('')}}">
    <table class="table table-striped table-condensed">
        <colgroup>
            <col class="col-md-3"/>
            <col class="col-md-{{ 12-3-msgzone_width|default(5) }}"/>
            <col class="col-md-{{ msgzone_width|default(5) }}"/>
        </colgroup>
        <thead>
          <tr{% if field['advanced']|default(false)=='true' %} data-advanced="true"{% endif %}>
            <th colspan="3"><h2>{{field['label']}}</h2></th>
          </tr>
        </thead>
        <tbody>
{# endmacro #}

{%              else %}
{{                      partial("OPNsense/Dnscryptproxy/layout_partials/form_input_tr",['this_field':field]) }}
{%              endif %}
{%          endif %}
{%      endfor %}
                        </tbody>
                    </table>
                  </div>
                </form>
            </div>
            <div class="modal-footer">
{%      if hasSaveBtn|default('true') == 'true' %}
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="btn_bootgrid_dialog_{{ base_dialog_id }}_save">{{ lang._('Save') }} <i id="btn_bootgrid_dialog_{{ base_dialog_id }}_save_progress" class=""></i></button>
{%      else %}
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
{%      endif %}
            </div>
        </div>
    </div>
</div>
{%  endif %}
