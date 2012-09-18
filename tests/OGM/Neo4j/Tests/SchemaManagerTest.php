<?php

namespace OGM\Neo4j\Tests;

use OGM\Neo4j\NodeManager;
use OGM\Neo4j\Configuration;
use Doctrine\Common\Annotations\AnnotationReader;

class SchemaManagerTest extends \OGM\Neo4j\Tests\BaseTest
{
	/*
	public function testDeleteDatabase()
	{
		$client = $this->gm->getClient();
		
		$path = '/cleandb/secret-key';
		$response = $client->getTransport()->delete($path);
		
		if($response['code'] !== 200)
		{
			$this->markTestSkipped('Delete plugin probably not installed');
		}
	}
	*/
	
	public function testGetReferenceNodeId()
	{
		$referenceNode = $this->gm->getSchemaManager()->getReferenceNode();
		
		$this->assertEquals(0, $referenceNode->getId());
	}
	
	public function testCreateCollections()
	{
		$this->gm->getSchemaManager()->createCollections();
		
		$client = $this->gm->getClient();
		
		$collectionsIndex = new \Everyman\Neo4j\Index\NodeIndex($client, 'collections');
		$nodes = $collectionsIndex->query('name:*');
		
		$nodeNames = array();
		foreach ($nodes as $node)
		{
			$nodeNames[] = $node->name;
		}
		
		$expectedNames = array(
			'Nodes\Cinema',
			'Nodes\Movie',
			'Nodes\Person'
		);

		$this->assertEquals(3, count($nodes));
		$this->assertEquals(3, count($expectedNames));
		$this->assertEquals($expectedNames, $nodeNames);
	}
}