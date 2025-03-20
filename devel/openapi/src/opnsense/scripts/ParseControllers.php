<?php

namespace Test;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


if (str_starts_with(__DIR__, "/usr/")) {
    $app_dir = __DIR__ . "/src/opnsense/mvc/app";
} else {
    // when developing, assume the core and plugins repos are side by side
    $app_dir = preg_replace("/\/plugins\/.*/", "/core/src/opnsense/mvc/app", __DIR__);
}

$config = require $app_dir . "/config/config.php";
set_include_path(get_include_path() . PATH_SEPARATOR . $app_dir . "/../../../../contrib");
require $app_dir . "/config/loader.php";


abstract class DocComment {
    public $description = "";
}


class ClassDocComment extends DocComment {
    public $package = "";

    // public function __construct(ReflectionClass $class) {

    // }
}


class MethodDocIsInherited extends Exception {}


class MethodDocComment extends DocComment {
    public $params = [];
    public $throws = [];
    public $return = "";

    // public function __construct(ReflectionMethod $method) {
    //     $doc = $method->getDocComment();
    public function __construct(string $doc) {
        if ($doc === false) {
            return;
        }

        $description = [];
        foreach (explode("\n", $doc) as $line) {
            $line = trim($line);
            if ($line == "/**" || $line == "*/") {continue;}
            $line = substr($line, 2);

            if ($line == "@inheritdoc") {
                // $controller = $method->getDeclaringClass();
                // $parent_rc = $controller->getParentClass();
                // $parent = ControllerRegistry::get($parent_rc->getName());
                // $parent_method =

                // $this->description = $line;
                // return;

                throw new MethodDocIsInherited();
            }

            if (str_starts_with($line, "@")) {
                $line = substr($line, 1);
                [$prop, $descr] = explode(" ", $line, 2);
                $plural = $prop . "s";
                if (property_exists($this, $plural)) {
                    $this->$plural[] = $descr;
                } else {
                    $this->$prop = $descr;
                }

            } else {
                $description[] = $line;
            }
        }
        $this->description = implode(" ", $description);
    }
}


class Parameter {
    public $name;
    public $has_default;
    public $default;

    public function __construct(ReflectionParameter $param) {
        $this->name = $param->name;
        if ($param->isDefaultValueAvailable()) {
            $this->has_default = true;
            $this->default = $param->getDefaultValue();
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
        "searchBase" => "*",
    ];

    public $name;
    public $method;  // HTTP method!
    public $parameters = [];
    public $doc;

    public function __construct(ReflectionMethod $method, string $src)
    {
        $name = preg_replace("/Action\$/", "", $method->name);
        $this->name = $name;

        $http_method = "GET";
        preg_match_all('/(?<=this->).*(?=\()/', $src, $matches);
        foreach ($matches[0] as $call) {
            if (array_key_exists($call, self::$BASE_METHOD_HTTP_METHODS)) {
                $http_method = self::$BASE_METHOD_HTTP_METHODS[$call];
                break;
            }
        }
        $this->method = $http_method;

        $params = [];
        $param_rcs = $method->getParameters();
        foreach ($param_rcs as $param_rc) {
            $params[] = new Parameter($param_rc);
        }
        $this->parameters = $params;

        // $doc = $method->getDocComment();
        // if (!$doc) {$doc = "";}
        // $this->doc = $doc;
        // $this->doc = new MethodDocComment($method->getDocComment());

        // try {
        //     $this->doc = new MethodDocComment($method->getDocComment());
        // } catch (MethodDocIsInherited $e) {
        //     $controller_rc = $method->getDeclaringClass();
        //     $parent_rc = $controller_rc->getParentClass();
        //     $parent = ControllerRegistry::get($parent_rc->getName());
        //     foreach ($parent->methods as $pm) {
        //         if ($pm->name == $name) {
        //             $this->doc = $pm->doc;
        //             break;
        //         }
        //     }
        // }
        $this->doc = $method->getDocComment();
    }
}


class Controller {
    public $name;
    public $parent;
    public $methods;
    public $model;
    public $is_abstract;
    public $doc;

    public function __construct(ReflectionClass $rc)
    {
        $name = $rc->getName();
        $this->name = $name;

        $parent = null;
        $parent_name = null;
        $parent_rc = $rc->getParentClass();
        if ($parent_rc) {
            $parent_name = $parent_rc->getName();
            $parent = ControllerRegistry::get($parent_name);
        }
        $this->parent = $parent_name;

        $this->is_abstract = $rc->isAbstract();

        $filename = $rc->getFileName();
        $fd = fopen($filename, "r") or die("Failed to read '" . $filename . "'");
        $src = fread($fd, 1000000);
        fclose($fd);
        $src_lines = explode("\n", $src);

        $methods = [];
        if ($parent) {$methods = $parent->methods or [];}
        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method_rc) {
            // skip inherited
            if ($method_rc->getDeclaringClass()->getName() != $name) {continue;}

            $method_name = $method_rc->name;
            if (!str_ends_with($method_name, "Action")) {continue;}

            $start = $method_rc->getStartLine();
            $length = $method_rc->getEndLine() - $start;
            $method_src = implode("\n", array_slice($src_lines, $start, $length));

            $methods[] = new Method($method_rc, $method_src);
        }
        $this->methods = $methods;

        $model = null;
        try {
            $prop = $rc->getProperty("internalModelClass");
            if ($prop) {
                $model = $prop->getDefaultValue();
            }
        } catch (ReflectionException $e) {
            if ($parent) {
                $model = $parent->model;
            }
        }
        $this->model = $model;

        $this->doc = $rc->getDocComment();
        // $doc = $rc->getDocComment();
        // if (!$doc) {$doc = "";}
        // $this->doc = $doc;

        // try {
        //     $this->doc = new MethodDocComment($method);
        // } catch (MethodDocIsInherited $e) {
        //     $controller_rc = $method->getDeclaringClass();
        //     $parent_rc = $controller_rc->getParentClass();
        //     $parent = ControllerRegistry::get($parent_rc->getName());
        //     foreach ($parent->methods as $pm) {
        //         if ($pm->name == $name) {
        //             $this->doc = $pm->doc;
        //             break;
        //         }
        //     }
        // }
    }
}


class ControllerRegistry {
    private static $registry = array();

    public static function register(ReflectionClass $rc) {
        $name = $rc->getName();
        if (array_key_exists($name, ControllerRegistry::$registry)) {
            return;
        }

        $parent_rc = $rc->getParentClass();
        $parent = null;
        if ($parent_rc) {
            self::register($parent_rc);
            $parent = self::get($parent_rc->getName());
        }

        $controller = new Controller($rc, $parent);
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
        $rc = new ReflectionClass($c);
        ControllerRegistry::register($rc);
    }
}


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
        // fails when called from python without shell
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
