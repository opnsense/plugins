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

// omitted: import/use/namespace ceremony

class Parameter {
    public $name;
    public $has_default;
    public $default;

    public function __construct(ReflectionParameter $rparam) { /** omitted */ }
}


class Method {
    public $name;
    public $method;  // HTTP method!
    public $parameters = [];
    public $doc;

    public function __construct(ReflectionMethod $rmethod)
    {
        $http_method = "GET";
        // omitted: detecting POST methods using algorithm from existing script

        $params = [];
        $rparams = $rmethod->getParameters();
        foreach ($rparams as $rparam) {
            $params[] = new Parameter($rparam);
        }

        $this->name = preg_replace("/Action\$/", "", $rmethod->name);
        $this->method = $http_method;
        $this->parameters = $params;
        $this->doc = $rmethod->getDocComment();
        // omitted: handle @inheritdoc using ControllerRegistry
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
        $parent = null;
        $parent_name = null;
        $rparent = $rclass->getParentClass();
        if ($rparent) {
            $parent_name = $rparent->getName();
            $parent = ControllerRegistry::get($parent_name);
        }

        $this->name = $rclass->getName();
        $this->parent = $parent_name;
        $this->model = $model;  // omitted: get internalModelClass (may be inherited)
        $this->is_abstract = $rclass->isAbstract();
        $this->doc = $rclass->getDocComment();

        $methods = [];
        if ($parent) {$methods = $parent->methods or [];}
        foreach ($rclass->getMethods(ReflectionMethod::IS_PUBLIC) as $rmethod) {
            $method_name = $rmethod->name;
            if (!str_ends_with($method_name, "Action")) {continue;}  // algorithm in Dispatcher.php

            $methods[] = new Method($rmethod);
        }
        $this->methods = $methods;
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
        return ControllerRegistry::$registry;
    }
}


/**
 * Search for source code paths that look like API controllers. We're relying on consistency of
 * naming, which is 100% consistent as of v25.1. Therefore, the filename tells us the class name.
 */
function find_controller_classes(string $base_path) { /** omitted */ }


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
function export_controllers(string $base_path, ?string $output_file = null)
{
    $json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

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
