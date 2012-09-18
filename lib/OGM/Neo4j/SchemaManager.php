<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */
namespace OGM\Neo4j;

use OGM\Neo4j\Mapping\ClassMetadata;
use OGM\Neo4j\Mapping\ClassMetadataFactory;

use Everyman\Neo4j\Client;
use Everyman\Neo4j\Index;

class SchemaManager
{

	/**
	 * @var OGM\Neo4j\GraphManager
	 */
	protected $gm;

	/**
	 *
	 * @var OGM\Neo4j\Mapping\ClassMetadataFactory
	 */
	protected $metadataFactory;
	
	/** @var Client */
	protected $client;

	/**
	 * @param OGM\Neo4j\GraphManager $gm
	 */
	public function __construct(GraphManager $gm, ClassMetadataFactory $cmf)
	{
		$this->gm = $gm;
		$this->metadataFactory = $cmf;
		$this->client = $gm->getClient();
	}

	public function getReferenceNode()
	{
		return $this->client->getReferenceNode();
		
		/**
		$actions = $this->client->discoverActions()->getDiscoveredActions();
		$referenceNodeUri = $actions['reference_node'];
		$referenceNodeId = $this->parseReferenceNodeIdFromUri($referenceNodeUri);
		
		$cmd = new \Neoxygen\UpDown\Command\Node\findNodeById();
		$cmd->setId($referenceNodeId);
		
		$node = $this->client->execute($cmd);
		var_dump($node);exit;
		
		return $this->client->execute($cmd);
		 * 
		 * @param type $referenceNodeUri
		 * @return type
		 */
	}
	
	private function parseReferenceNodeIdFromUri($referenceNodeUri)
	{
		$tmp = explode('/', $referenceNodeUri);
		$id = (int)array_pop($tmp);
		
		return $id;
	}

	/**
	 * Ensure indexes are created for all nodes that can be loaded with the
	 * metadata factory.
	 */
	public function ensureIndexes()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass)
			{
				continue;
			}
			$this->ensureNodeIndexes($class->name);
		}
	}

	/**
	 * Ensure indexes are created for all nodes that can be loaded with the
	 * metadata factory and delete the indexes that exist in MongoDB but not the
	 * node metadata.
	 */
	public function updateIndexes()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			$this->updateNodeIndexes($class->name);
		}
	}

	/**
	 * Ensure the given node's indexes are updated.
	 *
	 * @param string $nodeName
	 */
	public function updateNodeIndexes($nodeName)
	{
		$class = $this->gm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot update node indexes for mapped super classes or embedded nodes.');
		}

		if ($nodeIndexes = $this->getNodeIndexes($nodeName))
		{

			$collection = $this->gm->getNodeCollection($nodeName);
			$mongoIndexes = $collection->getIndexInfo();

			/* Determine which Mongo indexes should be deleted. Exclude the ID
			 * index and those that are equivalent to any in the class metadata.
			 */
			$self = $this;
			$mongoIndexes = array_filter($mongoIndexes, function($mongoIndex) use ($nodeIndexes, $self)
					{
						if ('_id_' === $mongoIndex['name'])
						{
							return false;
						}

						foreach ($nodeIndexes as $nodeIndex)
						{
							if ($self->isMongoIndexEquivalentToNodeIndex($mongoIndex, $nodeIndex))
							{
								return false;
							}
						}

						return true;
					});

			// Delete indexes that do not exist in class metadata
			foreach ($mongoIndexes as $mongoIndex)
			{
				if (isset($mongoIndex['name']))
				{
					/* Note: MongoCollection::deleteIndex() cannot delete
					 * custom-named indexes, so use the deleteIndexes command.
					 */
					$collection->getDatabase()->command(array(
						'deleteIndexes' => $collection->getName(),
						'index' => $mongoIndex['name'],
					));
				}
			}

			$this->ensureNodeIndexes($nodeName);
		}
	}

	/**
	 * Returns all indexes - indexed by nodeName
	 *
	 * @param bool $raw As MongoDB returns them (or as ODM stores them)
	 * @return array
	 */
	public function getAllIndexes($raw = true)
	{
		$all = array();
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			if ($collection = $this->gm->getNodeCollection($class->name))
			{
				$indexes = $collection->getIndexInfo();
				if ($raw)
				{
					$all[$class->name] = $indexes;
				}
				else
				{
					$ogmIndexes = array();
					foreach ($indexes as $rawIndex)
					{
						if ($rawIndex['name'] === '_id_')
						{
							continue;
						}
						$ogmIndexes[] = $this->rawIndexToNodeIndex($rawIndex);
					}
					$all[$class->name] = $ogmIndexes;
				}
			}
		}

		return $all;
	}

	public function getNodeIndexes($nodeName)
	{
		$visited = array();
		return $this->doGetNodeIndexes($nodeName, $visited);
	}

	private function doGetNodeIndexes($nodeName, array &$visited)
	{
		if (isset($visited[$nodeName]))
		{
			return array();
		}

		$visited[$nodeName] = true;

		$class = $this->gm->getClassMetadata($nodeName);
		$indexes = $this->prepareIndexes($class);

		// Add indexes from embedded & referenced nodes
		foreach ($class->fieldMappings as $fieldMapping)
		{
			if (isset($fieldMapping['embedded']) && isset($fieldMapping['targetNode']))
			{
				$embeddedIndexes = $this->doGetNodeIndexes($fieldMapping['targetNode'], $visited);
				foreach ($embeddedIndexes as $embeddedIndex)
				{
					foreach ($embeddedIndex['keys'] as $key => $value)
					{
						$embeddedIndex['keys'][$fieldMapping['name'] . '.' . $key] = $value;
						unset($embeddedIndex['keys'][$key]);
					}
					$indexes[] = $embeddedIndex;
				}
			}
			else if (isset($fieldMapping['reference']) && isset($fieldMapping['targetNode']))
			{
				foreach ($indexes as $idx => $index)
				{
					$newKeys = array();
					foreach ($index['keys'] as $key => $v)
					{
						if ($key == $fieldMapping['name'])
						{
							$key = $fieldMapping['simple'] ? $key : $key . '.$id';
						}
						$newKeys[$key] = $v;
					}
					$indexes[$idx]['keys'] = $newKeys;
				}
			}
		}
		return $indexes;
	}

	private function prepareIndexes(ClassMetadata $class)
	{
		$persister = $this->gm->getUnitOfWork()->getNodePersister($class->name);
		$indexes = $class->getIndexes();
		$newIndexes = array();

		foreach ($indexes as $index)
		{
			$newIndex = array(
				'keys' => array(),
				'options' => $index['options']
			);
			foreach ($index['keys'] as $key => $value)
			{
				$key = $persister->prepareFieldName($key);
				if (isset($class->discriminatorField) && $key === $class->discriminatorField['name'])
				{
					// The discriminator field may have its own mapping
					$newIndex['keys'][$class->discriminatorField['fieldName']] = $value;
				}
				elseif ($class->hasField($key))
				{
					$mapping = $class->getFieldMapping($key);
					$newIndex['keys'][$mapping['name']] = $value;
				}
				else
				{
					$newIndex['keys'][$key] = $value;
				}
			}

			$newIndexes[] = $newIndex;
		}

		return $newIndexes;
	}

	/**
	 * Ensure the given node's indexes are created.
	 *
	 * @param string $nodeName
	 */
	public function ensureNodeIndexes($nodeName)
	{
		$class = $this->gm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot create node indexes for mapped super classes or embedded nodes.');
		}
		if ($indexes = $this->getNodeIndexes($nodeName))
		{
			$collection = $this->gm->getNodeCollection($class->name);
			foreach ($indexes as $index)
			{
				if (!isset($index['options']['safe']))
				{
					$index['options']['safe'] = true;
				}
				$collection->ensureIndex($index['keys'], $index['options']);
			}
		}
	}

	/**
	 * Delete indexes for all nodes that can be loaded with the
	 * metadata factory.
	 */
	public function deleteIndexes()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			$this->deleteNodeIndexes($class->name);
		}
	}

	/**
	 * Delete the given node's indexes.
	 *
	 * @param string $nodeName
	 */
	public function deleteNodeIndexes($nodeName)
	{
		$class = $this->gm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot delete node indexes for mapped super classes or embedded nodes.');
		}
		$this->gm->getNodeCollection($nodeName)->deleteIndexes();
	}

	/**
	 * Create all the mapped node collections in the metadata factory.
	 */
	public function createCollections()
	{
		$referenceNode = $this->getReferenceNode();
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isRelationship)
			{
				continue;
			}
			$this->createNodeCollection($class->name, $referenceNode);
		}
	}

	/**
	 * Create the node collection for a mapped class.
	 *
	 * @param string $nodeName
	 */
	public function createNodeCollection($nodeName, $referenceNode)
	{
		$class = $this->gm->getClassMetadata($nodeName);
		
		if ($class->isMappedSuperclass || $class->isRelationship)
		{
			throw new \InvalidArgumentException('Cannot create node collection for mapped super classes or embedded nodes.');
		}
		
		$client = $this->gm->getClient();
		
		$index = new Index($client, Index::TypeNode, 'collections');
		
		$client->saveIndex($index);
		
		$node = $this->gm->getClient()->makeNode(array(
			'name' => $nodeName
		));

		$client->saveNode($node);
		
		$client->addToIndex($index, $node, 'name', $nodeName);
		
		$node->relateTo($referenceNode, ':COLLECTION:')->save();
	}

	/**
	 * Drop all the mapped node collections in the metadata factory.
	 */
	public function dropCollections()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			$this->dropNodeCollection($class->name);
		}
	}

	/**
	 * Drop the node collection for a mapped class.
	 *
	 * @param string $nodeName
	 */
	public function dropNodeCollection($nodeName)
	{
		$class = $this->gm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot delete node indexes for mapped super classes or embedded nodes.');
		}
		$this->gm->getNodeDatabase($nodeName)->dropCollection(
				$class->getCollection()
		);
	}
}
