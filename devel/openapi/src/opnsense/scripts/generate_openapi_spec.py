#! /usr/bin/env python3

import sys
import re
from typing import List, Dict, TypedDict, Literal

# https://github.com/globality-corp/openapi
from openapi.model import (
    ApiKeySecurity,
    BasicAuthenticationSecurity,
    BodyParameter,
    CollectionFormat,
    CollectionFormatWithMulti,
    Contact,
    Definitions,
    Examples,
    ExternalDocs,
    FileSchema,
    FormDataParameterSubSchema,
    Header,
    HeaderParameterSubSchema,
    Headers,
    Info,
    JsonReference,
    License,
    MediaTypeList,
    MimeType,
    Oauth2AccessCodeSecurity,
    Oauth2ApplicationSecurity,
    Oauth2ImplicitSecurity,
    Oauth2PasswordSecurity,
    Oauth2Scopes,
    Operation,
    ParameterDefinitions,
    ParametersList,
    PathItem,
    PathParameterSubSchema,
    Paths,
    PrimitivesItems,
    QueryParameterSubSchema,
    Response,
    ResponseDefinitions,
    Responses,
    Schema,
    SchemaAwareDict,
    SchemaAwareList,
    SchemaAwareString,
    SchemesList,
    Security,
    SecurityDefinitions,
    SecurityRequirement,
    Swagger,
    Tag,
    VendorExtension,
    Xml,
)

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
                responses=Responses({
                    "200": Response(
                        description="TODO - parse description",
                    )
                })
            )

        paths[path] = PathItem(**ops)

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
