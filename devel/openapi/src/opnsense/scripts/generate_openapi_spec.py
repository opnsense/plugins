#! /usr/bin/env python3

import json
import os
import re
import sys
from typing import Dict, List, Any

from apispec import APISpec
from openapi_spec_validator import validate

from collect_api_endpoints import Endpoint, collect_api_modules
from collect_xml_models import collect_models


def get_endpoints(source_path: str) -> Dict[str, List[Endpoint]]:
    dot = os.path.dirname(__file__)
    json_file = os.path.join(dot, "endpoints.json")

    if os.path.isfile(json_file):
        with open(json_file) as file:
            endpoints_by_model = json.loads(file.read())
    else:
        endpoints_by_model = collect_api_modules(source_path)
        with open(json_file, mode="w") as file:
            file.write(json.dumps(endpoints_by_model))

    return endpoints_by_model


def get_models(source_path: str) -> Dict[str, Dict]:
    dot = os.path.dirname(__file__)
    json_file = os.path.join(dot, "models.json")

    if os.path.isfile(json_file):
        with open(json_file) as file:
            models = json.loads(file.read())
    else:
        models = collect_models(source_path)
        with open(json_file, mode="w") as file:
            file.write(json.dumps(models))

    return models


def get_spec(endpoints: List[Endpoint]):
    param_pattern = re.compile(r"^\$(?P<name>\w+)(=(?P<default>.*))?$")

    spec = APISpec(
        title="OPNsense API",
        version="25.1",
        openapi_version="3.0.0",
        info={"description": "API for managing your OPNsense firewall"},
    )

    for endpoint in endpoints:
        path = f'/api/{endpoint["module"]}/{endpoint["controller"]}/{endpoint["command"]}'

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

            param_schema: Dict[str, Any] = {"type": "string"}  # fuck these cack-typed langs
            param_def = {
                "name": name,
                "in": "query",
                "schema": param_schema
            }

            if default is None:
                param_def["required"] = True
            else:
                param_schema["default"] = default

            param_defs.append(param_def)

        ops = {}
        method = endpoint.get("method", "GET").lower()
        methods = ["get", "post"] if method == "*" else [method]
        for method in methods:
            ops[method] = {
                "parameters": param_defs,
                "responses": {
                    "200": {"description": "OK"}
                }
            }

        spec.path(path=path, operations=ops)

    validate(spec.to_dict())  # type: ignore
    return spec


def write_spec(spec: APISpec, path: str):
    json_spec = json.dumps(spec.to_dict())
    with open(path, mode="w") as file:
        file.write(json_spec)


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} OUTPUT_FILE")
        exit(1)

    source_path = "/gitroot/upstream/opnsense/plugins" #"/usr/plugins"  # TODO: param for this
    output_file = sys.argv[1]

    # endpoints_by_model = get_endpoints(source_path)
    # endpoints = []
    # for e in endpoints_by_model.values():
    #     endpoints.extend(e)

    models = get_models(source_path)

    # spec = get_spec(endpoints)
    # write_spec(spec, output_file)
