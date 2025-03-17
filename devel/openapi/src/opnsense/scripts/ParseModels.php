<?php

namespace Test;

use ReflectionClass;
use ReflectionMethod;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


$config = include __DIR__ . "/src/opnsense/mvc/app/config/config.php";
include __DIR__ . "/src/opnsense/mvc/app/config/loader.php";

$json_flags = (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$json_pretty_flags = (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


$model_path = $config->__get("application")->modelsDir;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($model_path));
$classes_to_load = array();

foreach ($rii as $file) {
    if ($file->isDir()) {continue;}
    if (!str_ends_with($file, ".php")) {continue;}

    $rel_path = preg_replace("/.*(?=OPNsense)/", "", $file);
    $className = preg_replace("/\.php$/", "", $rel_path);
    $className = preg_replace("/\//", "\\", $className);
    $classes_to_load[] = $className;
}


class Model {
    private static $ignoreProperties = [
        "InternalReference", "Value", "Nodes", "ParentModel", "Changed", "AttributeValue"
    ];
    public $name;
    public $namespace;
    public $is_container;
    public $properties;
    public function __construct(ReflectionClass $rc) {
        $this->name = preg_replace("/.*\\\/", "", $rc->getName());
        $this->namespace = $rc->getNamespaceName();
        $this->is_container = $rc->getProperty("internalIsContainer")->getDefaultValue();
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

$models = [];
foreach ($classes_to_load as $c) {
    $rc = new ReflectionClass($c);
    if (!str_starts_with($rc->getNamespaceName(), "OPNsense")) {continue;}

    $models[] = new Model($rc);
    break;
}

echo json_encode($models, $json_pretty_flags);
// echo json_encode($models, $json_flags);

?>
