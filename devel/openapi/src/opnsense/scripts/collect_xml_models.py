#! /usr/bin/env python3

import json
import os
from collections import defaultdict
from typing import Any, Dict, List, Sequence, TypedDict, NotRequired, Tuple
from xml.etree import ElementTree
from xml.etree.ElementTree import Element


EXCLUDE_MODELS = ["mvc/app/models/OPNsense/iperf/FakeInstance.xml"]

SPECIAL_CASES = {
    "/Tor/General.xml": "items"
}

VALIDATOR_TO_SPEC_TYPE = defaultdict(
    lambda: "string",
    {
        "AccountField": "array",
        # "AliasContentField": "string",
        # "AliasesField": "string",
        "AliasField": "array",
        # "AliasNameField": "string",
        # "ApiKeyField": "string",
        "ArrayField": "array",
        "AuthenticationServerField": "array",
        "AuthGroupField": "array",
        "AutoNumberField": "integer",
        # "Base64Field": "string",
        # "BaseListField": "string",
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
        # "DescriptionField": "string",
        # "DomainIPField": "string",
        # "EmailField": "string",
        "ExitNodeField": "array",
        # "ExpiresField": "string",
        "FilterRuleContainerField": "object",
        "FilterRuleField": "array",
        "GatewayField": "array",
        # "GidField": "string",
        "GroupField": "array",
        "GroupMembershipField": "array",
        # "GroupNameField": "string",
        # "HostField": "string",
        # "HostnameField": "string",
        # "IKEAddressField": "string",
        "InstanceField": "array",
        "IntegerField": "integer",
        "InterfaceField": "array",
        "InterfaceField": "array",
        "InterfaceField": "array",
        # "IPPortField": "string",
        "IPsecProposalField": "array",
        "JsonKeyValueStoreField": "array",
        # "KeaPoolsField": "string",
        "LaggInterfaceField": "array",
        # "LegacyLinkField": "string",
        # "LinkAddressField": "string",
        # "MacAddressField": "string",
        "MemberField": "array",
        "ModelRelationField": "array",
        "NeighborField": "array",
        "NetworkAliasField": "array",
        # "NetworkField": "string",
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
        # "RangeAddressField": "string",
        # "RemoteHostField": "string",
        "ScheduleField": "array",
        "ServerField": "array",
        "ServiceField": "array",
        # "SimpleCustomField": "string",
        "SourceNatRuleContainerField": "object",
        "SourceNatRuleField": "array",
        "SPDField": "array",
        # "StoreB64Field": "string",
        # "TextField": "string",
        "TosField": "array",
        "TunableField": "array",
        # "UidField": "string",
        "UnboundInterfaceField": "array",
        # "UniqueIdField": "string",
        # "UpdateOnlyTextField": "string",
        # "UrlField": "string",
        "UserGroupField": "array",
        # "UsernameField": "string",
        "VipField": "array",
        "VipInterfaceField": "array",
        # "VipNetworkField": "string",
        "VirtualIPField": "array",
        "VlanInterfaceField": "array",
        # "VPNIdField": "string",
        "VTIField": "array",
    },
)


class PropertySchema(TypedDict):
    type: str
    required: NotRequired[List[str]]


class ModelSchema(TypedDict):
    type: str
    required: NotRequired[List[str]]
    properties: Dict[str, PropertySchema]


def _find_model_elements_and_handle_special_case(model_filename: str, items_element: Element) -> List[Element]:
    key = next((k for k in SPECIAL_CASES if model_filename.endswith(k)), None)
    if key:
        model_tag = SPECIAL_CASES[key]
        if items_element.tag == model_tag:
            return [items_element]
        if isinstance(model_tag, str):
            model_tag = [model_tag]
        return [el for el in [items_element.find(tag) for tag in model_tag] if el]

    return find_model_elements(items_element)


def find_model_elements(element: Element) -> List[Element]:
    if not [child for child in element]:
        # raise ValueError(f"Looking for a model but we find ourselves at a leaf node")
        return []
    if all("type" in child.attrib for child in element):
        return [element]

    elements = []
    for child in element:
        elements.extend(find_model_elements(child))
    return elements


def _parse_property_from_element(element: Element) -> Tuple[str, bool, PropertySchema]:
    prop_name = element.tag  # e.g. enabled, interval, log
    field_type = element.attrib.get("type")
    if not field_type or not field_type.endswith("Field"):
        children = find_model_elements(element)
        if not children:
            msg = f"Element <{prop_name}> does not have a field type attribute"
            raise ValueError(msg)

        first_child_type = children[0].attrib["type"]
        first_child_spec_type = VALIDATOR_TO_SPEC_TYPE[first_child_type]
        if first_child_spec_type == "array":
            if len(children) > 1:
                msg = f"Element <{element.tag}> has multiple ArrayField children"
                raise ValueError(msg)

        child_models = []
        for child in children:
            # field_type = child.attrib.get("type")
            child_models.append(_parse_model_from_element(child))

    spec_type = VALIDATOR_TO_SPEC_TYPE[field_type]
    prop_def: PropertySchema = {"type": spec_type}

    req_el = element.find("Required")
    req_el = req_el if req_el is not None else element.find("required")
    is_required = req_el is not None and bool(req_el.text) and req_el.text.upper() == "Y"

    # TODO: defaults, constraints, objects and arrays

    return prop_name, is_required, prop_def


def _parse_model_from_element(model_element: Element) -> Dict[str, ModelSchema]:
    models = {}

    name = model_element.tag
    name = "" if name.lower() == "items" else name

    if name in models:
        raise ValueError(f"Model already defined at '{name}'")

    properties = {}
    required_props = []
    model: ModelSchema = {
        "type": "object",
        "required": required_props,
        "properties": properties,
    }

    for prop in model_element:
        prop_name, is_required, prop_schema = _parse_property_from_element(prop)
        properties[prop_name] = prop_schema
        if is_required:
            required_props.append(prop_name)

    models[name] = model
    return models


def parse_model(model_filename: str) -> Dict[str, ModelSchema]:
    try:
        tree = ElementTree.parse(model_filename)
        root = tree.getroot()

        #region validation
        if root.tag != "model":
            raise ValueError(f"Expected root element to be a model tag")

        mount_element = tree.find("mount")
        if mount_element is None or mount_element.text is None:
            raise ValueError(f"Failed to find the <mount> tag")
        mount = mount_element.text.replace("//OPNsense/", "").replace("/", ".")

        items_element: Element = tree.find("items")  # type: ignore
        if items_element is None:
            raise ValueError(f"Failed to find the <items> tag")
        #endregion validation

        model_elements = _find_model_elements_and_handle_special_case(model_filename, items_element)

        # the qualified name we will use in $ref will be the mount point in the config.xml.
        # if there are container elements within the items element, the container tag will be appended.
        # e.g.:
        #   - relayd.general: this is the mount point, the properties are direct children of <items>
        #   - bind.domain.domains: the mount point is bind.domain, <domains> is a direct child of <items>
        qualified_model_schemas = {}
        for model_element in model_elements:
            print(model_element)
            model_schemas = _parse_model_from_element(model_element)
            for name, model_schema in model_schemas.items():
                qual_name = (f"{mount}.{name}" if name else mount).lower()
                qualified_model_schemas[qual_name] = model_schema

        return qualified_model_schemas

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
