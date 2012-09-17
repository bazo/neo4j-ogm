<?php

namespace OGM\Neo4j\Tests;

use OGM\Neo4j\NodeManager;
use OGM\Neo4j\Configuration;
use Doctrine\Common\Annotations\AnnotationReader;

class NodeManagerTest extends \OGM\Neo4j\Tests\BaseTest
{
	
//    public function testCustomRepository()
//    {
//        $nm = $this->getDocumentManager();
//        $this->assertInstanceOf('Documents\CustomRepository\Repository', $nm->getRepository('Documents\CustomRepository\Document'));
//    }

    public function testGetMetadataFactory()
    {
		$metaDataFactory = $this->nm->getMetadataFactory();
        $this->assertInstanceOf('\OGM\Neo4j\Mapping\ClassMetadataFactory', $metaDataFactory);
    }

    public function testGetConfiguration()
    {
        $this->assertInstanceOf('\OGM\Neo4j\Configuration', $this->nm->getConfiguration());
    }

    public function testGetUnitOfWork()
    {
        $this->assertInstanceOf('\OGM\Neo4j\UnitOfWork', $this->nm->getUnitOfWork());
    }

    public function testGetProxyFactory()
    {
        $this->assertInstanceOf('\OGM\Neo4j\Proxy\ProxyFactory', $this->nm->getProxyFactory());
    }

    public function testGetEventManager()
    {
        $this->assertInstanceOf('\Doctrine\Common\EventManager', $this->nm->getEventManager());
    }

    public function testGetSchemaManager()
    {
        $this->assertInstanceOf('\OGM\Neo4j\SchemaManager', $this->nm->getSchemaManager());
    }

//    public function testCreateQueryBuilder()
//    {
//        $this->assertInstanceOf('\OGM\Neo4j\Query\Builder', $this->nm->createQueryBuilder());
//    }

//    public function testGetFilterCollection()
//    {
//        $this->assertInstanceOf('\OGM\Neo4j\Query\FilterCollection', $this->nm->getFilterCollection());
//    }  
    
	/*
    public function testGetPartialReference()
    {
        $user = $this->nm->getPartialReference('Documents\CmsUser', 42);
        $this->assertTrue($this->nm->contains($user));
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
//        $this->nm->$methodName(null);
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
//        $this->nm->close();
//        if ($methodName === 'flush') {
//            $this->nm->$methodName();
//        } else {
//            $this->nm->$methodName(new \stdClass());
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
