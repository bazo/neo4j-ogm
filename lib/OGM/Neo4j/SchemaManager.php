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

class SchemaManager
{

	/**
	 * @var OGM\Neo4j\NodeManager
	 */
	protected $nm;

	/**
	 *
	 * @var OGM\Neo4j\Mapping\ClassMetadataFactory
	 */
	protected $metadataFactory;
	
	/** @var \Neoxygen\UpDown\UpDownClient */
	protected $client;

	/**
	 * @param OGM\Neo4j\NodeManager $nm
	 */
	public function __construct(NodeManager $nm, ClassMetadataFactory $cmf)
	{
		$this->nm = $nm;
		$this->metadataFactory = $cmf;
		$this->client = $nm->getClient();
	}

	public function getReferenceNode()
	{
		$actions = $this->client->discoverActions()->getDiscoveredActions();
		$referenceNodeUri = $actions['reference_node'];
		$referenceNodeId = $this->parseReferenceNodeIdFromUri($referenceNodeUri);
		
		$cmd = new \Neoxygen\UpDown\Command\Node\findNodeById();
		$cmd->setId($referenceNodeId);
		
		$node = $this->client->execute($cmd);
		var_dump($node);exit;
		
		return $this->client->execute($cmd);
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
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot update node indexes for mapped super classes or embedded nodes.');
		}

		if ($nodeIndexes = $this->getNodeIndexes($nodeName))
		{

			$collection = $this->nm->getNodeCollection($nodeName);
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
			if ($collection = $this->nm->getNodeCollection($class->name))
			{
				$indexes = $collection->getIndexInfo();
				if ($raw)
				{
					$all[$class->name] = $indexes;
				}
				else
				{
					$onmIndexes = array();
					foreach ($indexes as $rawIndex)
					{
						if ($rawIndex['name'] === '_id_')
						{
							continue;
						}
						$onmIndexes[] = $this->rawIndexToNodeIndex($rawIndex);
					}
					$all[$class->name] = $onmIndexes;
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

		$class = $this->nm->getClassMetadata($nodeName);
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
		$persister = $this->nm->getUnitOfWork()->getNodePersister($class->name);
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
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot create node indexes for mapped super classes or embedded nodes.');
		}
		if ($indexes = $this->getNodeIndexes($nodeName))
		{
			$collection = $this->nm->getNodeCollection($class->name);
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
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot delete node indexes for mapped super classes or embedded nodes.');
		}
		$this->nm->getNodeCollection($nodeName)->deleteIndexes();
	}

	/**
	 * Create all the mapped node collections in the metadata factory.
	 */
	public function createCollections()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			$this->createNodeCollection($class->name);
		}
	}

	/**
	 * Create the node collection for a mapped class.
	 *
	 * @param string $nodeName
	 */
	public function createNodeCollection($nodeName)
	{
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot create node collection for mapped super classes or embedded nodes.');
		}
		$this->nm->getNodeDatabase($nodeName)->createCollection(
				$class->getCollection(), $class->getCollectionCapped(), $class->getCollectionSize(), $class->getCollectionMax()
		);
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
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot delete node indexes for mapped super classes or embedded nodes.');
		}
		$this->nm->getNodeDatabase($nodeName)->dropCollection(
				$class->getCollection()
		);
	}

	/**
	 * Drop all the mapped node databases in the metadata factory.
	 */
	public function dropDatabases()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			$this->dropNodeDatabase($class->name);
		}
	}

	/**
	 * Drop the node database for a mapped class.
	 *
	 * @param string $nodeName
	 */
	public function dropNodeDatabase($nodeName)
	{
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot drop node database for mapped super classes or embedded nodes.');
		}
		$this->nm->getNodeDatabase($nodeName)->drop();
	}

	/**
	 * Create all the mapped node databases in the metadata factory.
	 */
	public function createDatabases()
	{
		foreach ($this->metadataFactory->getAllMetadata() as $class)
		{
			if ($class->isMappedSuperclass || $class->isEmbeddedNode)
			{
				continue;
			}
			$this->createNodeDatabase($class->name);
		}
	}

	/**
	 * Create the node database for a mapped class.
	 *
	 * @param string $nodeName
	 */
	public function createNodeDatabase($nodeName)
	{
		$class = $this->nm->getClassMetadata($nodeName);
		if ($class->isMappedSuperclass || $class->isEmbeddedNode)
		{
			throw new \InvalidArgumentException('Cannot delete node indexes for mapped super classes or embedded nodes.');
		}
		$this->nm->getNodeDatabase($nodeName)->execute("function() { return true; }");
	}

	/**
	 * Determine if an index returned by MongoCollection::getIndexInfo() can be
	 * considered equivalent to an index in class metadata.
	 *
	 * Indexes are considered different if:
	 *
	 *   (a) Key/direction pairs differ or are not in the same order
	 *   (b) Sparse or unique options differ
	 *   (c) Mongo index is unique without dropDups and mapped index is unique
	 *       with dropDups
	 *   (d) Geospatial options differ (bits, max, min)
	 *
	 * Regarding (c), the inverse case is not a reason to delete and
	 * recreate the index, since dropDups only affects creation of
	 * the unique index. Additionally, the background option is only
	 * relevant to index creation and is not considered.
	 */
	public function isMongoIndexEquivalentToNodeIndex($mongoIndex, $nodeIndex)
	{
		$nodeIndexOptions = $nodeIndex['options'];

		if ($mongoIndex['key'] !== $nodeIndex['keys'])
		{
			return false;
		}

		if (empty($mongoIndex['sparse']) xor empty($nodeIndexOptions['sparse']))
		{
			return false;
		}

		if (empty($mongoIndex['unique']) xor empty($nodeIndexOptions['unique']))
		{
			return false;
		}

		if (!empty($mongoIndex['unique']) && empty($mongoIndex['dropDups']) &&
				!empty($nodeIndexOptions['unique']) && !empty($nodeIndexOptions))
		{

			return false;
		}

		foreach (array('bits', 'max', 'min') as $option)
		{
			if (isset($mongoIndex[$option]) xor isset($nodeIndexOptions[$option]))
			{
				return false;
			}

			if (isset($mongoIndex[$option]) && isset($nodeIndexOptions[$option]) &&
					$mongoIndex[$option] !== $nodeIndexOptions[$option])
			{

				return false;
			}
		}

		return true;
	}

}
