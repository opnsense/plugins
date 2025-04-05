#! /usr/bin/env python3

"""
Build an OpenApi spec.

It's intended to be called by configd, but CLI args will be added.

Calls `parse_endpoints.py` and `parse_xml_models.py` if their cached JSON output is not found.
"""

import os
from collections import defaultdict
from typing import Any, Dict, List, Literal, TypeAlias

import openapi_spec_validator as oasv
from apispec import APISpec

from parse_endpoints import Endpoint, Parameter, get_endpoints
from parse_xml_models import Model, XmlNode, get_models

SpecType: TypeAlias = Literal["string"] | Literal["boolean"] | Literal["integer"] | Literal["number"] | Literal["array"] | Literal["object"]

# Given a Field class from PHP, get the OpenApi data type
FIELD_TO_SPEC_TYPE: Dict[str, SpecType] = defaultdict(
    lambda: "string",
    {
        "AccountField": "array",
        "AliasField": "array",
        "ArrayField": "array",
        "AuthenticationServerField": "array",
        "AuthGroupField": "array",
        "AutoNumberField": "integer",
        "BooleanField": "boolean",
        "CaContainerField": "object",
        "CAsField": "array",
        "CertificateContainerField": "object",
        "CertificateField": "array",
        "CertificatesField": "array",
        "CharonLogLevelField": "array",
        "CheckipField": "array",
        "ClientField": "array",
        "ConfigdActionsField": "array",
        "ConnnectionField": "array",
        "ContainerField": "object",
        "CountryField": "array",
        "CSVListField": "array",
        "CustomPolicyField": "object",
        "ExitNodeField": "array",
        "FilterRuleContainerField": "object",
        "FilterRuleField": "array",
        "GatewayField": "array",
        "GroupField": "array",
        "GroupMembershipField": "array",
        "InstanceField": "array",
        "IntegerField": "integer",
        "InterfaceField": "array",
        "InterfaceField": "array",
        "InterfaceField": "array",
        "IPsecProposalField": "array",
        "JsonKeyValueStoreField": "array",
        "LaggInterfaceField": "array",
        "MemberField": "array",
        "ModelRelationField": "array",
        "NeighborField": "array",
        "NetworkAliasField": "array",
        "NumericField": "number",
        "OpenVPNServerField": "array",
        "OptionField": "array",
        "PolicyContentField": "array",
        "PolicyRulesField": "array",
        "PoolsField": "array",
        "PortField": "array",
        "PrivField": "array",
        "PrivField": "array",
        "ProtocolField": "array",
        "ScheduleField": "array",
        "ServerField": "array",
        "ServiceField": "array",
        "SourceNatRuleContainerField": "object",
        "SourceNatRuleField": "array",
        "SPDField": "array",
        "TosField": "array",
        "TunableField": "array",
        "UnboundInterfaceField": "array",
        "UserGroupField": "array",
        "VipField": "array",
        "VipInterfaceField": "array",
        "VirtualIPField": "array",
        "VlanInterfaceField": "array",
        "VTIField": "array",
    },
)


# OpenApi primitives can only have these constraints. Any other tags in the XML need to be
# understood and processed (e.g. OptionValues implies an enum).
PRIMITIVE_VALIDATORS: Dict[str, List[str]] = {
    "string": [
        "minLength",
        "maxLength",
        "format",
        "pattern",
    ],
    "boolean": [],  # TODO
    "integer": [
        "minimum",
        "exclusiveMinimum",
        "maximum",
        "exclusiveMaximum",
    ],
    "number": [],  # TODO
}

CONSTRAINTS: Dict[str, List[str]] = {
}


def get_spec_type(node: XmlNode) -> SpecType | None:
    """
    If the element hs a type attribute, we can look it up. But some elements are freeform objects
    without a type attribute.

    ASSUMPTION: elements without a type, where no children have a type either, are not objects;
    they are either properties of objects, or property constraints, or property modifiers
    """
    field_type = node.type

    if field_type is None:
        def has_typed_children(node: XmlNode) -> bool:
            return (
                node.type is not None or
                any(has_typed_children(child) for child in node.properties) or
                False
            )
        if has_typed_children(node):
            return "object"
        return None
    return FIELD_TO_SPEC_TYPE[field_type]


# Played around with ways to type this output, and it doesn't seem worth it.
# A dict is wanted, and the logic is all in here.
def get_model_spec(node: XmlNode) -> Dict[str, Any]:
    """
    Does the heavy lifting. The output becomes the schema for the request body or response, for
    endpoints that use this model.

    Looks like an XmlNode is one of:
    - a type (primitive/array/object)
    - the parent is an object and the node is a property
    - the parent is a property or primitive
    """

    spec: Dict[str, Any] = {}

    _type = get_spec_type(node)
    if _type is not None:
        spec["type"] = _type

    match _type:
        case None:
            pass

        case "array":
            # In an OpenApi spec, the element is defined by the `items` property of the
            # array, but in the XML, a single tag defines the element and the array of it.
            # So, hack a new node so we can isolate the logic with a recursive call.
            #
            # AFAICS, array types are always object, and arrays are never mixed.
            # Arrays of primitives are defined by the Multiple attribute, so will not
            # hit this case.

            item_node = XmlNode(
                type="ContainerField",
                name=node.name,
                value=node.value,
                properties=node.properties,
            )

            item_type = get_model_spec(item_node)
            spec["items"] = item_type

        case "object":
            spec["properties"] = props = {}
            required = []
            for prop in node.properties:
                pd = get_model_spec(prop)
                if pd.pop("required", False):
                    required.append(prop.name)
                props[prop.name] = pd
            if required:
                spec["required"] = required

        case _:
            for prop in node.properties:
                # also: nullable, readOnly, writeOnly
                if prop.name in PRIMITIVE_VALIDATORS[_type]:
                    spec[prop.name] = prop.value
                else:
                    # TODO: collect these and handle them. E.g. Mask, Multiple. They ought to be
                    # in the FieldType.
                    # logger.warning(f"{prop.name} is not valid for {_type}")
                    pass


    # props = [get_model_spec(prop) for prop in node.properties]
    # spec["properties"] = props
    return spec


def get_parameter(param: Parameter) -> Dict:
    return {
        "in": "path",
        "name": param.name,
        "schema": {"type": "string"},  # TODO
        "required": True,  # to support optional path params, you need another operation without the param :-(
    }


def get_operation(endpoint: Endpoint) -> Dict[str, Any]:
    method = endpoint.method.lower()
    model = endpoint.model or "status"
    ref = f"#/components/schemas/{model}"

    responses = {
        "200": {
            "description": endpoint.description,
            "content": {
                "application/json": {
                    "schema": {
                        "$ref": ref,
                    }
                },
            },
        },
    }

    op: Dict[str, Any] = {"responses": responses}
    if endpoint.parameters:
        op["parameters"] = [get_parameter(p) for p in endpoint.parameters]

    return {method: op}


def get_path_spec(endpoint: Endpoint) -> Dict[str, Any]:
    params = [f"{{{p.name}}}" for p in endpoint.parameters]
    path = "/".join([endpoint.path] + params)
    return {
        "path": path,
        "description": endpoint.description,
        "operations": get_operation(endpoint),
    }


# The APISpec library is looking less like it's worth the import. It saves little effort, and
# doesn't validate the spec.
# Left in for now, but will likely just build a dict directly.
from apispec import APISpec
def get_spec(models: List[Model], endpoints: List[Endpoint]) -> APISpec:
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
        spec.path(**get_path_spec(endpoint))

    return spec


def validate_spec(spec: APISpec):
    oasv.validate_spec(spec.to_dict())  # type: ignore


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
