{#
 # Copyright (c) 2021 Manuel Faux
 # OPNsense® is Copyright © 2014-2021 by Deciso B.V.
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

{#
 # Generate input dialog, uses the following parameters (as associative array):
 #
 # fields          :   list of field type objects, see form_input_tr tag for details
 # id              :   form id, used as unique id for this modal form. inner form to place data is called frm_[id]
 #                     save button is identified by btn_[id]_save
 # label           :   dialog label
 #}

{# Volt templates in php7 have issues with scope sometimes, copy input values to make them more unique #}
{% set base_dialog_id=id %}
{% set base_dialog_fields=fields %}
{% set base_dialog_label=label %}

{# Find if there are help supported or advanced field on this page #}
{% set base_dialog_help=false %}
{% set base_dialog_advanced=false %}
{% for field in base_dialog_fields|default({})%}
    {% for name,element in field %}
        {% if name=='help' %}
            {% set base_dialog_help=true %}
        {% endif %}
        {% if name=='advanced' %}
            {% set base_dialog_advanced=true %}
        {% endif %}
    {% endfor %}
    {% if base_dialog_help|default(false) and base_dialog_advanced|default(false) %}
        {% break %}
    {% endif %}
{% endfor %}

<script>
    $(function() {
        // hook into on-show event to extend validation
        $('#{{base_dialog_id}}').on('shown.bs.modal', function (e) {
            $('.nav-tabs a[href="#frm_{{base_dialog_id}}-tab_general"]').tab('show');

            $("#btn_{{base_dialog_id}}_save").click(function() {
                // TODO: Search for tab with validation errors, but currently only the first tab has even validation rules
                $('.nav-tabs a[href="#frm_{{base_dialog_id}}-tab_general"]').tab('show');
            });
        })

        // Read currently selected tab in main form and store for later
        $('#{{base_dialog_id}}').on('show.bs.modal', function (e) {
            $('#{{base_dialog_id}}').attr("data-inittab", window.location.hash);
        });
        // Restore selected tab of main form as URL was modified by dialog tabs
        $('#{{base_dialog_id}}').on('hide.bs.modal', function (e) {
            window.location.hash = $('#{{base_dialog_id}}').attr("data-inittab");
        });
    });
</script>

<div class="modal fade" id="{{base_dialog_id}}" tabindex="-1" role="dialog" aria-labelledby="{{base_dialog_id}}Label" aria-hidden="true">
    <div class="modal-backdrop fade in"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ lang._('Close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="{{base_dialog_id}}Label">{{base_dialog_label}}</h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist" id="dialogtabs">
                    {% for field in base_dialog_fields['tabs']|default({})%}
                    <li>
                        <a data-toggle="tab" href="#frm_{{base_dialog_id}}-tab_{{field[0]}}"><b>{{field[1]}}</b></a>
                    </li>
                    {% endfor %}
                </ul>
                <form id="frm_{{base_dialog_id}}">
                    <div class="content-box tab-content">
                        <div class="table-responsive">
                            <table class="table table-striped table-condensed">
                                <colgroup>
                                    <col class="col-md-3"/>
                                    <col class="col-md-{{ 12-3-msgzone_width|default(5) }}"/>
                                    <col class="col-md-{{ msgzone_width|default(5) }}"/>
                                </colgroup>
                                <tbody>
                                {%  if base_dialog_advanced|default(false) or base_dialog_help|default(false) %}
                                    <tr>
                                        <td>{% if base_dialog_advanced|default(false) %}<a href="#"><i class="fa fa-toggle-off text-danger" id="show_advanced_formDialog{{base_dialog_id}}"></i></a> <small>{{ lang._('advanced mode') }}</small>{% endif %}</td>
                                        <td colspan="2" style="text-align:right;">
                                            {% if base_dialog_help|default(false) %}<small>{{ lang._('full help') }}</small> <a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_formDialog{{base_dialog_id}}"></i></a>{% endif %}
                                        </td>
                                    </tr>
                                {% endif %}
                                </tbody>
                            </table>
                        </div>
                        {% for tab in base_dialog_fields['tabs']|default({})%}
                        <div id="frm_{{base_dialog_id}}-tab_{{tab[0]}}" class="tab-pane fade">
                            <div class="table-responsive">
                                <table class="table table-striped table-condensed">
                                    <colgroup>
                                        <col class="col-md-3"/>
                                        <col class="col-md-{{ 12-3-msgzone_width|default(5) }}"/>
                                        <col class="col-md-{{ msgzone_width|default(5) }}"/>
                                    </colgroup>
                                    <tbody>
                            {% for field in tab[2]|default({})%}
                                {# looks a bit buggy in the volt templates, field parameters won't reset properly here #}
                                {% set advanced=false %}
                                {% set help=false %}
                                {% set hint=false %}
                                {% set style=false %}
                                {% set maxheight=false %}
                                {% set width=false %}
                                {% set allownew=false %}
                                {% set readonly=false %}
                                {% if field['type'] == 'header' %}
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
                                            <th colspan="3">
                                                <h2>{{field['label']}}</h2>
                                                {%- if field['hint']|default(false) %}
                                                <small>{{field['hint']}}</small>
                                                {%- endif %}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                {% else %}
                                  {{ partial("layout_partials/form_input_tr",field)}}
                                {% endif %}
                            {% endfor %}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        {% endfor %}
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                {% if hasSaveBtn|default('true') == 'true' %}
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="btn_{{base_dialog_id}}_save">{{ lang._('Save') }} <i id="btn_{{base_dialog_id}}_save_progress" class=""></i></button>
                {% else %}
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
                {% endif %}
            </div>
        </div>
    </div>
</div>
