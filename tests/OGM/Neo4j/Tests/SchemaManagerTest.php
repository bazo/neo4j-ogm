<?php

namespace OGM\Neo4j\Tests;

use OGM\Neo4j\NodeManager;
use OGM\Neo4j\Configuration;
use Doctrine\Common\Annotations\AnnotationReader;

class SchemaManagerTest extends \OGM\Neo4j\Tests\BaseTest
{
	public function testDeleteDatabase()
	{
		$client = $this->nm->getClient();
		
		$client->delete('http://localhost:7474/db/data/cleandb/secret-key');
	}
	
	public function testGetReferenceNodeId()
	{
		$referenceNodeId = $this->nm->getSchemaManager()->getReferenceNode();
		
		$this->assertEquals(0, $referenceNodeId);
	}
	
	public function testCreateRootNode()
	{
		
	}
}