<?php

/**
 * Parse Field classes using reflection. Because regex is not ideal for parsing source code.
 * We do the absolute bare minimum in PHP because it suffers from being PHP.
 *
 * Called from `parse_xml_models.py`.
 *
 * USAGE:
 *      php ParseFieldTypes.php [ARGS]
 *
 * ARGS:
 *      -o, --output-file      path to write a JSON file
 */

namespace Test;

use ReflectionClass;
use ReflectionMethod;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


// TODO: remove this when I figure out how to use Make
if (str_starts_with(__DIR__, "/usr/")) {
    $app_dir = __DIR__ . "/src/opnsense/mvc/app";
} else {
    // when developing, assume the core and plugins repos are side by side
    $app_dir = preg_replace("/\/plugins\/.*/", "/core/src/opnsense/mvc/app", __DIR__);
}

$config = include $app_dir . "/config/config.php";
include $app_dir . "/config/loader.php";


class Field {
    private static $ignoreProperties = [
        "InternalReference", "Value", "Nodes", "ParentModel", "Changed", "AttributeValue"
    ];

    public $name;
    public $namespace;
    public $parent;
    public $is_container;
    public $properties;

    public function __construct(ReflectionClass $rc)
    {
        $this->name = $rc->getName();
        $this->namespace = $rc->getNamespaceName();

        $parent = null;
        $parent_name = null;
        $parent_rc = $rc->getParentClass();
        if ($parent_rc) {
            $parent_name = $parent_rc->getName();
            $parent = FieldRegistry::get($parent_name);
        }
        $this->parent = $parent_name;

        $is_container = false;
        try {
            $is_container = $rc->getProperty("internalIsContainer")->getDefaultValue();
        } catch (Exception $e) {
            if ($parent) {
                $is_container = $parent->is_container;
            }
        }
        $this->is_container = $is_container;

        $props = [];
        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $method_name = $method->name;
            if (!str_starts_with($method_name, "set")) {continue;}

            $prop = preg_replace("/^set/", "", $method_name);
            if (str_starts_with($prop, "Internal")) {continue;}
            if (in_array($prop, $this::$ignoreProperties)) {continue;}
            $props[] = $prop;
        }
        $this->properties = $props;
    }
}


class FieldRegistry {
    private static $registry = array();

    public static function register(ReflectionClass $rc) {
        $name = $rc->getName();
        if (array_key_exists($name, FieldRegistry::$registry)) {
            return;
        }

        $parent_rc = $rc->getParentClass();
        $parent = null;
        if ($parent_rc) {
            self::register($parent_rc);
            $parent = self::get($parent_rc->getName());
        }

        $field = new Field($rc, $parent);
        self::$registry[$name] = $field;
    }

    public static function get(string $name) {
        if (array_key_exists($name, FieldRegistry::$registry)) {
            return FieldRegistry::$registry[$name];
        }
    }

    public static function dump() {
        return FieldRegistry::$registry;
    }
}


function find_field_classes(string $base_path)
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_path));
    $class_names = array();

    foreach ($rii as $file) {
        if (
            $file->isDir() ||
            !str_ends_with($file, ".php") ||
            !preg_match("/\/FieldTypes\/\w+/", $file)
        ) {continue;}

        $rel_path = preg_replace("/.*(?=OPNsense)/", "", $file);
        $class_name = preg_replace("/\.php$/", "", $rel_path);
        $class_name = preg_replace("/\//", "\\", $class_name);
        $class_names[] = $class_name;
    }

    return $class_names;
}


function register_fields(array $class_names)
{
    foreach ($class_names as $c) {
        $rc = new ReflectionClass($c);
        FieldRegistry::register($rc);
    }
}


function export_fields($base_path, $output_file = null, $pretty = false)
{
    $json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if ($pretty) {
        $json_flags = $json_flags | JSON_PRETTY_PRINT;
    }

    $class_names = find_field_classes($base_path);
    register_fields($class_names);

    $fields = FieldRegistry::dump();
    $json = json_encode($fields, $json_flags) . "\n";

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

$base_path = $config->__get("application")->modelsDir;
echo export_fields($base_path, $output_file, true);

?>
