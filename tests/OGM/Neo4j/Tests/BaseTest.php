<?php

namespace OGM\Neo4j\Tests;


use OGM\Neo4j\GraphManager;
use OGM\Neo4j\Configuration;
use OGM\Neo4j\Mapping\ClassMetadata;
use OGM\Neo4j\Mapping\Driver\AnnotationDriver;
use OGM\Neo4j\Tests\Mocks\MetadataDriverMock;
use OGM\Neo4j\Tests\Mocks\DocumentManagerMock;
use OGM\Neo4j\Tests\Mocks\ConnectionMock;

use Doctrine\Common\Annotations\AnnotationRegistry;

use Doctrine\Common\EventManager;
use Doctrine\Common\Annotations\AnnotationReader;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
	/** @var GraphManager */
    protected $gm;
	
	/** @var Unit */
    protected $uow;
	
	/** @var \OGM\Neo4j\SchemaManager */
	protected $schemaManager;

    public function setUp()
    {
        $config = new Configuration();
		
        $config->setProxyDir(__DIR__ . '/../../../Proxies');
        $config->setProxyNamespace('Proxies');

        $config->setHydratorDir(__DIR__ . '/../../../Hydrators');
        $config->setHydratorNamespace('Hydrators');

        $reader = new AnnotationReader();
        $annotationDriver = new AnnotationDriver($reader, __DIR__ . '/../../../Nodes');
		$annotationDriver->registerAnnotationClasses();
		AnnotationRegistry::registerAutoloadNamespace("OGM\Neo4j\Mapping\Annotations", __DIR__ . '/../../../../../lib/OGM/Neo4j/Mapping/Annotations/OGM/Annotations');
        $config->setMetadataDriverImpl($annotationDriver);

		//$client = \Neoxygen\UpDown\UpDownClient::factory(array('host' => '127.0.0.1', 'port' => 7474));
		
		$client = new \Everyman\Neo4j\Client;
		
		$evm = new EventManager;
		
        $this->gm = GraphManager::create($config, $client, $evm);
        $this->uow = $this->gm->getUnitOfWork();
		$this->schemaManager = $this->gm->getSchemaManager();
    }

    protected function getTestDocumentManager($metadataDriver = null)
    {
		/*
        if ($metadataDriver === null) {
            $metadataDriver = new MetadataDriverMock();
        }
        $mongoMock = new ConnectionMock();
        $config = new \OGM\Neo4j\Configuration();
        $config->setProxyDir(__DIR__ . '/../../Proxies');
        $config->setProxyNamespace('OGM\Neo4j\Tests\Proxies');
        $eventManager = new EventManager();
        $mockDriver = new MetadataDriverMock();
        $config->setMetadataDriverImpl($metadataDriver);

        return DocumentManagerMock::create($mongoMock, $config, $eventManager);
		 * 
		 */
    }

    public function tearDown()
    {
		$client = $this->gm->getClient();
		
		$path = '/cleandb/secret-key';
		$response = $client->getTransport()->delete($path);
    }
}