#! /usr/bin/env python3

import sys
from typing import List, Dict, TypedDict, Literal

# https://github.com/globality-corp/openapi
from openapi.model import Swagger, Info, Operation, PathItem, Paths, Response, Responses

from collect_api_endpoints import collect_api_modules

class Endpoint(TypedDict):
    method: Literal["GET"] | Literal["POST"] | Literal["*"]
    module: str
    controller: str
    command: str
    parameters: str
    is_abstract: bool
    base_class: str
    filename: str
    model_filename: str | None
    type: str


def get_endpoints(path: str) -> List[Endpoint]:
    collected_endpoints = collect_api_modules(path)
    endpoints = []
    for list_of_lists in collected_endpoints.values():
        _endpoints = [m for sub_list in list_of_lists for m in sub_list]
        endpoints.extend(_endpoints)
    return endpoints


def get_spec(endpoints: List[Endpoint]):
    models = {}
    paths = {}
    for endpoint in endpoints:
        path = f'/{endpoint["module"]}/{endpoint["controller"]}/{endpoint["command"]}'

        model_filename = endpoint["model_filename"]
        # TODO: parse xml, generate json schema

        # parameters = endpoint["parameters"]
        parameters = []  # TODO

        items = {}
        method = endpoint.get("method", "GET").lower()
        methods = ["get", "post"] if method == "*" else [method]
        for method in methods:
            items[method] = Operation(
                parameters=parameters,
                responses=Responses({
                    "200": Response(
                        description="TODO - parse description",
                    )
                })
            )

        paths[path] = PathItem(**items)

    spec = Swagger(
        swagger="2.0",
        info=Info(
            title="OPNsense",
            version="1.0.0",
        ),
        basePath="/api",
        paths = Paths(paths)
    )

    spec.validate()

    return spec


def write_spec(spec: Swagger, path: str):
    with open(path, mode="w") as file:
        file.write(spec.dumps())


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} OUTPUT_FILE")
        exit(1)

    source_path = "/usr/plugins"
    output_file = sys.argv[1]
    endpoints = get_endpoints(source_path)
    spec = get_spec(endpoints)
    write_spec(spec, output_file)
