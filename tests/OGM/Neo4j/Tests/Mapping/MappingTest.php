<?php

namespace OGM\Neo4j\Tests\Mapping;

use Doctrine\Common\Annotations\AnnotationRegistry;
use OGM\Neo4j\Mapping\ClassMetadata;

class MappingTest extends \OGM\Neo4j\Tests\BaseTest
{
	/**
	 * @return \OGM\Neo4j\Mapping\Driver\AnnotationDriver
	 */
	protected function loadDriver()
    {
		AnnotationRegistry::registerAutoloadNamespace("OGM\Neo4j\Mapping\Annotations", __DIR__ . '/../../../../../lib/OGM/Neo4j/Mapping/Annotations/OGM/Annotations');
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
		$annotationDriver = new \OGM\Neo4j\Mapping\Driver\AnnotationDriver($reader);
		$annotationDriver->registerAnnotationClasses();
		$annotationDriver->addPaths(array(__DIR__ . '/../../../../Nodes'));
        return $annotationDriver;
    }
	
	public function testGetClassMetaData()
	{
		$driver = $this->loadDriver();
		$driver->getAllClassNames();
	}
}
