#! /usr/bin/env python3

# https://github.com/globality-corp/openapi
from openapi.model import Swagger, Info, Operation, PathItem, Paths, Response, Responses

sample_data = {
    'opnsense': [
        [
            {
                'method': 'GET',
                'module': 'opnsense',
                'controller': 'index',
                'is_abstract': False,
                'base_class': 'IndexController',
                'command': 'index',
                'parameters': '',
                'filename': 'IndexController.php',
                'model_filename': None,
                'type': 'Resources'
            }
        ]
    ],
    'openapi': [
        [
            {
                'method': 'GET',
                'module': 'openapi',
                'controller': 'service',
                'is_abstract': False,
                'base_class': 'ApiMutableServiceControllerBase',
                'command': 'reconfigure',
                'parameters': '',
                'filename': 'ServiceController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml',
                'type': 'Service'
            },
            {
                'type': 'Service',
                'method': 'POST',
                'module': 'openapi',
                'controller': 'service',
                'is_abstract': False,
                'base_class': 'ApiMutableServiceControllerBase',
                'command': 'reconfigure',
                'parameters': '',
                'filename': 'ServiceController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            },
            {
                'type': 'Service',
                'method': 'POST',
                'module': 'openapi',
                'controller': 'service',
                'is_abstract': False,
                'base_class': 'ApiMutableServiceControllerBase',
                'command': 'restart',
                'parameters': '',
                'filename': 'ServiceController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            },
            {
                'type': 'Service',
                'method': 'POST',
                'module': 'openapi',
                'controller': 'service',
                'is_abstract': False,
                'base_class': 'ApiMutableServiceControllerBase',
                'command': 'start',
                'parameters': '',
                'filename': 'ServiceController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            },
            {
                'type': 'Service',
                'method': 'GET',
                'module': 'openapi',
                'controller': 'service',
                'is_abstract': False,
                'base_class': 'ApiMutableServiceControllerBase',
                'command': 'status',
                'parameters': '',
                'filename': 'ServiceController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            },
            {
                'type': 'Service',
                'method': 'POST',
                'module': 'openapi',
                'controller': 'service',
                'is_abstract': False,
                'base_class': 'ApiMutableServiceControllerBase',
                'command': 'stop',
                'parameters': '',
                'filename': 'ServiceController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            }
        ],
        [
            {
                'type': 'Service',
                'method': 'GET',
                'module': 'openapi',
                'controller': 'settings',
                'is_abstract': False,
                'base_class': 'ApiMutableModelControllerBase',
                'command': 'get',
                'parameters': '',
                'filename': 'SettingsController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            },
            {
                'type': 'Service',
                'method': 'POST',
                'module': 'openapi',
                'controller': 'settings',
                'is_abstract': False,
                'base_class': 'ApiMutableModelControllerBase',
                'command': 'set',
                'parameters': '',
                'filename': 'SettingsController.php',
                'model_filename': '/usr/plugins/devel/openapi/src/opnsense/mvc/app/models/OPNsense/OpenApi/OpenApi.xml'
            }
        ]
    ]
}


from collect_api_endpoints import collect_api_modules
collected_modules = sample_data
# collected_modules = collect_api_modules("/usr/plugins")

models = {}
paths = {}
for list_of_lists in collected_modules.values():
    modules = [m for sub_list in list_of_lists for m in sub_list]
    for module in modules:
        if module.get('module') != "openapi":
            continue

        path = f"/{module.get('module')}/{module.get('controller')}/{module.get('command')}"

        model_filename = module.get('model_filename')

        items = {}
        method = module.get('method', 'GET').lower()
        methods = ["get", "post"] if method == "*" else [method]
        for method in methods:
            items[method] = Operation(
                parameters=[],
                responses=Responses({
                    "200": Response(
                        description="TODO - parse description",
                    )
                })
            )

        paths[path] = PathItem(**items)


swagger = Swagger(
    swagger="2.0",
    info=Info(
        title="OPNsense",
        version="1.0.0",
    ),
    basePath="/api",
    paths = Paths(paths)
)

swagger.validate()

with open("/usr/local/opnsense/www/openapi.json", mode="w") as file:
    file.write(swagger.dumps())
