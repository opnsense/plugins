{#
# Copyright (c) 2023-2024 Cedrik Pischem
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

{#
 # This template generates a Bootgrid table based on the provided parameters and field definitions.
 #
 # Accepted Parameters:
 # - table_id: (string) The ID of the table.
 # - edit_dialog: (string) The ID of the associated edit dialog.
 # - edit_alert: (string) The ID of the alert section displayed for configuration changes.
 # - fields: (array) A presorted list of field definitions with the following possible keys:
 #   - id: (string) The unique identifier for the field.
 #   - label: (string) The display name of the column.
 #   - type: (string) The type of the field (e.g., 'text', 'checkbox', 'header').
 #   - column_id: (string) The column identifier for rendering (optional; derived from 'id' if not provided).
 #   - data_visible: (string|boolean) Determines if the column is visible in the table ('true' or 'false').
 #   - column_visible: (string|boolean) Determines if the column is generated ('true' or 'false').
 #   - data_type: (string) The data type for the column (e.g., 'string', 'boolean').
 #   - formatter: (string) The formatter for the column (e.g., 'boolean', 'commands').
 #   - width: (string) The width of the column (e.g., '6em').
 #
 # Special Handling:
 # - 'uuid' is always included as a hidden, unique identifier column.
 # - Columns with id='enabled' are rendered with a 'rowtoggle' formatter and specific styling.
 # - 'commands' is a hardcoded column for row actions, rendered at the end of the table.
 #
 # Example Usage:
 # {{ partial("layout_partials/bootgrid_tables", {
 #     'table_id': 'exampleGrid',
 #     'edit_dialog': 'DialogExample',
 #     'edit_alert': 'ConfigurationChangeMessage',
 #     'fields': formDialogExample,
 #     'add_button_id': 'addExampleBtn'
 # }) }}
 #}
<div style="display: block;">
    <table id="{{ table_id }}" class="table table-condensed table-hover table-striped"
           data-editDialog="{{ edit_dialog }}" data-editAlert="{{ edit_alert }}">
        <thead>
            <tr>
                <!-- Hardcoded 'uuid' column at the beginning -->
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>

                <!-- Dynamic columns based on presorted fields -->
                {% for field in fields %}
                    {% if field['type'] != 'header' %}
                        {% set column_id = field['column_id'] %}
                        {% set label = lang._(field['label']) %}
                        {% set data_type = 'string' %}
                        {% set formatter = '' %}

                        {% if field['type'] == 'checkbox' %}
                            {% set data_type = 'boolean' %}
                            {% set formatter = 'boolean' %}
                        {% endif %}

                        {% set data_visible = field['data_visible']|default('true') %}
                        {% set column_visible = field['column_visible']|default('true') %}

                        {% if column_visible == 'true' %}
                            {% if column_id == 'enabled' %}
                                <!-- Special case for 'enabled' column -->
                                <th
                                    data-column-id="{{ column_id }}"
                                    data-width="6em"
                                    data-type="boolean"
                                    data-formatter="rowtoggle"
                                    {% if data_visible == 'false' %} data-visible="false"{% endif %}
                                >{{ label }}</th>
                            {% else %}
                                <!-- General case for other columns -->
                                <th
                                    data-column-id="{{ column_id }}"
                                    data-type="{{ data_type }}"
                                    {% if data_visible == 'false' %} data-visible="false"{% endif %}
                                    {% if formatter %} data-formatter="{{ formatter }}"{% endif %}
                                >{{ label }}</th>
                            {% endif %}
                        {% endif %}
                    {% endif %}
                {% endfor %}

                <!-- Hardcoded 'commands' column at the end -->
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td>
                    <button id="{{ add_button_id }}" data-action="add" type="button" class="btn btn-xs btn-primary">
                        <span class="fa fa-plus"></span>
                    </button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                        <span class="fa fa-trash-o"></span>
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
