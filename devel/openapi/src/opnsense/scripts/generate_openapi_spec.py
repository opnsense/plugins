#! /usr/bin/env python3

"""
Build an OpenApi spec.

It's intended to be called by configd, but CLI args will be added.

Calls `parse_endpoints.py` and `parse_xml_models.py` if their cached JSON output is not found.
"""

import os
from collections import defaultdict
from typing import Any, Dict, List, Literal, Tuple, TypeAlias

import openapi_spec_validator as oasv
from apispec import APISpec

from parse_endpoints import Endpoint, Parameter, get_endpoints
from parse_xml_models import XmlModel, XmlNode, get_models


# XML tags that are not properties
QUALIFIERS = [
    "Mask",  # regex pattern
    "ValidationMessage", # blah
    "Constraints", # more complex
    "Default",
    "Required",
    "Multiple",
    "BlankDesc",
]


ARRAY_FIELD_TYPES = [
    "AliasField",
    "ArrayField",
    "CAsField",
    "CertificatesField",
    "ClientField",
    "ConnnectionField",
    "FilterRuleField",
    "GatewayField",
    "GroupField",
    "InstanceField",
    "NeighborField",
    "PolicyRulesField",
    "ServerField",
    "SourceNatRuleField",
    "SPDField",
    "TunableField",
    "VipField",
    "VTIField"
]


def get_model_spec(node: XmlNode) -> Dict[str, Any]:
    """
    Does the heavy lifting. The output becomes the schema for the request body or response, for
    endpoints that use this model.

    Looks like an XmlNode is one of:
    - a type (primitive/array/object)
    - the parent is an object and the node is a property
    - the parent is a property or primitive
    """

    props = []
    quals = []
    for child in node.children:
        if child.name in QUALIFIERS:
            quals.append(child)
        else:
            props.append(child)

    is_primitive = not any(props)
    is_multiple = any(q for q in quals if q.name == "Multiple")
    is_array = is_multiple
    is_enum = any(p for p in props if p.name == "OptionValues")

    has_single_child = len(props) == 1
    if has_single_child:
        prop = props[0]
        is_array = is_multiple or prop.type in ARRAY_FIELD_TYPES

    if is_enum:
        if not has_single_child:
            raise ValueError("enum expected to be primitive")
        spec = {
            "type": "string",
            "enum": [p.name for p in props[0].children],
        }
    elif is_primitive:
        spec = {
            "type": "string",
        }
    else:
        _props = {prop.name: get_model_spec(prop) for prop in props}
        spec = {
            "type": "object",
            "properties": _props,
        }

    if is_array or is_multiple:
        spec = {
            "type": "array",
            "items": spec,
        }
    return spec


def get_path_parameter_spec(param: Parameter) -> Dict:
    return {
        "in": "path",
        "name": param.name,
        "schema": {"type": "string"},  # TODO
        "required": True,  # to support optional path params, you need another operation without the param :-(
    }


def resolve_component_path(
    endpoint: Endpoint,
    component_schemas: Dict[str, Dict]
) -> Tuple[str | None, str | None]:

    client_prop = None
    if endpoint.model and endpoint.model_path_map:
        client_prop, model_path = endpoint.model_path_map.split(":", maxsplit=2)
        breadcrumbs = model_path.split(".")
        tree: Dict[str, Dict] = component_schemas.get(endpoint.model)  # type: ignore
        model_path = endpoint.model
        while breadcrumbs:
            prop = breadcrumbs[0]
            if "properties" in tree:
                tree = tree["properties"][prop]
                model_path = f"{model_path}/properties/{prop}"
                breadcrumbs = breadcrumbs[1:]
            elif "items" in tree:
                tree = tree["items"]
                model_path = f"{model_path}/items"
            else:
                raise KeyError(f"could not find {prop} in {model_path}")
    else:
        model_path = endpoint.model

    return client_prop, model_path


def get_operation(endpoint: Endpoint, component_schemas: Dict[str, Dict]) -> Dict[str, Any]:
    client_prop, model_path = resolve_component_path(endpoint, component_schemas)

    model_path = model_path or "status"
    schema = {"$ref": f"#/components/schemas/{model_path}"}
    if client_prop:
        schema = {
            "type": "object",
            "properties": {
                client_prop: schema,
            }
        }

    content = {
        "application/json": {
            "schema": schema
        },
    }

    responses = {
        "200": {
            "description": endpoint.description,
            "content": content,
        },
    }

    op = {
        "operationId": endpoint.operation_id,
        "responses": responses,
    }
    if endpoint.parameters:
        op["parameters"] = [get_path_parameter_spec(p) for p in endpoint.parameters]

    method = endpoint.method.lower()
    if method == "post" and endpoint.requires_body:
        op["requestBody"] = {
            "required": True,
            "content": content,
        }

    return {method: op}


def get_spec(models: List[XmlModel], endpoints: List[Endpoint]) -> APISpec:
    spec = APISpec(
        title="OPNsense API",
        version="25.1",
        openapi_version="3.1.0",
        info={"description": "API for managing your OPNsense firewall"},
    )

    # TODO: probably needs to be "result" and have "result" prop? needs investigation
    default_schema_name = "status"
    default_schema = {
        "type": "object",
        "properties": {
            "status": {"type": "string"}
        }
    }
    spec.components.schema(default_schema_name, default_schema)


    for model in models:
        component = get_model_spec(model)
        spec.components.schema(model.schema_path, component)

    for endpoint in endpoints:
        operation = get_operation(endpoint, spec.components.schemas)
        spec.path(path=endpoint.path, description=endpoint.description, operations=operation)

    return spec


def validate_spec(spec: APISpec):
    from referencing import Resource
    from referencing.exceptions import Unresolvable
    try:
        oasv.validate_spec(spec.to_dict())  # type: ignore
    except KeyboardInterrupt:
        raise
    except Unresolvable as ex:
        args = []
        for arg in ex.args:
            if isinstance(arg, Resource):
                contents = str(arg.contents)
                if len(contents) > 400:
                    contents = f"{contents[0:400]}..."
                arg = arg.__class__(contents=contents, specification=arg._specification)
            args.append(arg)
        raise ex.__class__(*args).with_traceback(None) from None
    except Exception as ex:
        msg = str(ex)
        if len(msg) > 400:
            msg = f"{msg[0:400]}..."
        raise Exception(msg).with_traceback(None) from None


if __name__ == "__main__":
    path_filter = "/firewall"  # dev convenience; lets us work on one path at a time
    # # TODO: argparse. Expecting arg[1] to be, e.g., "/usr/local/opnsense/www/openapi.yml"
    output_file = os.path.realpath("openapi.yml")

    endpoints = get_endpoints()
    endpoints = [ep for ep in endpoints if not ep.path.startswith("/auth")]  # TODO: disallowed in API?
    endpoints = [ep for ep in endpoints if ep.path.startswith(path_filter)]
    # print(endpoints)

    model_names = set(ep.model for ep in endpoints)
    models = get_models()
    models = [m for m in models if m.schema_path in model_names]

    spec = get_spec(models, endpoints)
    validate_spec(spec)

    yaml = spec.to_yaml()  # or json
    with open(output_file, "w") as file:
        file.write(yaml)
