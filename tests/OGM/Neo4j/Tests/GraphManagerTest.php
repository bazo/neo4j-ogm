<?php

namespace OGM\Neo4j\Tests;

use OGM\Neo4j\NodeManager;
use OGM\Neo4j\Configuration;
use Doctrine\Common\Annotations\AnnotationReader;

use Everyman\Neo4j\Client;

class NodeManagerTest extends \OGM\Neo4j\Tests\BaseTest
{
	
//    public function testCustomRepository()
//    {
//        $gm = $this->getDocumentManager();
//        $this->assertInstanceOf('Documents\CustomRepository\Repository', $gm->getRepository('Documents\CustomRepository\Document'));
//    }

    public function testGetMetadataFactory()
    {
		$metaDataFactory = $this->gm->getMetadataFactory();
        $this->assertInstanceOf('\OGM\Neo4j\Mapping\ClassMetadataFactory', $metaDataFactory);
    }

    public function testGetConfiguration()
    {
        $this->assertInstanceOf('\OGM\Neo4j\Configuration', $this->gm->getConfiguration());
    }

    public function testGetUnitOfWork()
    {
        $this->assertInstanceOf('\OGM\Neo4j\UnitOfWork', $this->gm->getUnitOfWork());
    }

    public function testGetProxyFactory()
    {
        $this->assertInstanceOf('\OGM\Neo4j\Proxy\ProxyFactory', $this->gm->getProxyFactory());
    }

    public function testGetEventManager()
    {
        $this->assertInstanceOf('\Doctrine\Common\EventManager', $this->gm->getEventManager());
    }

    public function testGetSchemaManager()
    {
        $this->assertInstanceOf('\OGM\Neo4j\SchemaManager', $this->gm->getSchemaManager());
    }
	
	public function testGetClient()
    {
        //$this->assertInstanceOf('\Neoxygen\UpDown\UpDownClient', $this->gm->getClient());
		//$this->assertInstanceOf('Client', $this->gm->getClient());
		
		$this->assertTrue($this->gm->getClient() instanceof Client);
    }

//    public function testCreateQueryBuilder()
//    {
//        $this->assertInstanceOf('\OGM\Neo4j\Query\Builder', $this->gm->createQueryBuilder());
//    }

//    public function testGetFilterCollection()
//    {
//        $this->assertInstanceOf('\OGM\Neo4j\Query\FilterCollection', $this->gm->getFilterCollection());
//    }  
    
	/*
    public function testGetPartialReference()
    {
        $user = $this->gm->getPartialReference('Documents\CmsUser', 42);
        $this->assertTrue($this->gm->contains($user));
        $this->assertEquals(42, $user->id);
        $this->assertNull($user->getName());
    }
	*/
	/*
    static public function dataMethodsAffectedByNoObjectArguments()
    {
        return array(
            array('persist'),
            array('remove'),
            array('merge'),
            array('refresh'),
            array('detach')
        );
    }
	*/
	
//    /**
//     * @dataProvider dataMethodsAffectedByNoObjectArguments
//     * @expectedException \InvalidArgumentException
//     * @param string $methodName
//     */
//    public function testThrowsExceptionOnNonObjectValues($methodName) {
//        $this->gm->$methodName(null);
//    }
//
//    static public function dataAffectedByErrorIfClosedException()
//    {
//        return array(
//            array('flush'),
//            array('persist'),
//            array('remove'),
//            array('merge'),
//            array('refresh'),
//        );
//    }
	
//    /**
//     * @dataProvider dataAffectedByErrorIfClosedException
//     * @param string $methodName
//     */
//    public function testAffectedByErrorIfClosedException($methodName)
//    {
//        $this->setExpectedException('OGM\Neo4j\MongoDBException', 'closed');
//
//        $this->gm->close();
//        if ($methodName === 'flush') {
//            $this->gm->$methodName();
//        } else {
//            $this->gm->$methodName(new \stdClass());
//        }
//    }

    protected function getDocumentManager()
    {
        $config = new Configuration();

        $config->setProxyDir(__DIR__ . '/../../../../Proxies');
        $config->setProxyNamespace('Proxies');

        $config->setHydratorDir(__DIR__ . '/../../../../Hydrators');
        $config->setHydratorNamespace('Hydrators');

        $config->setDefaultDB('ogm_tests');

        /*
        $config->setLoggerCallable(function(array $log) {
            print_r($log);
        });
        $config->setMetadataCacheImpl(new ApcCache());
        */

        $reader = new AnnotationReader();
        $config->setMetadataDriverImpl(new AnnotationDriver($reader, __DIR__ . '/Nodes'));
        return DocumentManager::create($this->getConnection(), $config);
    }
}
