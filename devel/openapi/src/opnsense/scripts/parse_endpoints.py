#! /usr/bin/env python3

"""
Build a dataclass for each API endpoint. These become "operations" in OpenApi spec.

Called by `generate_openapi_spec.py`.
"""

import json
import os
import subprocess
from typing import Any, Dict, List, Literal, Self, TypeAlias, TypedDict
from pydantic import BaseModel, RootModel

from parse_xml_models import get_openapi_schema_path


HttpMethod: TypeAlias = Literal["GET"] | Literal["POST"]

#region DTOs from ParseControllers.php
# This is, approximately, raw php Reflection.
# TypedDict does not validate; this will not throw. It's only here for linting.
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
    methods: List[PhpMethod]
    model: str
    is_abstract: bool
    doc: str | Literal[False]
#endregion DTOs from ParseControllers.php


#region intermediate DTOs
# These do validation and throw nice errors. The errors are why they exist.
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

    @classmethod
    def from_php(cls, method: PhpMethod) -> Self:
        parameters: List[PhpParameter] = method.pop("parameters")  # type: ignore
        doc: str = method.pop("doc")  # type: ignore
        return cls(
            description=doc or "",  # TODO: parse PHP doc comment
            parameters=[Parameter(**p) for p in parameters],
            **method,  # type: ignore
        )

class Controller(BaseModel):
    name: str
    description: str
    methods: List[Method]
    model: str | None
    is_abstract: bool

    @classmethod
    def from_php(cls, ctrl: PhpController) -> Self:
        ctrl = ctrl.copy()
        model: str | None = ctrl.pop("model")  # type: ignore
        methods: List[PhpMethod] = ctrl.pop("methods")  # type: ignore
        doc: str = ctrl.pop("doc")  # type: ignore

        if model:
            vendor, _module, name = model.split("\\")
            model = get_openapi_schema_path(vendor, _module, name)

        return cls(
            model=model,
            description=doc or "",  # TODO
            methods=[Method.from_php(m) for m in methods],
            **ctrl,  # type: ignore
        )

#endregion intermediate DTOs


class Endpoint(BaseModel):
    """
    In OpenApi terms, this is an "operation", but "endpoint" seems more descriptive.

    Most important part of OpenApi spec. If we skip models, we can still get a spec
    with just this data (but good luck with post requests!)

    OpenApi spec defines "response" (and, optionally, "request") for models.
    Here, "model" is a placeholder. End goal is to stick models into "components".
    """

    description: str
    path: str
    method: HttpMethod
    parameters: List[Parameter]
    model: str | None

    @property
    def operation_id(self) -> str:
        """Can be any unique string (can be shared between http methods)"""
        return self.path.replace("/", "_")

    def __repr__(self):
        route = f"[{self.method}] {self.path}"
        if self.parameters:
            return f"{route} ({', '.join(repr(p) for p in self.parameters)})"
        return route


def get_controllers(json_path: str = "./controllers.json") -> List[Controller]:
    """
    Call ParseControllers.php. Using intermediate JSON because concerned about PHP polluting stdout.
    JSON should probably go somewhere in /var..?
    """

    json_path = os.path.realpath(json_path)

    if not os.path.isfile(json_path):
        php_script_path = os.path.realpath("./ParseControllers.php")
        php_incantation = f"/usr/bin/php {php_script_path} -o='{json_path}'"

        # SMELL SMELL SMELL
        # On my box, php errors on fopen, but only when run without shell=True. Identical php_info().
        # I suspect I'm missing something about BSD or about PHP...
        subprocess.run(php_incantation, shell=True)

    with open(json_path) as file:
        controller_json = file.read()

    controller_dicts_by_name: Dict[str, PhpController] = json.loads(controller_json)

    controllers = []
    for c in controller_dicts_by_name.values():
        controllers.append(Controller.from_php(c))
    return controllers


def get_controller_url_segments(class_name: str):
    """Expects, e.g. OPNsense\\Proxy\\Api\\AclController"""
    segments = class_name.split("\\")
    return segments[-3], segments[-1].replace("Controller", "")


def get_endpoints(json_path: str = "./endpoints.json") -> List[Endpoint]:
    json_path = os.path.realpath(json_path)

    if os.path.isfile(json_path):
        with open(json_path) as file:
            endpoint_json = file.read()
        _endpoints = json.loads(endpoint_json)
        endpoints = [Endpoint(**ep) for ep in _endpoints]
        return endpoints

    endpoints = []
    for controller in get_controllers():
        if controller.is_abstract:
            continue

        module, controller_name = get_controller_url_segments(controller.name)

        for method in controller.methods:
            http_methods: List[HttpMethod] = ["GET", "POST"] if method.method == "*" else [method.method]
            for http_method in http_methods:
                endpoint = Endpoint(
                    description=method.description,
                    path=f"/{module}/{controller_name}/{method.name}".lower(),
                    method=http_method,
                    parameters=method.parameters,
                    model=controller.model,
                )
                endpoints.append(endpoint)

    EndpointList = RootModel[List[Endpoint]]
    endpoint_json = EndpointList(endpoints).model_dump_json()
    with open(json_path, "w") as file:
        file.write(endpoint_json)

    return endpoints


if __name__ == "__main__":
    from pprint import pprint
    pprint(get_endpoints())
