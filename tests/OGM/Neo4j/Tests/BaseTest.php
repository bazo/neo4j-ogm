<?php

namespace OGM\Neo4j\Tests;


use OGM\Neo4j\NodeManager;
use OGM\Neo4j\Configuration;
use OGM\Neo4j\Mapping\ClassMetadata;
use OGM\Neo4j\Mapping\Driver\AnnotationDriver;
use OGM\Neo4j\Tests\Mocks\MetadataDriverMock;
use OGM\Neo4j\Tests\Mocks\DocumentManagerMock;
use OGM\Neo4j\Tests\Mocks\ConnectionMock;

use Doctrine\Common\EventManager;
use Doctrine\Common\Annotations\AnnotationReader;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
	/** @var NodeManager */
    protected $nm;
	
	/** @var Unit */
    protected $uow;

    public function setUp()
    {
        $config = new Configuration();

		
        $config->setProxyDir(__DIR__ . '/../../../Proxies');
        $config->setProxyNamespace('Proxies');

        $config->setHydratorDir(__DIR__ . '/../../../Hydrators');
        $config->setHydratorNamespace('Hydrators');

        $reader = new AnnotationReader();
        $this->annotationDriver = new AnnotationDriver($reader, __DIR__ . '/../../../Nodes');
        $config->setMetadataDriverImpl($this->annotationDriver);

		$client = \Neoxygen\UpDown\UpDownClient::factory(array('host' => '127.0.0.1', 'port' => 7474));
		$evm = new EventManager;
		
        $this->nm = NodeManager::create($config, $client, $evm);
        $this->uow = $this->nm->getUnitOfWork();
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
		/*
        if ($this->dm) {
            $collections = $this->dm->getConnection()->selectDatabase('doctrine_odm_tests')->listCollections();
            foreach ($collections as $collection) {
                $collection->drop();
            }
        }
		 * 
		 */
    }
}