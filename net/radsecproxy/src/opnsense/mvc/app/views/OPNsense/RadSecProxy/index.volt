<h1>RadSecProxy</h1>
{{ partial("layout_partials/base_form",['fields':basicForm,'id':'frm_BasicSettings'])}}


<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':['tabs':[['basic_settings','Basic'], ['clients','Client'], ['servers','Servers'], ['realms','Realms']]]])}}
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':['tab':['basic_settings','Basic', basicForm]]]) }}
</div>