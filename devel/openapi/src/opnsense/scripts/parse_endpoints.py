#! /usr/bin/env python3

import json
import logging
import os
import subprocess
import sys
import re
from collections import defaultdict
from pprint import pprint
from timeit import default_timer
from typing import (Any, Callable, Concatenate, Dict, List, Literal, NewType,
                    Optional, ParamSpec, Self, Tuple, Type, TypedDict, TypeVar, TypeAlias)
from xml.etree import ElementTree
from xml.etree.ElementTree import Element

from pydantic import BaseModel, RootModel

from parse_xml_models import ModuleName, logger, explode_php_name


# class PhpParamDoc(BaseModel):
#     name: str
#     type: str
#     description: str


# class PhpDoc(BaseModel):
#     description: str
#     parameters: List


HttpMethod: TypeAlias = Literal["GET"] | Literal["POST"]

class Parameter(BaseModel):
    name: str
    has_default: bool
    default: Any

    def __repr__(self):
        if self.has_default:
            default = "null" if self.default is None else str(self.default)
            return f"{self.name}={default}"
        return self.name


class Method(BaseModel):
    description: str
    name: str
    method: HttpMethod | Literal["*"]
    parameters: List[Parameter]


class Controller(BaseModel):
    name: str
    namespace: str
    parent: str | None
    methods: List[Method]
    model: str | None
    is_abstract: bool
    # doc: str


class Endpoint(BaseModel):
    description: str
    method: HttpMethod
    module: str
    controller: str
    command: str
    parameters: List[Parameter]
    model: str | None

    @property
    def path(self):
        return f"/{self.module}/{self.controller}/{self.command}".lower()

    def __repr__(self):
        route = f"[{self.method}] {self.path}"
        if self.parameters:
            return f"{route} ({', '.join(repr(p) for p in self.parameters)})"
        return route


EndpointList = RootModel[List[Endpoint]]


# def parse_php_doc(doc: str) -> PhpDoc:
#     lines = [l.strip() for l in doc.split("\n")]
#     lines = [re.sub(r"^\*\s*", "", l) for l in lines if l not in ("/**", "*/")]
#     params = []
#     for param_doc in [l.replace("@param ", "") for l in lines if l.startswith("@param")]:
#         _type, name, p_descr = param_doc.split(" ", maxsplit=3)
#         params.append(PhpParamDoc(type=_type, name=name.replace("@", ""), description=p_descr))
#     descr = " ".join([l for l in lines if not l.startswith("@")])
#     return PhpDoc(description=descr, parameters=params)


def parse_php_doc(doc: Dict) -> PhpDoc:

    return PhpDoc(description=descr, parameters=params)


def get_controllers() -> List[Controller]:
    controller_json_path = os.path.realpath("./controllers.json")

    if not os.path.isfile(controller_json_path):
        # without shell, php errors on fopen.
        php_args = f"/usr/bin/php {os.path.realpath("./ParseControllers.php")} -o='{controller_json_path}'"
        subprocess.run(php_args, check=True, text=True, shell=True)

    with open(controller_json_path) as file:
        controller_json = file.read()

    controllers = []
    for c in json.loads(controller_json).values():
        model_path = (c.pop("model") or "").replace("\\", ".").lower()
        controllers.append(Controller(model=model_path, **c))
    return controllers


def get_endpoints() -> List[Endpoint]:
    endpoint_json_path = os.path.realpath("./endpoints.json")

    if os.path.isfile(endpoint_json_path):
        with open(endpoint_json_path) as file:
            endpoint_json = file.read()
        _endpoints = json.loads(endpoint_json)
        endpoints = [Endpoint(**ep) for ep in _endpoints]
        return endpoints

    endpoints = []
    for controller in get_controllers():
        pprint(controller)
        if controller.is_abstract:
            continue

        module, controller_name = explode_php_name(controller.name)

        for ep in controller.methods:
            doc = parse_php_doc(ep.doc)
            http_methods: List[HttpMethod] = ["GET", "POST"] if ep.method == "*" else [ep.method]
            for http_method in http_methods:
                endpoint = Endpoint(
                    method=http_method,
                    module=module,
                    controller=controller_name,
                    command=ep.name,
                    parameters=ep.parameters,
                    model=controller.model,
                    description=doc.description,
                )
                endpoints.append(endpoint)

    with open(endpoint_json_path, "w") as file:
        endpoint_json = EndpointList(endpoints).model_dump_json()
        file.write(endpoint_json)

    return endpoints


if __name__ == "__main__":
    logger.setLevel(logging.DEBUG)

    pprint(get_endpoints())
