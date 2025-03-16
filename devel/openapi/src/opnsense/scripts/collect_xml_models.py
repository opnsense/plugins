#! /usr/bin/env python3

import json
import os
from collections import defaultdict
from typing import Any, Dict, List, Sequence, TypedDict, NotRequired, Optional, Tuple, Literal, Callable, TypeAlias
from xml.etree import ElementTree
from xml.etree.ElementTree import Element
from functools import partial
from pydantic import BaseModel

EXCLUDE_MODELS = ["mvc/app/models/OPNsense/iperf/FakeInstance.xml"]

SPECIAL_CASES = {
    "/Tor/General.xml": "items"
}

FIELD_TO_SPEC_TYPE = defaultdict(
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


SpecType: TypeAlias = Literal["string"] | Literal["boolean"] | Literal["integer"] | Literal["number"] | Literal["array"] | Literal["object"]
StringFormat: TypeAlias = (
    Literal["date"] |       # RFC 3339, section 5.6, for example, 2017-07-21
    Literal["date-time"] |  # RFC 3339, section 5.6, for example, 2017-07-21T17:32:28Z
    Literal["password"] |   # a hint to UIs to mask the input
    Literal["byte"] |       # base64
    Literal["binary"] |     # file data
    str                     # extensible
)



class SchemaType(BaseModel):
    pass

class Named(BaseModel):
    name: str

class AnyValue(Named):
    pass

#region mixins
class Nullable(BaseModel):
    nullable: Optional[bool]

class MinMax(BaseModel):
    minimum: Optional[int]
    exclusiveMinimum: Optional[bool]
    maximum: Optional[int]
    exclusiveMaximum: Optional[bool]

class MinMaxLength(BaseModel):
    minLength: Optional[int]
    maxLength: Optional[int]
#endregion mixins

class NamedType(Named):
    type: SpecType

class Int(NamedType, MinMax):
    type: Literal["integer"]
    format: Optional[Literal["int32"] | Literal["int64"]]

class Num(NamedType, MinMax):
    type: Literal["number"]
    format: Optional[Literal["float"] | Literal["double"]]

class Bool(NamedType, Named, Nullable):
    type: Literal["boolean"]

class String(NamedType):
    type: Literal["string"]
    format: Optional[StringFormat]
    pattern: Optional[str]  # partial match

Primitive: TypeAlias = Int | Num | Bool | String

class OneOf(Named):
    oneOf: List[NamedType]

class AnyOf(Named):
    anyOf: List[NamedType]

class ArrayItem(BaseModel):
    type: SpecType

class Ref(BaseModel):
    __ref__: str

class Array(NamedType, SchemaType):
    type: Literal["array"]
    items: ArrayItem | Ref

class Enum()

a: Array = {
    "name": "stuff",
    "type": "array",
    "items": {
        "type": "object"
    }
}
b: Bool = {
    "name": "a",
    "type": "boolean"
}

def _walk(path: str, cls: type, spec: Dict, tree: Element) -> Dict:
    _path = ".".join((path, tree.tag))
    _type = tree.attrib.get("type", None)
    # spec_type = FIELD_TO_SPEC_TYPE[_type] if _type is not None


    if spec_type is None:
        # receiver = expect(None)

    cls = TAG_TO_CLASS
        def
    elif spec_type == "string":
        pass
    elif spec_type == "boolean":
        pass
    elif spec_type == "integer":
        pass
    elif spec_type == "number":
        pass
    elif spec_type == "array":
        pass
    elif spec_type == "object":
        pass
    else:
        raise ValueError(f"unknown spec_type {spec_type}")


    schemas = []
    for child in tree:
        schema = _walk(_path, child)
        schemas.append(schema)


def parse_model(model_filename: str):
    try:
        tree = ElementTree.parse(model_filename)
        root = tree.getroot()
        items: Element

        #region validation
        if root.tag != "model":
            raise ValueError(f"Expected root element to be a model tag")

        mount_element = tree.find("mount")
        if mount_element is None or mount_element.text is None:
            raise ValueError(f"Failed to find the <mount> tag")
        path = mount_element.text.replace("//OPNsense/", "").replace("/", ".").lower()

        items = tree.find("items")  # type: ignore
        if items is None:
            raise ValueError(f"Failed to find the <items> tag")
        #endregion validation

        return _walk(path, items)

    except Exception as ex:
        ex.args = (f"{model_filename}: {ex}", *ex.args[1:])
        raise





def collect_models(source: str):
    models = {}
    for root, _, files in os.walk(source, topdown=True):
        path_segments = root.split("/")
        if path_segments[-3] != "models":
            continue
        model_files = [os.path.join(root, f) for f in files if f.endswith(".xml")]
        for model_file in model_files:
            if any(x for x in EXCLUDE_MODELS if model_file.endswith(x)):
                continue
            models_from_file = parse_model(model_file)
            models.update(models_from_file)
    return models
