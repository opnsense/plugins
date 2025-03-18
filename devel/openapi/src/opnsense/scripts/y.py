#! /usr/bin/env python

import subprocess
from pprint import pprint
from timeit import default_timer
import json
import os
import sys
import re
import logging
from collections import defaultdict
from functools import partial
from pydantic import BaseModel, SkipValidation
from typing import *
from xml.etree import ElementTree
from xml.etree.ElementTree import Element
from collect_xml_models import *

with open(f"{os.environ["HOME"]}/.pyrc") as file: exec(file.read())


logger = logging.getLogger(f"{__file__}")
logger.setLevel(logging.WARNING)
logger.addHandler(logging.StreamHandler())
time_logger = logger.getChild("timing")
time_logger.setLevel(logging.DEBUG)


ModuleName = NewType('ModuleName', str)


def pithy(obj):
    for attr in ("func.__name__", "name", "tag", "field_type"):
        try:
            _obj = obj
            for attr in attr.split("."):
                _obj = getattr(_obj, attr)
            break
        except AttributeError:
            continue
    s = str(_obj)
    return f"{s[:20]}..." if len(s) > 22 else s


def measure_time(func):
    def wrapper(*args, **kwargs):
        t1 = default_timer()
        result = func(*args, **kwargs)
        t2 = default_timer()
        _args = ", ".join([pithy(a) for a in args])
        _kwargs = ", ".join([f"{k}={v}" for k, v in kwargs.items()])
        _params = ", ".join([p for p in (_args, _kwargs) if p])
        time_logger.debug(f'{func.__name__}({_params}) executed in {(t2-t1):.6f}s')
        return result
    return wrapper


def _walk(module: ModuleName, element: Element) -> Field:
    field_type = element.attrib.get("type", "ContainerField")
    is_legacy = field_type.startswith(".\\")
    if is_legacy: raise NotImplementedError("TODO: handle legacy types")

    field = FieldTypeRegistry.get(module, field_type)
    if field is None:
        raise ValueError(f"Unknown field type: {field_type}")
    # is_parent_metadata = len(element) == 0 or element.tag == "OptionValues"

    for child in element:
        if not (
            field.is_container or
            child.tag in field.properties or
            any([t.lower() == child.tag.lower() for t in field.properties])
        ):
            raise ValueError(f"{field_type} does not have a '{child.tag}' property")
        prop = _walk(module, child)
        field.properties.append(prop)

    return field.new(name=element.tag)


ModuleAndName = NamedTuple('ModuleAndName', [('module', str), ('name', str)])

class FieldType(BaseModel):
    name: str
    module: str
    parent: Optional[Self]
    properties: List[str]
    is_container: bool


class FieldTypeRegistry:
    _cache: Dict[ModuleName, Dict[str, FieldType]] = {}

    @staticmethod
    def explode_php_name(php_name) -> Tuple[ModuleName, str]:
        segments = php_name.split("\\")
        return ModuleName(segments[-3]), segments[-1]

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
            module, name = cls.explode_php_name(php_name)

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
            subprocess.Popen(("php", os.path.realpath("./ParseModels.php"), f"-o='{json_path}'"))

        with open(json_path) as file:
            field_json = file.read()
        fields_by_php_name = json.loads(field_json)

        for ft in fields_by_php_name.values():
            register_bottom_up(ft)


@measure_time
def parse_xml_file(xml_file):
    logger.debug("=========================================================")
    logger.debug(f"Parsing {xml_file}")

    relpath = re.sub('.*/models/', '', xml_file)
    module = ModuleName(relpath.split("/")[1])

    tree = ElementTree.parse(xml_file)
    items = tree.find("items")
    if items is None:
        raise ValueError("items tag not found")

    result = _walk(module, items)
    logger.info(repr(result))
    logger.debug(f"Finished parsing {xml_file}")


def get_model_xml_files(source: str):
    found = []
    for root, _, files in os.walk(source, topdown=True, followlinks=True):
        path_segments = root.split("/")
        if path_segments[-3] != "models":
            continue
        _files = [os.path.join(root, f) for f in files if f.endswith(".xml")]
        found.extend(_files)
    return found


if __name__ == "__main__":
    field_type_json_path = os.path.realpath("./field_types.json")
    FieldTypeRegistry.load(field_type_json_path)

    source = "/gitroot/upstream/opnsense" if "HOSTNAME" in os.environ else "/usr"
    xml_files = get_model_xml_files(source)

    if len(sys.argv) > 1:
        substring = sys.argv[1]
        xml_files = [m for m in xml_files if substring in m]

    logger.setLevel(logging.DEBUG)

    for xml_file in xml_files[0:3]:
        parse_xml_file(xml_file)
