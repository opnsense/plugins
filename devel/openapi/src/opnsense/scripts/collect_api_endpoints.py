#!/usr/local/bin/python3
"""
Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
 this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
 notice, this list of conditions and the following disclaimer in the
 documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
"""
import os
import argparse
import re
from typing import List, TypedDict, Literal
from jinja2 import Template


EXCLUDE_CONTROLLERS = ["Core/Api/FirmwareController.php"]
DEFAULT_BASE_METHODS = {
    "ApiMutableModelControllerBase": [
        {"command": "set", "parameters": "", "method": "POST"},
        {"command": "get", "parameters": "", "method": "GET"},
    ],
    "ApiMutableServiceControllerBase": [
        {"command": "status", "parameters": "", "method": "GET"},
        {"command": "start", "parameters": "", "method": "POST"},
        {"command": "stop", "parameters": "", "method": "POST"},
        {"command": "restart", "parameters": "", "method": "POST"},
        {"command": "reconfigure", "parameters": "", "method": "POST"},
    ],
}


class Endpoint(TypedDict):
    method: Literal["GET"] | Literal["POST"] | Literal["*"]
    module: str
    controller: str
    command: str
    parameters: str
    is_abstract: bool
    base_class: str | None
    filename: str
    model_filename: str | None
    requires_model: bool
    type: str


class_def_pattern = re.compile(r"\n([\w]*).*class.*Controller.*extends\s([\w|\\]+)")
static_field_pattern = re.compile(
    r"\s+protected\s+static\s+\$internal(?:Model|Service)Class\s*=\s*['|\"]([\w|\\]*)['|\"];"
)
function_def_pattern = re.compile(
    r"(\n\s*(private|public|protected)\s+function\s+(\w+)\((.*)\))"
)


def parse_api_php(src_filename) -> List[Endpoint]:
    base_filename = os.path.basename(src_filename)
    controller = re.sub(
        "(?<!^)(?=[A-Z])",
        "_",
        os.path.basename(base_filename.split("Controller.php")[0]),
    ).lower()
    module_name = src_filename.replace("\\", "/").split("/")[-3].lower()

    data = open(src_filename).read()
    m = re.findall(class_def_pattern, data)
    base_class: str | None = m[0][1].split("\\")[-1] if len(m) > 0 else None
    is_abstract = len(m) > 0 and m[0][0] == "abstract"

    m = re.findall(static_field_pattern, data)
    model_filename = None
    if len(m) > 0:
        app_location = "/".join(src_filename.split("/")[:-5])
        model_xml = "%s/models/%s.xml" % (app_location, m[0].replace("\\", "/"))
        if os.path.isfile(model_xml):
            model_filename = model_xml.replace("//", "/")

    function_callouts = re.findall(function_def_pattern, data)
    result = list()
    this_commands = []
    for idx, function in enumerate(function_callouts):
        begin_marker = data.find(function_callouts[idx][0])
        if idx + 1 < len(function_callouts):
            end_marker = data.find(function_callouts[idx + 1][0])
        else:
            end_marker = -1
        code_block = data[begin_marker + len(function[0]) : end_marker]
        if function[2].endswith("Action"):
            this_commands.append(function[2][:-6])

            if is_abstract:
                _type = "Abstract [non-callable]"
            elif controller.find("service") > -1:
                _type = "Service"
            else:
                _type = "Resources"

            # find most likely method (default => GET)
            if code_block.find("request->isPost(") > -1:
                method = "POST"
            elif code_block.find("$this->delBase") > -1:
                method = "POST"
            elif code_block.find("$this->addBase") > -1:
                method = "POST"
            elif code_block.find("$this->setBase") > -1:
                method = "POST"
            elif code_block.find("$this->toggleBase") > -1:
                method = "POST"
            elif code_block.find("$this->searchBase") > -1:
                method = "*"
            else:
                method = "GET"

            requires_model = code_block.find("request->getPost(") > -1

            record: Endpoint = {
                "method": method,
                "module": module_name,
                "controller": controller,
                "is_abstract": is_abstract,
                "base_class": base_class,
                "command": function[2][:-6],
                "parameters": function[3].replace(" ", "").replace('"', '""'),
                "filename": base_filename,
                "model_filename": model_filename,
                "requires_model": requires_model,
                "type": _type,
            }
            result.append(record)

    if base_class in DEFAULT_BASE_METHODS:
        for item in DEFAULT_BASE_METHODS[base_class]:
            if item not in this_commands:
                result.append(
                    {
                        "type": "Service",
                        "method": item["method"],
                        "module": module_name,
                        "controller": controller,
                        "is_abstract": False,
                        "base_class": base_class,
                        "command": item["command"],
                        "parameters": item["parameters"],
                        "filename": base_filename,
                        "model_filename": model_filename,
                    }
                )

    return sorted(result, key=lambda i: i["command"])


def source_url(repo, src_filename):
    parts = src_filename.split("/")
    if repo == "plugins":
        return "https://github.com/opnsense/plugins/blob/master/%s" % "/".join(
            parts[parts.index("src") - 2 :]
        )
    else:
        return "https://github.com/opnsense/core/blob/master/%s" % "/".join(
            parts[parts.index("src") :]
        )


def collect_api_modules(source: str):
    # collect all endpoints
    all_modules = dict()
    for root, dirs, files in os.walk(source):
        for fname in sorted(files):
            filename = os.path.join(root, fname)

            skip = False
            for to_exclude in EXCLUDE_CONTROLLERS:
                if filename.endswith(to_exclude):
                    skip = True
                    break
            if skip:
                continue

            if (
                filename.lower().endswith("controller.php")
                and filename.find("mvc/app/controllers") > -1
                and root.endswith("Api")
            ):
                endpoints = parse_api_php(filename)
                if len(endpoints) > 0:
                    module = endpoints[0]["module"]
                    if module not in all_modules:
                        all_modules[module] = list()
                    all_modules[module].append(endpoints)  # TODO: extend, not append

    return all_modules


def render(modules: dict, repo: str):
    # writeout .rst files
    for module_name in modules:
        target_filename = "%s/source/development/api/%s/%s.rst" % (
            os.path.dirname(__file__),
            repo,
            module_name,
        )
        print("update %s" % target_filename)
        template_data = {
            "title": "%s" % module_name.title(),
            "title_underline": "".join("~" for x in range(len(module_name))),
            "controllers": [],
        }
        for controller in modules[module_name]:
            payload = {
                "type": controller[0]["type"],
                "filename": controller[0]["filename"],
                "is_abstract": controller[0]["is_abstract"],
                "base_class": controller[0]["base_class"],
                "endpoints": [],
                "uses": [],
            }
            for endpoint in controller:
                payload["endpoints"].append(endpoint)
            if controller[0]["model_filename"]:
                payload["uses"].append(
                    {
                        "type": "model",
                        "link": source_url(repo, controller[0]["model_filename"]),
                        "name": os.path.basename(controller[0]["model_filename"]),
                    }
                )
            template_data["controllers"].append(payload)

        with open(target_filename, "w") as f_out:
            if os.path.isfile("%s.in" % target_filename):
                template_filename = "%s.in" % target_filename
            else:
                template_filename = "collect_api_endpoints.in"
            template = Template(open(template_filename, "r").read())
            f_out.write(template.render(template_data))


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("source", help="source directory")
    parser.add_argument("--repo", help="target repository", default="core")
    cmd_args = parser.parse_args()

    modules = collect_api_modules(cmd_args.source)
    render(modules, cmd_args.repo)
