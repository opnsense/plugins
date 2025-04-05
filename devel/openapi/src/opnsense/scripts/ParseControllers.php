<?php
/**
 * Parse controller classes using reflection, to minimise regex parsing.
 *
 * I do the bare minimum in PHP because a) I don't know PHP and b) type system
 * is not great.
 *
 * Called from `parse_endpoints.py`.
 *
 * USAGE:
 *      php ParseControllers.php [ARGS]
 *
 * ARGS:
 *      -o, --output-file      path to write a JSON file
 */

namespace OPNsense\OpenApi\Parsing;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


// TODO: remove this when I figure out how to use Make
if (str_starts_with(__DIR__, "/usr/")) {
    $app_dir = __DIR__ . "/src/opnsense/mvc/app";
} else {
    // when developing, assume the core and plugins repos are side by side
    $app_dir = preg_replace("/\/plugins\/.*/", "/core/src/opnsense/mvc/app", __DIR__);
}

$config = require $app_dir . "/config/config.php";

// TODO: remove this when I figure out how to use Make
set_include_path(get_include_path() . PATH_SEPARATOR . $app_dir . "/../../../../contrib");

require $app_dir . "/config/loader.php";
class Parameter {
    public $name;
    public $has_default;
    public $default;

    public function __construct(ReflectionParameter $rparam) {
        $this->name = $rparam->name;
        if ($rparam->isDefaultValueAvailable()) {
            $this->has_default = true;
            $this->default = $rparam->getDefaultValue();
        } else {
            $this->has_default = false;
        }
    }
}


class Method {
    protected static $BASE_METHOD_HTTP_METHODS = [
        "request->isPost" => "POST",
        "request->hasPost" => "POST",
        "request->getPost" => "POST",
        "delBase" => "POST",
        "addBase" => "POST",
        "setBase" => "POST",
        "toggleBase" => "POST",
        "searchBase" => "*",  // in the existing script, this means GET and POST are both accepted
        // "searchBase" => "POST"  // would be simpler; I can live with it (or GET)
    ];

    public $name;
    public $method;  // HTTP method!
    public $parameters = [];
    public $doc;

    public function __construct(ReflectionMethod $rmethod, string $src)
    {
        $name = preg_replace("/Action\$/", "", $rmethod->name);

        // Presence in source code of, e.g., "this->request->getPost(" implies POST.
        // See comment in Controller ctor.
        $matches = null;
        $after_deref = "(?<=\\\$this->)";   // lookbehind for "$this->"
        $before_bracket = "(?=\()";         // lookahead for "("
        $call = "\S+";                      // non-space
        $pattern = "/" . $after_deref . $call . $before_bracket . "/";
        preg_match_all($pattern, $src, $matches);

        $http_method = "GET";
        foreach ($matches[0] as $call) {
            if (array_key_exists($call, self::$BASE_METHOD_HTTP_METHODS)) {
                $http_method = self::$BASE_METHOD_HTTP_METHODS[$call];
                break;
            }
        }

        $params = [];
        $rparams = $rmethod->getParameters();
        foreach ($rparams as $rparam) {
            $params[] = new Parameter($rparam);
        }

        $doc = $rmethod->getDocComment();
        if (preg_match("/@inheritdoc/", $doc)) {
            $rclass = $rmethod->getDeclaringClass();
            $rparent = $rclass->getParentClass();
            $parent = ControllerRegistry::get($rparent->getName());
            $parent_method = array_filter($parent->methods, function ($method) use ($name) {
                return $method->name === $name;
            })[0];
            $doc = $parent_method->doc;
        }

        $this->name = $name;
        $this->method = $http_method;
        $this->parameters = $params;
        $this->doc = $doc;
    }
}


class Controller {
    public $name;
    public $parent;
    public $methods = [];
    public $model;
    public $is_abstract;
    public $doc;

    public function __construct(ReflectionClass $rclass)
    {
        $name = $rclass->getName();

        $parent = null;
        $parent_name = null;
        $rparent = $rclass->getParentClass();
        if ($rparent) {
            $parent_name = $rparent->getName();
            $parent = ControllerRegistry::get($parent_name);
        }

        $model = null;
        try {
            $prop = $rclass->getProperty("internalModelClass");
            if ($prop) {
                $model = $prop->getDefaultValue();
            }
        } catch (ReflectionException $e) {
            if ($parent) {
                $model = $parent->model;
            }
        }

        if ($model && $model[0] == "\\") {
            $model = substr($model, 1);
        }

        $doc = $rclass->getDocComment();
        if (preg_match("/@inheritdoc/", $doc)) {
            // there's only one class with no parent, and it doesn't use @inheritdoc
            $doc = $parent->doc;
        }

        $this->name = $name;
        $this->parent = $parent_name;
        $this->model = $model;
        $this->is_abstract = $rclass->isAbstract();
        $this->doc = $doc;

        /**
         * A route does not define an HTTP method - all routes accept all methods.
         *
         * `collect_api_endpoints.py` in the `docs` repo looks for method calls that imply
         * POST. This makes me nervous. There is no way to defend against, e.g.:
         *     if (request->isPost()) {throw WrongMethod("ha ha sucker")}
         *
         * In my view, when Ad talks about complexity and maintenance, this is the danger.
         *
         * I presume that unusual usage would be picked up in plugin code review, though. So I
         * assume: it's been working for years, it's good enough.
         *
         * I considered nikic/PHP-Parser, but I don't think it's worth adding a dependency.
         */
        $filename = $rclass->getFileName();
        $fd = fopen($filename, "r") or die("Failed to read '" . $filename . "'");
        $src = fread($fd, 1000000);
        fclose($fd);
        $src_lines = explode("\n", $src);

        $methods = [];
        if ($parent) {
            foreach ($parent->methods as $m) {
                $methods[$m->name] = $m;
            }
        }
        foreach ($rclass->getMethods(ReflectionMethod::IS_PUBLIC) as $rmethod) {
            // skip inherited (we already added them)
            if ($rmethod->getDeclaringClass()->name != $name) {continue;}

            $method_name = $rmethod->name;
            if (!str_ends_with($method_name, "Action")) {continue;}  // algorithm in Dispatcher.php

            // get the body of the method as raw source code - see earlier comment
            $start = $rmethod->getStartLine();
            $length = $rmethod->getEndLine() - $start;
            $method_src = implode("\n", array_slice($src_lines, $start, $length));

            $method = new Method($rmethod, $method_src);
            $methods[$method->name] = $method;
        }
        $this->methods = array_values($methods);
    }
}


/**
 * INVARIANT: parent is always registered before child
 */
class ControllerRegistry {
    private static $registry = array();

    public static function register(ReflectionClass $rclass) {
        $name = $rclass->getName();
        if (array_key_exists($name, ControllerRegistry::$registry)) {
            return;
        }

        $rparent = $rclass->getParentClass();
        if ($rparent) {
            self::register($rparent);
        }

        $controller = new Controller($rclass);
        self::$registry[$name] = $controller;
    }

    public static function get(string $name) {
        if (array_key_exists($name, ControllerRegistry::$registry)) {
            return ControllerRegistry::$registry[$name];
        }
    }

    public static function dump() {
        return array_values(ControllerRegistry::$registry);
    }
}


/**
 * Search for source code paths that look like API controllers. We're relying on consistency of
 * naming, which is 100% consistent as of v25.1. Therefore, the filename tells us the class name.
 */
function find_controller_classes(string $base_path)
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_path));
    $class_names = array();

    foreach ($rii as $file) {
        if (
            $file->isDir() ||
            !str_ends_with($file, ".php") ||
            !preg_match("/\/Api\/\w+Controller/", $file)
        ) {continue;}

        $rel_path = preg_replace("/.*(?=OPNsense)/", "", $file);
        $class_name = preg_replace("/\.php$/", "", $rel_path);
        $class_name = preg_replace("/\//", "\\", $class_name);
        $class_names[] = $class_name;
    }

    return $class_names;
}


function register_controllers(array $class_names)
{
    foreach ($class_names as $c) {
        $rclass = new ReflectionClass($c);
        ControllerRegistry::register($rclass);
    }
}


/**
 * Do The Thing, then either write JSON to file or return JSON to stdout.
 */
function export_controllers($base_path, $output_file = null, $pretty = false)
{
    $json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if ($pretty) {
        $json_flags = $json_flags | JSON_PRETTY_PRINT;
    }

    $class_names = find_controller_classes($base_path);
    register_controllers($class_names);

    $controllers = ControllerRegistry::dump();
    $json = json_encode($controllers, $json_flags) . "\n";

    if ($output_file) {
        // fails here when called from python without shell=True. See parse_endpoints.py
        $fd = fopen($output_file, "w") or die("Failed to touch '" . $output_file . "'");
        fwrite($fd, $json);
        fclose($fd);
    } else {
        return $json;
    }
}


$opts = getopt("o:", ["output-file:"]);
if (array_key_exists("o", $opts)) {
    $output_file = $opts["o"];
} elseif (array_key_exists("output-file", $opts)) {
    $output_file = $opts["output-file"];
} else {
    $output_file = null;
}

$base_path = $config->__get("application")->controllersDir;
echo export_controllers($base_path, $output_file, true);

?>
