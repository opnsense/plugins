#! /usr/bin/env python3

import json
import os
import re
import subprocess
from pprint import pprint
from typing import Any, Dict, List, Literal, Self, TypeAlias, TypedDict

from pydantic import BaseModel, RootModel

from parse_xml_models import explode_php_name

HttpMethod: TypeAlias = Literal["GET"] | Literal["POST"]


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

    @classmethod
    def from_php(cls, method: PhpMethod) -> Self:
        parameters: List[PhpParameter] = method.pop("parameters")  # type: ignore
        doc: str = method.pop("doc")  # type: ignore
        return Method(
            description=parse_php_doc(doc) if doc else "",
            parameters=[Parameter(**p) for p in parameters],
            **method,  # type: ignore
        )


class Controller(BaseModel):
    name: str
    description: str
    parent: str | None
    methods: List[Method]
    model: str | None
    is_abstract: bool

    @classmethod
    def from_php(cls, ctrl: PhpController) -> Self:
        ctrl = ctrl.copy()
        model: str | None = ctrl.pop("model")  # type: ignore
        parent: str | None = ctrl.pop("parent")  # type: ignore
        methods: List[PhpMethod] = ctrl.pop("methods")  # type: ignore
        doc: str = ctrl.pop("doc")  # type: ignore

        return cls(
            model=model.replace("\\", ".").lower() if model else None,
            description=parse_php_doc(doc) if doc else "",
            parent=parent.replace("\\", ".").lower() if parent else None,
            methods=[Method.from_php(m) for m in methods],
            **ctrl,  # type: ignore
        )


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
        controllers.append(Controller.from_php(c))
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
    pprint(get_endpoints())
