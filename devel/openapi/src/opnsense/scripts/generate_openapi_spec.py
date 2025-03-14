#! /usr/bin/env python3

# https://github.com/globality-corp/openapi
from openapi.model import Swagger, Info, Operation, PathItem, Paths, Response, Responses

swagger = Swagger(
    swagger="2.0",
    info=Info(
        title="Example",
        version="1.0.0",
    ),
    basePath="/api",
    paths=Paths({
        "/hello": PathItem(
            get=Operation(
                responses=Responses({
                    "200": Response(
                        description="Returns hello",
                    )
                })
            ),
        ),
    }),
)

with open("/usr/local/opnsense/www/openapi.json", mode="w") as file:
    file.write(swagger.dumps())
