<?php
namespace OGM\Neo4j;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\EventManager;
use OGM\Neo4j\Hydrator\HydratorFactory;
use OGM\Neo4j\Proxy\ProxyFactory;

//use Neoxygen\UpDown\UpDownClient as Client;
use Everyman\Neo4j\Client as Client;

/**
 * NodeManager
 *
 * @author Martin
 */
class GraphManager implements ObjectManager
{
	
	protected
		/** @var Mapping\ClassMetadataFactory */	
		$metadataFactory,
			
		/** @var UnitOfWork */	
		$unitOfWork,
		
		/** @var Client */	
		$client
	;
	
	public static function create(Configuration $config = null, Client $client = null, EventManager $eventManager = null)
	{
		return new static($config, $client, $eventManager);
	}
	
    public function __construct(Configuration $config = null, Client $client = null, EventManager $eventManager = null)
    {
        $this->config = $config ?: new Configuration;
		$this->client = $client;
        $this->eventManager = $eventManager ?: new EventManager;

        $this->metadataFactory = new Mapping\ClassMetadataFactory;
        $this->metadataFactory->setGraphManager($this);
        $this->metadataFactory->setConfiguration($this->config);
		
        if ($cacheDriver = $this->config->getMetadataCacheImpl()) {
            $this->metadataFactory->setCacheDriver($cacheDriver);
        }

        $hydratorDir = $this->config->getHydratorDir();
        $hydratorNs = $this->config->getHydratorNamespace();
        $this->hydratorFactory = new HydratorFactory(
          $this,
          $this->eventManager,
          $hydratorDir,
          $hydratorNs,
          $this->config->getAutoGenerateHydratorClasses()
        );

        $this->unitOfWork = new UnitOfWork($this, $this->eventManager, $this->hydratorFactory);
        $this->hydratorFactory->setUnitOfWork($this->unitOfWork);
        $this->schemaManager = new SchemaManager($this, $this->metadataFactory);
        $this->proxyFactory = new ProxyFactory($this,
                $this->config->getProxyDir(),
                $this->config->getProxyNamespace(),
                $this->config->getAutoGenerateProxyClasses()
        );
    }
	
	/**
     * Gets the UnitOfWork used by the NodeManager to coordinate operations.
     *
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }
	
	/**
     * Gets the metadata factory used to gather the metadata of classes.
     *
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadataFactory
     */
    public function getMetadataFactory()
	{
		return $this->metadataFactory;
	}
	
	/**
     * Gets the Configuration used by the NodeManager.
     *
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->config;
    }
	
	/**
     * Gets the proxy factory used by the NodeManager to create document proxies.
     *
     * @return ProxyFactory
     */
    public function getProxyFactory()
    {
        return $this->proxyFactory;
    }
	
	/**
     * Gets the EventManager used by the NodeManager.
     *
     * @return \Doctrine\Common\EventManager
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }
	
	/**
     * Returns SchemaManager, used to create/drop indexes.
     *
     * @return \OGM\Neo4j\SchemaManager
     */
    public function getSchemaManager()
    {
        return $this->schemaManager;
    }
	
	/**
	 * @return Client
	 */
	public function getClient()
	{
		return $this->client;
	}
	
	/**
     * Finds an object by its identifier.
     *
     * This is just a convenient shortcut for getRepository($className)->find($id).
     *
     * @param string
     * @param mixed
     * @return object
     */
    public function find($className, $id)
	{
		
	}

    /**
     * Tells the ObjectManager to make an instance managed and persistent.
     *
     * The object will be entered into the database as a result of the flush operation.
     *
     * NOTE: The persist operation always considers objects that are not yet known to
     * this ObjectManager as NEW. Do not pass detached objects to the persist operation.
     *
     * @param object $object The instance to make managed and persistent.
     */
    public function persist($object)
	{
		
	}

    /**
     * Removes an object instance.
     *
     * A removed object will be removed from the database as a result of the flush operation.
     *
     * @param object $object The object instance to remove.
     */
    public function remove($object)
	{
		
	}

    /**
     * Merges the state of a detached object into the persistence context
     * of this ObjectManager and returns the managed copy of the object.
     * The object passed to merge will not become associated/managed with this ObjectManager.
     *
     * @param object $object
     * @return object
     */
    public function merge($object)
	{
		
	}

    /**
     * Clears the ObjectManager. All objects that are currently managed
     * by this ObjectManager become detached.
     *
     * @param string $objectName if given, only objects of this type will get detached
     */
    public function clear($objectName = null)
	{
		
	}

    /**
     * Detaches an object from the ObjectManager, causing a managed object to
     * become detached. Unflushed changes made to the object if any
     * (including removal of the object), will not be synchronized to the database.
     * Objects which previously referenced the detached object will continue to
     * reference it.
     *
     * @param object $object The object to detach.
     */
    public function detach($object)
	{
		
	}

    /**
     * Refreshes the persistent state of an object from the database,
     * overriding any local changes that have not yet been persisted.
     *
     * @param object $object The object to refresh.
     */
    public function refresh($object)
	{
		
	}

    /**
     * Flushes all changes to objects that have been queued up to now to the database.
     * This effectively synchronizes the in-memory state of managed objects with the
     * database.
     */
    public function flush()
	{
		
	}

    /**
     * Gets the repository for a class.
     *
     * @param string $className
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository($className)
	{
		
	}

    /**
     * Returns the ClassMetadata descriptor for a class.
     *
     * The class name must be the fully-qualified class name without a leading backslash
     * (as it is returned by get_class($obj)).
     *
     * @param string $className
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    public function getClassMetadata($className)
	{
		return $this->metadataFactory->getMetadataFor($className);
	}

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * This method is a no-op for other objects.
     *
     * @param object $obj
     */
    public function initializeObject($obj)
	{
		
	}

    /**
     * Check if the object is part of the current UnitOfWork and therefore
     * managed.
     *
     * @param object $object
     * @return bool
     */
    public function contains($object)
	{
		
	}
}
