<?php

if (!file_exists($file = __DIR__.'/../vendor/autoload.php')) {
    throw new RuntimeException('Install dependencies to run test suite.');
}

$loader = require_once $file;

$loader->add('OGM\Neo4j\Tests', __DIR__);
$loader->add('Nodes', __DIR__);
$loader->add('Stubs', __DIR__);

//\OGM\Neo4j\Mapping\Driver\AnnotationDriver::registerAnnotationClasses();
