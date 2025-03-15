#! /usr/bin/env python3

import json
import os
import re
import sys
from typing import Dict, List

# https://github.com/globality-corp/openapi
from openapi.model import (
    FormDataParameterSubSchema,
    Info,
    Operation,
    ParametersList,
    PathItem,
    Paths,
    Response,
    Responses,
    SchemaAwareDict,
    SchemaAwareList,
    SchemaAwareString,
    Swagger,
)

from collect_api_endpoints import Endpoint, collect_api_modules


def get_spec(endpoints: List[Endpoint]):
    param_pattern = re.compile(r"^\$(?P<name>\w+)(=(?P<default>.*))?$")

    models = {}
    paths = {}
    for endpoint in endpoints:
        path = f'/{endpoint["module"]}/{endpoint["controller"]}/{endpoint["command"]}'

        model_filename = endpoint["model_filename"]
        # TODO: parse xml, generate json schema

        param_strings = endpoint["parameters"]
        param_strings = param_strings.split(",") if param_strings else []
        param_defs = []
        for p in param_strings:
            m = re.match(param_pattern, p)
            if not m:
                raise ValueError(f"failed to parse parameter '{p}' at /api/{path}")

            name = m.group("name")
            default = m.group("default")

            param_def = {
                "name": name,
                "type": "string",  # TODO: can we do better with these cack-typed langs?
                "in": "formData",
            }
            if default is None:
                param_def["required"] = True
            else:
                param_def["default"] = default

            param_defs.append(param_def)

        ops = {}
        method = endpoint.get("method", "GET").lower()
        methods = ["get", "post"] if method == "*" else [method]
        for method in methods:
            params = []
            for param_def in param_defs:
                param = FormDataParameterSubSchema(**param_def)
                param.validate()
                params.append(param)

            ops[method] = Operation(
                parameters=ParametersList(params),
                responses=Responses(
                    {
                        "200": Response(
                            description="TODO - parse description",
                        )
                    }
                ),
            )

        paths[path] = PathItem(**ops)

    spec = Swagger(
        swagger="2.0",
        info=Info(
            title="OPNsense",
            version="1.0.0",
        ),
        basePath="/api",
        paths=Paths(paths),
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

    endpoints_by_model: Dict[str, List[Endpoint]]

    dot = os.path.dirname(__file__)
    json_file = os.path.join(dot, "endpoints.json")
    if os.path.isfile(json_file):
        with open(json_file) as file:
            endpoints_by_model = json.loads(file.read())
    else:
        source_path = "/usr/plugins"
        endpoints_by_model = collect_api_modules(source_path)

    endpoints = []
    for e in endpoints_by_model.values():
        endpoints.extend(e)

    output_file = sys.argv[1]
    spec = get_spec(endpoints)
    write_spec(spec, output_file)
