#! /usr/bin/env python3

import json
import os
import subprocess
import sys
from typing import Dict, List, NewType, Optional, Self, Tuple
from xml.etree import ElementTree
from xml.etree.ElementTree import Element

from pydantic import BaseModel, RootModel

EXCLUDE_MODELS = ["mvc/app/models/OPNsense/iperf/FakeInstance.xml"]


ModuleName = NewType('ModuleName', str)


class Model(BaseModel):
    type: str
    name: str
    path: str
    value: Optional[str]
    properties: List[Self]
    option_values: Optional[List[str]] = None


class FieldType(BaseModel):
    name: str
    module: ModuleName
    parent: Optional[Self]
    properties: List[str]
    is_container: bool

    def new(self, **kwargs) -> Model:
        return Model(type=self.name, **kwargs)


def explode_php_name(class_name: str) -> Tuple[ModuleName, str]:
    segments = class_name.split("\\")
    # TODO: what about vendors other than OPNsense? e.g. Deciso\\Proxy
    return ModuleName(segments[-3]), segments[-1]


class FieldTypeRegistry:
    _cache: Dict[ModuleName, Dict[str, FieldType]] = {}

    @classmethod
    def get(cls, module: ModuleName, name: str) -> FieldType | None:
        """First looks for the type in the specified module, then in the Base module"""
        Base = ModuleName("Base")
        to_search = (module, Base) if module and module != Base else (Base,)
        for _module in to_search:
            module_cache = cls._cache.get(_module, {})
            field_type = module_cache.get(name, None)
            if field_type:
                return field_type

    @classmethod
    def load(cls, json_path):
        def register_bottom_up(ft):
            php_name = ft["name"]
            module, name = explode_php_name(php_name)

            field_type = cls.get(module, name)
            if field_type:
                return field_type

            parent_php_name = ft.pop("parent")
            if parent_php_name:
                parent_ft = fields_by_php_name.get(parent_php_name)
                parent = register_bottom_up(parent_ft)
            else:
                parent = None

            field_type = FieldType(
                name=name,
                module=module,
                parent=parent,
                **{k: v for k, v in ft.items() if k not in ("name", "parent")},
            )

            module_cache = cls._cache.get(module, None)
            if not module_cache:
                module_cache = cls._cache[module] = {}
            module_cache[name] = field_type

            return field_type

        if not os.path.exists(json_path):
            # php_args = ["/usr/bin/php", os.path.realpath("./ParseFieldTypes.php"), f"-o='{json_path}'"]
            # subprocess.Popen(php_args, cwd=os.path.dirname(__file__))
            # # without shell, php errors on fopen.
            php_args = f"/usr/bin/php {os.path.realpath("./ParseFieldTypes.php")} -o='{json_path}'"
            subprocess.run(php_args, check=True, text=True, shell=True)

        with open(json_path) as file:
            field_json = file.read()
        fields_by_php_name = json.loads(field_json)

        for ft in fields_by_php_name.values():
            register_bottom_up(ft)


def _walk(module: ModuleName, path: str, element: Element) -> Model:
    field_type = element.attrib.get("type", "ContainerField")
    is_legacy = field_type.startswith(".\\")
    if is_legacy:
        raise NotImplementedError("TODO: handle legacy types")

    field = FieldTypeRegistry.get(module, field_type)
    if field is None:
        raise ValueError(f"Unknown field type: {field_type}")

    name = element.tag.lower()
    value = element.text
    value = value.strip() if value else value
    child_path = path if name == "items" else f"{path}.{name}"

    props = []
    for child in element:
        # TODO: figure out how to handle tags that don't have setXxx methods in the PHP model
        # if not (
        #     field.is_container or
        #     child.tag in field.properties or
        #     any([t.lower() == child.tag.lower() for t in field.properties])
        # ):
        #     raise ValueError(f"{field_type} does not have a '{child.tag}' property")
        prop = _walk(module, child_path, child)
        props.append(prop)
    option_values = []
    return field.new(name=name, path=path, value=value, properties=props, option_values=option_values)


def parse_xml_file(xml_file):
    vendor, module, name = xml_file[0:-4].split("/")[-3:]
    path = f"{vendor}.{module}.{name}".lower()
    module = ModuleName(module)

    tree = ElementTree.parse(xml_file)

    items = tree.find("items")
    if items is None:
        raise ValueError("items tag not found")

    model = _walk(module, path, items)
    return model


def get_model_xml_files(source: str):
    found = []
    for root, _, files in os.walk(source, topdown=True, followlinks=True):
        path_segments = root.split("/")
        if path_segments[-3] != "models":
            continue
        _files = [os.path.join(root, f) for f in files if f.endswith(".xml")]
        found.extend(_files)
    return found


ModelList = RootModel[List[Model]]

def get_models(
    field_type_json_path: str,
    model_json_path: str,
    xml_file_filter: str | None = None
) -> List[Model]:

    if os.path.isfile(model_json_path) and not xml_file_filter:
        with open(model_json_path) as file:
            model_json = file.read()
        _models = json.loads(model_json)
        models = [Model(**m) for m in _models]
        return models

    FieldTypeRegistry.load(field_type_json_path)

    source = "/gitroot/upstream/opnsense" if "HOSTNAME" in os.environ else "/usr"  # TODO: remove once I understand the build system
    xml_files = get_model_xml_files(source)
    if xml_file_filter:
        xml_files = [m for m in xml_files if xml_file_filter in m]

    models = []
    for xml_file in xml_files:
        try:
            model = parse_xml_file(xml_file)
        except NotImplementedError as ex:  # TODO: delete this before merging!
            # logger.warning(ex)
            continue

        models.append(model)

    # Don't write json if we don't have all the models
    if not xml_file_filter:
        model_json = ModelList(models).model_dump_json()
        with open(model_json_path, mode="w") as file:
            file.write(model_json)

    return models


if __name__ == "__main__":
    # partial string match. E.g. `parse_xml_models.py Auth`` will filter by files with "Auth" in the path.
    xml_file_filter = sys.argv[1] if len(sys.argv) > 1 else None

    # TODO: make CLI param
    field_type_json_path = os.path.realpath("./field_types.json")
    model_json_path = os.path.realpath("./models.json")

    get_models(field_type_json_path, model_json_path, xml_file_filter=xml_file_filter)
