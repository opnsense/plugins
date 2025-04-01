#! /usr/bin/env python3

"""
Build a dataclass for each API endpoint. These become "operations" in OpenApi spec.

Called by `generate_openapi_spec.py`.
"""

import json
import os
import subprocess
from typing import Any, List, Literal, Self, TypeAlias, TypedDict
from pydantic import BaseModel

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
    # No from_php method needed, already matches the PHP DTO.

class Method(BaseModel):
    description: str
    name: str
    method: HttpMethod | Literal["*"]
    parameters: List[Parameter]

    @classmethod
    def from_php(cls, method: PhpMethod) -> Self:
        # omitted: pick description out of PHP doc comment.
        return cls(**method)

class Controller(BaseModel):
    name: str
    description: str
    methods: List[Method]
    model: str | None
    is_abstract: bool

    @classmethod
    def from_php(cls, ctrl: PhpController) -> Self:
        # omitted: replace PHP backslashes
        return cls(**ctrl)

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

    controllers = []
    with open(json_path) as file:
        ... # iterate and call Controller.from_php()

    return controllers


def get_endpoints() -> List[Endpoint]:
    # I'm also caching as JSON here, but will measure performance to see if it's needed

    endpoints = []
    for controller in get_controllers():
        if controller.is_abstract:
            continue

        model_schema_path = None
        if controller.model:
            vendor, module, name = controller.model.split("\\")
            model_schema_path = get_openapi_schema_path(vendor, module, name)

        # omitted: regex split helper. E.g. ("Auth", "User") or ("Firewall", "SourceNat")
        module, controller_name = explode_php_name(controller.name)

        for method in controller.methods:
            http_methods: List[HttpMethod] = ["GET", "POST"] if method.method == "*" else [method.method]
            for http_method in http_methods:
                endpoint = Endpoint(
                    description=method.description,
                    path=f"/{module}/{controller_name}/{method.name}".lower(),
                    method=http_method,
                    parameters=method.parameters,
                    model=model_schema_path,
                )
                endpoints.append(endpoint)

    return endpoints


if __name__ == "__main__":
    from pprint import pprint
    pprint(get_endpoints())
