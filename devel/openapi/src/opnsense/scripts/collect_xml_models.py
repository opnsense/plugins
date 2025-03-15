#! /usr/bin/env python3

import json
import os
from collections import defaultdict
from typing import Any, Dict, List
from xml.etree import ElementTree
from xml.etree.ElementTree import Element


EXCLUDE_MODELS = ["mvc/app/models/OPNsense/iperf/FakeInstance.xml"]

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


def find_model_containers(element: Element) -> List[Element]:
    if all("type" in child.attrib for child in element):
        return [element]

    elements = []
    for child in element:
        elements.extend(find_model_containers(child))
    return elements


def _parse_model_from_element(model_container: Element) -> Dict:
    models = {}
    for model_element in model_container:
        name = model_element.tag
        print(name)
        name = "" if name.lower() == "items" else name

        if name in models:
            raise ValueError(f"Model already defined at '{name}'")

        properties = {}
        required_props: List[str] = []
        model = {
            "type": "object",
            "required": required_props,
            "properties": properties,
        }

        for prop in model_element:
            prop_name = prop.tag  # e.g. enabled, interval, log
            field_type = prop.attrib.get("type")
            if not field_type:
                msg = f"Element <{prop_name}> does not have a type attribute"
                raise ValueError(msg)

            spec_type = VALIDATOR_TO_SPEC_TYPE[field_type]
            prop_def = {"type": spec_type}

            req_el = prop.find("Required")
            req_el = req_el if req_el is not None else prop.find("required")
            if req_el is not None and req_el.text and req_el.text.upper() == "Y":
                required_props.append(prop_name)

            properties[prop_name] = prop_def

        models[name] = model
    return models


def parse_model(model_filename: str) -> Dict[str, Dict]:
    try:
        tree = ElementTree.parse(model_filename)
        root = tree.getroot()

        if root.tag != "model":
            raise ValueError(f"Expected root element to be a model tag")

        mount_element = tree.find("mount")
        if mount_element is None or mount_element.text is None:
            raise ValueError(f"Failed to find the <mount> tag")
        mount = mount_element.text.replace("//OPNsense/", "").replace("/", ".")

        items_element: Element = tree.find("items")  # type: ignore
        if items_element is None:
            raise ValueError(f"Failed to find the <items> tag")

        model_containers = find_model_containers(items_element)

        model_schema = {}
        for model_container in model_containers:
            print(model_container)
            models = _parse_model_from_element(model_container)
            for name, model_def in models.items():
                name = f"{mount}.{name}" if name else mount  # e.g. relayd.general
                model_schema[name] = model_def

        return model_schema

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
