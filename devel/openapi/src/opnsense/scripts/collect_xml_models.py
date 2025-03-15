#! /usr/bin/env python3

import json
import sys
from collections import defaultdict
from typing import Any, Dict
from xml.etree import ElementTree
from xml.etree.ElementTree import Element

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


def collect_models(model_filename: str) -> Dict[str, Dict]:
    tree = ElementTree.parse(model_filename)
    root = tree.getroot()

    if root.tag != "model":
        raise ValueError(f"Expected root element of {model_filename} to be a model tag")

    mount_element = tree.find("mount")
    if mount_element is None or mount_element.text is None:
        raise ValueError(f"Failed to find the <mount> tag in {model_filename}")
    mount = mount_element.text.replace("//OPNsense/", "").replace("/", ".")

    items_element: Element = tree.find("items")  # type: ignore
    if items_element is None:
        raise ValueError(f"Failed to find the <items> tag in {model_filename}")

    components = {}
    for model in items_element:
        name = model.tag
        component_name = f"{mount}.{name}"  # e.g. relayd.general

        properties = {}
        for prop in model:
            prop_name = prop.tag  # e.g. enabled, interval, log
            field_type = prop.attrib.get("type")
            if not field_type:
                msg = f"Element <{prop_name}> does not have a type attribute in {model_filename}"
                raise ValueError(msg)

            spec_type = VALIDATOR_TO_SPEC_TYPE[field_type]
            field_props: Dict[str, Any] = {"type": spec_type}

            # TODO: enums
            # enum_el = prop.find("OptionValues")

            req_el = prop.find("Required")
            req_el = req_el if req_el is not None else prop.find("required")
            if req_el is not None and req_el.text and req_el.text.upper() == "Y":
                field_props["required"] = True

            # TODO: constraints

            # type: number
            # minimum: 0
            # exclusiveMinimum: true
            # maximum: 50

            # type: string
            # minLength: 3
            # maxLength: 20
            # pattern: '^\d{3}-\d{2}-\d{4}$'  # partial match by default

            properties[prop_name] = field_props

        components[component_name] = {"properties": properties}

    return components


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} XML_MODEL_FILE")
        exit(1)

    model_filename = sys.argv[1]
    models = collect_models(model_filename)
    print(json.dumps(models))
