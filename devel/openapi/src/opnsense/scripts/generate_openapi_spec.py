#! /usr/bin/env python3

"""
Build an OpenApi spec.

It's intended to be called by configd, but CLI args will be added.

Calls `parse_endpoints.py` and `parse_xml_models.py` if their cached JSON output is not found.
"""

import os
from typing import Any, Dict, List

from parse_endpoints import Endpoint, get_endpoints


def get_operation(endpoint: Endpoint) -> Dict[str, Any]:
    method = endpoint.method.lower()

    if endpoint.model and method == "get":
        # TODO: check whether post responses return the model
        # TODO: explore the "search" routes, which accept both post and get
        schema = {
            "$ref": endpoint.model,  # e.g. "opnsense.captiveportal.captiveportal"
            # Will make more sense later. Left in to show the spec API
        }
    else:
        # I don't fully understand non-model responses yet. Possibly they come from configd?
        # I'm willing to compromise on correctness in this case.
        # The request body is more important than the response.
        schema = {
            "type": "object",
            "properties": {
                "status": {"type": "string"}
            }
        }

    responses = {
        "200": {
            "description": endpoint.description,
            "operationId": endpoint.operation_id,
            "content": {
                "application/json": {
                    "schema": schema,
                },
            },
        },
    }
    return {method: {"responses": responses}}


# The APISpec library is looking less like it's worth the import. It saves little effort, and
# doesn't validate the spec.
# Left in for now, but will likely just build a dict directly.
from apispec import APISpec
def get_spec(endpoints: List[Endpoint]) -> APISpec:
    spec = APISpec(
        title="OPNsense API",
        version="25.1",
        openapi_version="3.1.0",
        info={"description": "API for managing your OPNsense firewall"},
    )

    # proof-of-concept code takes List[Model] and adds them to spec as components
    # omitted for brevity

    for endpoint in endpoints:
        operation = get_operation(endpoint)
        spec.path(path=endpoint.path, description=endpoint.description, operations=operation)

    return spec


if __name__ == "__main__":
    # TODO: argparse. Expecting arg[1] to be, e.g., "/usr/local/opnsense/www/openapi.yml"
    output_file = os.path.realpath("openapi.yml")

    endpoints = get_endpoints()
    spec = get_spec(endpoints)

    # omitted: use openapi_spec_validator library; apispec library doesn't validate
    validate_spec(spec)

    yaml = spec.to_yaml()  # or json
    with open(output_file, "w") as file:
        file.write(yaml)
