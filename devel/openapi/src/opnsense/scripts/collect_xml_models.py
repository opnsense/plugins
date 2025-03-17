#! /usr/bin/env python3

import json
import os
from collections import defaultdict
from typing import Any, Dict, List, Sequence, TypedDict, NotRequired, Tuple, Literal, Callable, TypeAlias, Self
from xml.etree import ElementTree
from xml.etree.ElementTree import Element
from functools import partial

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


# class Schema(TypedDict)
# class Builder:

#     def push_schema(self, schema):
SpecType: TypeAlias = Literal["string"] | Literal["boolean"] | Literal["integer"] | Literal["number"] | Literal["array"] | Literal["object"]
StringFormat: TypeAlias = (
    Literal["date"] |       # RFC 3339, section 5.6, for example, 2017-07-21
    Literal["date-time"] |  # RFC 3339, section 5.6, for example, 2017-07-21T17:32:28Z
    Literal["password"] |   # a hint to UIs to mask the input
    Literal["byte"] |       # base64
    Literal["binary"] |     # file data
    str                     # extensible
)

TypedDict = object

class SchemaType(TypedDict):
    pass

class Named(TypedDict):
    name: str

class AnyValue(Named):
    pass

#region mixins
class Nullable(TypedDict):
    nullable: NotRequired[bool]

class MinMax(TypedDict):
    minimum: NotRequired[int]
    exclusiveMinimum: NotRequired[bool]
    maximum: NotRequired[int]
    exclusiveMaximum: NotRequired[bool]

class MinMaxLength(TypedDict):
    minLength: NotRequired[int]
    maxLength: NotRequired[int]
#endregion mixins

class NamedType(Named):
    type: SpecType

class Int(NamedType, MinMax):
    type: Literal["integer"]
    format: NotRequired[Literal["int32"] | Literal["int64"]]

class Num(NamedType, MinMax):
    type: Literal["number"]
    format: NotRequired[Literal["float"] | Literal["double"]]

class Bool(NamedType, Named, Nullable):
    type: Literal["boolean"]

class String(NamedType):
    type: Literal["string"]
    format: NotRequired[StringFormat]
    pattern: NotRequired[str]  # partial match

Primitive: TypeAlias = Int | Num | Bool | String

class OneOf(Named):
    oneOf: List[NamedType]

class AnyOf(Named):
    anyOf: List[NamedType]

class ArrayItem(TypedDict):
    type: SpecType

class Ref(TypedDict):
    __ref__: str

class MixedArrayItem(TypedDict):
    oneOf: List[NamedType | Ref]

class Array(NamedType):
    type: Literal["array"]
    items: Self | ArrayItem | Ref

class Object(NamedType):
    type: Literal["array"]
    items: ArrayItem | Ref

def _walk(path: str, tree: Element) -> Dict:
    tag = tree.tag.lower()
    _path = path if tag == "items" else ".".join((path, tag))
    _type = tree.attrib.get("type", "ContainerField")  # BaseModel.php defaults to "ContainerField" when no type attribute in XML
    # spec_type = FIELD_TO_SPEC_TYPE[_type] if _type is not None

    cls_map = defaultdict(
        lambda: String,
        {
            "BooleanField": Bool,
        }
    )
    cls = cls_map[_type] if _type is not None else None

    print(_path)

    kwargs = {}
    for child in tree:
        r = _walk(_path, child)
        kwargs.update(r)


class BaseField:
    def __init__(self, ref=None, tagname=None):
        self.internalReference = ref
        self.internalXMLTagName = tagname
    @property
    def __reference(self): self.internalReference  # model or prop mount path, e.g. "bind.dnsbl.general"
    def addChildNode(self, tagName, fieldObject): pass
    def setParentModel(self, model): pass
    def isContainer(self): return False
    def isArrayType(self): return False
class ContainerField(BaseField): pass
def __init__(self):
    self.internalData = ContainerField()
    items_element = None  # load xml and get the items tag
    parseXml(self, items_element, config_data=None, internal_data=self.internalData)  # type: ignore

def parseXml(self, xml: Element, config_data: Element, internal_data: BaseField):
    for xmlNode in xml:
        tagName = xmlNode.tag
        xmlNodeType = xmlNode.attrib.get("type", None)  # BaseModel.php defaults to "ContainerField" when no type attribute in XML
        if xmlNodeType is None:
            field_rfcls = ContainerField
        else:
            if "\\" in xmlNodeType:
                if xmlNodeType.startswith(".\\"):
                    raise NotImplementedError("See BaseModel.php")
                else:
                    classname = xmlNodeType
                field_rfcls = BaseField  # $this->getNewField($classname);
            else:
                field_rfcls = BaseField  # $this->getNewField("OPNsense\\Base\\FieldTypes\\" . $xmlNodeType);

        new_ref = f"{internal_data.__reference}.{tagName}" if internal_data.__reference else tagName

        fieldObject = field_rfcls(new_ref, tagName)  # type: ignore
        fieldObject.setParentModel(self)
        if xmlNode.attrib.get("volatile", None) == "true":
            raise NotImplementedError("See BaseModel.php")

#         // now add content to this model (recursive)
        if not fieldObject.isContainer():
            internal_data.addChildNode(tagName, fieldObject)
            if len(xmlNode) > 0:
                # the php model can have a custom parser
                for fieldMethod in xmlNode:
                    method_name = "set" + fieldMethod.tag
                    def noop(_): pass
                    method = getattr(field_rfcls, method_name, noop)
                    method(parseOptionData(self, fieldMethod))
        else:
            config_section_data = None  # In BaseModel.php, this is read from config.xml
            if fieldObject.isArrayType():
                # handle Array types, recurring items
                tagUUID = internal_data.generateUUID()
                child_node = fieldObject.newContainerField(f"{fieldObject.__reference}.{tagUUID}", tagName)
                child_node.setInternalIsVirtual()  # presumably because: There's no content in config.xml for this array node.
                parseXml(self, xmlNode, config_section_data, child_node)
                fieldObject.addChildNode(tagUUID, child_node)
            else:
                # All other node types (Text,Email,...)
                parseXml(self, xmlNode, config_section_data, fieldObject)

            internal_data.addChildNode(xmlNode.tag, fieldObject);


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

        items = tree.find("items")  # type: ignore
        if items is None:
            raise ValueError(f"Failed to find the <items> tag")
        #endregion validation

        path = mount_element.text.replace("//OPNsense/", "").replace("/", ".").lower()
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
            # models.update(models_from_file)
    return models
