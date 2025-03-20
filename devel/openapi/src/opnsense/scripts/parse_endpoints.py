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


HttpMethod: TypeAlias = Literal["GET"] | Literal["POST"]

# class PhpParamDoc(BaseModel):
#     name: str
#     type: str
#     description: str


# class PhpDoc(BaseModel):
#     description: str
#     parameters: List

class PhpParameter(TypedDict):
    name: str
    has_default: bool
    default: Any


class PhpMethod(TypedDict):
    name: str
    method: HttpMethod | Literal["*"]
    parameters: List[PhpParameter]
    doc: str | Literal[False]


class PhpController(TypedDict):
    name: str
    parent: str
    methods: List[PhpMethod]
    model: str
    is_abstract: bool
    doc: str | Literal[False]


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
    description: str
    parent: str | None
    methods: List[Method]
    model: str | None
    is_abstract: bool


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


def parse_php_doc(doc: str) -> str:
    # TODO: parse @param / @throws / @return
    # TODO: handle @inheritdoc (ugh, do I need a registry?)
    lines = [l.strip() for l in doc.split("\n")]
    lines = [re.sub(r"^\*\s*", "", l) for l in lines if l not in ("/**", "*/")]
    descr_lines = []
    for line in (l for l in lines):
        if line.startswith("@"): break
        descr_lines.append(line)
    return " ".join(descr_lines)  # TODO: handle double linebreak


def unpack_method(method: PhpMethod) -> Method:
    parameters: List[PhpParameter] = method.pop("parameters")  # type: ignore
    doc: str = method.pop("doc")  # type: ignore
    return Method(
        description=parse_php_doc(doc) if doc else "",
        parameters=[Parameter(**p) for p in parameters],
        **method,  # type: ignore
    )


def unpack_controller(ctrl: PhpController) -> Controller:
    ctrl = ctrl.copy()
    model: str | None = ctrl.pop("model")  # type: ignore
    parent: str | None = ctrl.pop("parent")  # type: ignore
    methods: List[PhpMethod] = ctrl.pop("methods")  # type: ignore
    doc: str = ctrl.pop("doc")  # type: ignore
    return Controller(
        model=model.replace("\\", ".").lower() if model else None,
        description=parse_php_doc(doc) if doc else "",
        parent=parent.replace("\\", ".").lower() if parent else None,
        methods=[unpack_method(m) for m in methods],
        **ctrl,  # type: ignore
    )


def get_controllers() -> List[Controller]:
    controller_json_path = os.path.realpath("./controllers.json")

    if not os.path.isfile(controller_json_path):
        # without shell, php errors on fopen.
        php_args = f"/usr/bin/php {os.path.realpath("./ParseControllers.php")} -o='{controller_json_path}'"
        subprocess.run(php_args, check=True, text=True, shell=True)

    with open(controller_json_path) as file:
        controller_json = file.read()

    controller_dicts_by_name: Dict[str, PhpController] = json.loads(controller_json)

    controllers = []
    for c in controller_dicts_by_name.values():
        controllers.append(unpack_controller(c))
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
                    parameters=ep.parameters,
                    model=controller.model,
                    description=ep.description,
                )
                endpoints.append(endpoint)

    with open(endpoint_json_path, "w") as file:
        endpoint_json = EndpointList(endpoints).model_dump_json()
        file.write(endpoint_json)

    return endpoints


if __name__ == "__main__":
    logger.setLevel(logging.DEBUG)

    pprint(get_endpoints())
