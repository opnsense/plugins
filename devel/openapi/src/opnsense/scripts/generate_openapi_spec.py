#! /usr/bin/env python3
from pprint import pprint
import json
import os
import re
import sys
from collections import defaultdict
from typing import (Any, Callable, Concatenate, Dict, List, Literal, NewType,
                    Optional, ParamSpec, Self, Tuple, Type, TypedDict, TypeVar, TypeAlias)

from apispec import APISpec
from openapi_spec_validator import validate

from parse_xml_models import get_models, Model, logger
from parse_endpoints import get_endpoints, Endpoint


SpecType: TypeAlias = Literal["string"] | Literal["boolean"] | Literal["integer"] | Literal["number"] | Literal["array"] | Literal["object"]
StringFormat: TypeAlias = (
    Literal["date"] |       # RFC 3339, section 5.6, for example, 2017-07-21
    Literal["date-time"] |  # RFC 3339, section 5.6, for example, 2017-07-21T17:32:28Z
    Literal["password"] |   # a hint to UIs to mask the input
    Literal["byte"] |       # base64
    Literal["binary"] |     # file data
    str                     # extensible
)

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


def _walk_model(model: Model) -> Dict[str, Any]:
    _type: SpecType = FIELD_TO_SPEC_TYPE[model.type]
    spec: Dict[str, Any] = {"type": _type}

    match _type:
        case "array":
            # logically, an object should be nested in the array, but that isn't done here.
            # So we'll fudge the model to look like an object, then nest it ourselves.
            # AFAICS, array types are always object, and arrays are never mixed.
            md = model.model_dump(exclude={"type", "properties"})
            md["type"] = "ContainerField"
            md["properties"] = model.properties
            _model = Model(**md)

            item_type = _walk_model(_model)
            spec["items"] = item_type

        case "object":
            spec["properties"] = props = {}
            required = []
            for prop in model.properties:
                pd = _walk_model(prop)
                if pd.pop("required", False):
                    required.append(prop.name)
                props[prop.name] = pd
            if required:
                spec["required"] = required
        # case "string":
        # case "boolean":
        # case "integer":
        # case "number":
        case _:
            for prop in model.properties:
                spec[prop.name] = prop.value

    return spec


def get_model_spec(model: Model):
    return _walk_model(model)


def get_endpoint_spec(endpoint: Endpoint) -> Dict[str, Any]:
    method = endpoint.method.lower()
    model_path = endpoint.model
    schema = {"schema": model_path} if model_path else {}
    op = {
        method: {
            "responses": {
                "200": {
                    "content": {
                        "application/json": schema
                    }
                }
            }
        }
    }
    return op

def get_spec(models: List[Model], endpoints: List[Endpoint]):
    spec = APISpec(
        title="OPNsense API",
        version="25.1",
        openapi_version="3.0.0",
        info={"description": "API for managing your OPNsense firewall"},
    )
    for model in models:
        component = get_model_spec(model)
        spec.components.schema(model.path, component)

    for endpoint in endpoints:
        operation = get_endpoint_spec(endpoint)
        spec.path(path=endpoint.path, operations=operation)
    return spec


if __name__ == "__main__":
    output_file = os.path.realpath("openapi.yml")

    models = get_models()
    endpoints = get_endpoints()
    spec = get_spec(models, endpoints)
    yaml = spec.to_yaml()

    with open(output_file, "w") as file:
        file.write(yaml)
    print(yaml)
