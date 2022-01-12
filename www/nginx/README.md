# The nginx plugin

## Frontend Development

The nginx plugin is special in its implementation because it needs
some advanced functions (frontend and backend) to work.

Since the scripts are bigger and contain some templating,
it depends on the following libraries:

* underscore.js or lodash (tooling library)
* backbone.js (client side MVC framework)

Since this still leads to many classes and methods,
the files are built as ES6 modules,
which are built using webpack.

To install the frontend code, pleaste navigate into the
www/nginx/src/opnsense/www/js/nginx directory and
run `npm install` to install the build tools.
When all dependencies are installed, you should be able to run
`node_modules/.bin/webpack-cli --config webpack.conf.js`
to build the JavaScript files.
Please note that the files ending with `.html` are converted
to JavaScript functions (handled as lodash templates).

If you need to debug something, you can switch from `production`
to `development` in the `webpack.conf.js`.

## Backend

Most are standard but some endpoints support maps, which are not
supported by OPNsense core.

You can detect them simply as they are doing more than just a mapping
to the \*base methods.

Such mappings work in the way that they catch up the request,
map the internal data first, and then forward their UUIDs
to the \*base method.

## The nginx plugin as infrastucture

The include pattern for nginx vhosts is
`opnsense_<TYPE>_vhost_plugins/*.conf` which means that all files in the
directory `/usr/local/etc/nginx/opnsense_<TYPE>_vhost_plugins`, which end
with `.conf` are automatically included and served.
Type can be http or stream.
Please make sure your plugin creates a valid configuration because
otherwise nginx will not start.

This is intended for plugins, which do need some data to be served via
HTTP or TCP (for example traffic stats, a local FastCGI service,
converting an unix socket to TCP etc.) but do not want to serve themself.

## Hooking a HTTP server block

Just create a directory called `UUID_pre` or `UUID_post` in the nginx
configuration directory and place a file ending with `.conf` in it.
UUID is the UUID of the server object.
