#! /usr/bin/env python

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


def measure_time(func):
    def wrapper(*args, **kwargs):
        t1 = default_timer()
        result = func(*args, **kwargs)
        t2 = default_timer()
        print(f'{func.__name__}() executed in {(t2-t1):.6f}s')
        return result
    return wrapper


def get_model_files(source: str):
    found = []
    for root, _, files in os.walk(source, topdown=True, followlinks=True):
        path_segments = root.split("/")
        if path_segments[-3] != "models":
            continue
        _files = [os.path.join(root, f) for f in files if f.endswith(".xml")]
        found.extend(_files)
    return found


def get_fieldtype_files(source: str):
    # /usr/local/opnsense/mvc/app/models/OPNsense/DynDNS/FieldTypes/ for both core and plugins
    found = []
    for root, _, files in os.walk(source, topdown=True, followlinks=True):
        if not root.endswith("/FieldTypes"):
            continue
        _files = [os.path.join(root, f) for f in files if f.endswith(".php")]
        found.extend(_files)
    return found


source = "/gitroot/upstream/opnsense" if "HOSTNAME" in os.environ else "/usr"
all_model_files = get_model_files(source)

special_snowflakes = [
    "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Dnsbl.xml",
    "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Domain.xml",
    "/gitroot/upstream/opnsense/plugins/security/tor/src/opnsense/mvc/app/models/OPNsense/Tor/General.xml",
]


class FieldBase(BaseModel):
    class Config:
        frozen = True
    type: str
    base: Optional["FieldType"]
    child_tags: List[str] = []
    is_container: bool = False
    option_values: Optional[List[str]] = None
    properties: List["FieldType"] = []
    # properties: Annotated[List["FieldType"], SkipValidation] = []

    @property
    def is_array_type(self):
        return (self.base and self.base.is_array_type) or self.type == "ArrayField"

    @property
    def is_enum(self):
        return self.option_values is not None

    def copy_with(self, **update):
        return self.model_copy(deep=True, update=update)

    def __prop_repr__(self):
        props = [
            f"{n}: {t}" if n else t
            for n, t in
            [(getattr(p, "name", None), p.type) for p in self.properties]
        ]
        return ", ".join(props)


class FieldType(FieldBase):
    def inherit(self, type, child_tags=[], **update) -> Self:
        if "base" in update:
            raise TypeError(f"{__name__}() got an unexpected keyword argument 'base'")
        child_tags=self.child_tags.copy() + child_tags
        update.update(base=self, name=update.get("name", ""), child_tags=child_tags, properties=[])
        return self.copy_with(type=type, **update)

    def new(self, *, name: str) -> "Field":
        to_skip = {"properties", "child_tags"}
        d = self.model_dump(exclude=to_skip)
        for key in to_skip:
            d[key] = getattr(self, key).copy()
        return Field(name=name, **d)

    def __repr__(self):
        return f"{self.type}({self.__prop_repr__()})"


class Field(FieldType):
    name: str

    def __repr__(self):
        return f"{self.type}(name={self.name}, {self.__prop_repr__()})"


def parse_field_types(source_files: List[str], cache: Dict, module: str, field_type: str) -> FieldType:

    modules = (module, "Base") if module != "Base" else (module,)
    for cache_key in modules:
        rel_path = f"/{cache_key}/FieldTypes/{field_type}.php"
        paths = [p for p in source_files if p.endswith(rel_path)]
        if len(paths) == 1: break
    if len(paths) != 1:
        raise Exception(f"No unambiguous file '{field_type}.php' (checked {modules})")
    path = paths[0]

    module_cache = cache.get(cache_key, None)
    if module_cache is None:
        module_cache = cache[cache_key] = {}
    cached = module_cache.get(field_type, None)
    is_hit = cached is not None
    logger.debug(f"{id(cache)}: {field_type}: cache {'hit' if is_hit else 'miss'} from {cache_key}")
    if is_hit:
        return cached

    logger.debug(f"{field_type}: reading {path}")
    with open(path) as file:
        class_pattern = re.compile(fr"^\s*(abstract)?\s*class {field_type}(\s+extends\s+(?P<base>\S+))?")
        container_pattern = re.compile(fr"^\s*protected\s+\$internalIsContainer\s+=\s+(?P<value>(true|false))\s*;")
        setter_pattern = re.compile(r"\s*public\s+function\s+set(?P<tag>\w+)\s*\(\$[^,]+\)")

        lines = (l for l in file)
        for line in lines:
            class_match = class_pattern.match(line)
            if not class_match: continue

            base_name = class_match.group("base") or None
            if base_name:
                base = parse_field_types(source_files, cache, module, base_name)
                field = base.inherit(type=field_type)
            else:
                field = FieldType(type=field_type, base=None)
            break

        if not field:
            raise ValueError(f"Class {field_type} not declared in {path}")

        for line in lines:
            container_match = container_pattern.match(line)
            if container_match:
                is_container = container_match.group("value") == "true"
                field = field.copy_with(is_container=is_container)
                continue

            setter_match = setter_pattern.match(line)
            if setter_match:
                child_tag = setter_match.group("tag")
                if child_tag in ("InternalReference", "Value", "Nodes"): continue
                field.child_tags.append(child_tag)
                continue
        logger.debug(f"Parsed {field_type} and found {field.child_tags} child tags")

    module_cache[field_type] = field
    logger.debug(f"{id(cache)}: Cached {field_type} in {cache_key} module")
    logger.debug(f"{id(cache)}: Cache for {cache_key} module holds {cache[cache_key].keys()}")
    return field


@measure_time
def _walk(
    parse_field_type: Callable[[str], FieldType],
    element: Element
) -> Field:
    field_type = element.attrib.get("type", "ContainerField")
    is_legacy = field_type.startswith(".\\")
    if is_legacy: return Field(name=element.tag, **parse_field_type("BaseField").copy_with(type="NOT IMPLEMENTED").model_dump())  # TODO

    field = parse_field_type(field_type)
    # is_parent_metadata = len(element) == 0 or element.tag == "OptionValues"

    for child in element:
        if not (field.is_container or child.tag in field.child_tags or child.tag.lower() in [t.lower() for t in field.child_tags]):
            raise ValueError(f"{field_type} does not have a '{child.tag}' property")
        prop = _walk(parse_field_type, child)
        field.properties.append(prop)

    return field.new(name=element.tag)




if __name__ == "__main__":
    source = "/gitroot/upstream/opnsense" if "HOSTNAME" in os.environ else "/usr"
    model_files = get_model_files(source)

    if len(sys.argv) > 1:
        substring = sys.argv[1]
        model_files = [m for m in model_files if substring in m]

    logger.setLevel(logging.DEBUG)

    field_files = get_fieldtype_files(source)
    partial_field_type_parser = partial(parse_field_types, field_files, {})

    for model_filename in model_files:
        relpath = re.sub('.*/models/', '', model_filename)
        module = relpath.split("/")[1]
        field_type_parser = partial(partial_field_type_parser, module)

        tree = ElementTree.parse(model_filename)
        items = tree.find("items")
        if items is None:
            raise ValueError("items tag not found")

        logger.debug(f"Parsing {model_filename}")
        result = _walk(field_type_parser, items)
        logger.info(repr(result))
