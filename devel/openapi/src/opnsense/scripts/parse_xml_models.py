#! /usr/bin/env python3

"""
Find XML model files and parse into intermediate DTOs.
"""

import json
import os
from typing import List
from xml.etree import ElementTree
from xml.etree.ElementTree import Element as XmlElement

from pydantic import BaseModel, RootModel

EXCLUDE_MODEL = "mvc/app/models/OPNsense/iperf/FakeInstance.xml"


def get_openapi_schema_path(vendor: str, module: str, name: str) -> str:
    """Component path in the OpenApi schema; API ops will $ref to it."""
    return f"{vendor}.{module}.{name}".lower()


#region Intermediate DTOs
# for validation and to smooth over XML child/attribute distinctions
class XmlNode(BaseModel):
    type: str | None
    name: str
    value: str | None
    properties: List["XmlNode"]

# To save passing path recursively, only the root node gets it
class Model(XmlNode):
    schema_path: str
#endregion Intermediate DTOs


def _walk_xml(element: XmlElement) -> XmlNode:
    field_type = element.attrib.get("type", None)
    if field_type and field_type.startswith(".\\"):
        field_type = field_type[2:]

    value = element.text
    value = value.strip() if value else value

    props = []
    for child in element:
        prop = _walk_xml(child)
        props.append(prop)

    return XmlNode(
        type=field_type,
        name=element.tag,
        value=value,
        properties=props
    )


def parse_xml_file(xml_file: str) -> Model:
    path_without_ext = xml_file[0:-4]
    vendor, module, name = path_without_ext.split("/")[-3:]
    schema_path = get_openapi_schema_path(vendor, module, name)

    tree = ElementTree.parse(xml_file)
    items = tree.find("items")
    if items is None:
        raise ValueError("items tag not found")  # never happens; just appeases the linter

    xml_model = _walk_xml(items)
    return Model(**xml_model.model_dump(), schema_path=schema_path)


def get_model_xml_files(base_path: str) -> List[str]:
    """Finds paths of model XML files within mvc/app/models"""
    found = []
    for root, _, files in os.walk(base_path, topdown=True):
        path_segments = root.split("/")
        if path_segments[-3] != "models":  # seems consistent as of v25.1
            continue
        xml_files = [os.path.join(root, f) for f in files if f.endswith(".xml")]
        found.extend(xml_files)
    return found


def get_models(
    base_path: str = "/usr",  # good enough for now
    json_path: str = "./models.json"
) -> List[Model]:

    if os.path.isfile(json_path):
        with open(json_path) as file:
            model_json = file.read()
        _models = json.loads(model_json)
        models = [Model(**m) for m in _models]
        return models

    xml_files = get_model_xml_files(base_path)

    models = []
    for xml_file in xml_files:
        model = parse_xml_file(xml_file)
        models.append(model)

    ModelList = RootModel[List[Model]]
    model_json = ModelList(models).model_dump_json()
    with open(json_path, mode="w") as file:
        file.write(model_json)

    return models


if __name__ == "__main__":
    from pprint import pprint
    pprint(get_models())
