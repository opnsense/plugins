#! /usr/bin/env python3

import json
import logging
import os
import subprocess
import sys
from collections import defaultdict
from pprint import pprint
from timeit import default_timer
from typing import (Any, Callable, Concatenate, Dict, List, Literal, NewType,
                    Optional, ParamSpec, Self, Tuple, Type, TypedDict, TypeVar, TypeAlias)
from xml.etree import ElementTree
from xml.etree.ElementTree import Element

from pydantic import BaseModel, RootModel

from parse_xml_models import ModuleName, logger, explode_php_name


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


# ParameterList = RootModel[List[Parameter]]


class Method(BaseModel):
    name: str
    method: HttpMethod | Literal["*"]
    parameters: List[Parameter]


class Controller(BaseModel):
    name: str
    namespace: str
    parent: str | None
    methods: List[Method]
    # uses: str  # TODO
    is_abstract: bool


class Endpoint(BaseModel):
    method: HttpMethod
    module: str
    controller: str
    command: str
    parameters: List[Parameter]

    def __repr__(self):
        r = f"[{self.method}] {self.module}/{self.controller}/{self.command}"
        if self.parameters:
            return f"{r} ({', '.join(repr(p) for p in self.parameters)})"
        return r


EndpointList = RootModel[List[Endpoint]]


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
        controllers.append(Controller(**c))
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
            http_methods: List[HttpMethod] = ["GET", "POST"] if ep.method == "*" else [ep.method]
            for http_method in http_methods:
                endpoint = Endpoint(
                    method=http_method,
                    module=module,
                    controller=controller_name,
                    command=ep.name,
                    parameters=ep.parameters
                )
                endpoints.append(endpoint)

    with open(endpoint_json_path, "w") as file:
        endpoint_json = EndpointList(endpoints).model_dump_json()
        file.write(endpoint_json)

    return endpoints


if __name__ == "__main__":
    logger.setLevel(logging.DEBUG)

    pprint(get_endpoints())
