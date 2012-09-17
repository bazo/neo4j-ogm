<?php
namespace OGM\Neo4j;

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;

/**
 * Configuration
 *
 * @author Martin
 */
class Configuration
{
	/**
     * Array of attributes for this configuration instance.
     *
     * @var array $attributes
     */
    protected $attributes = array(
        'retryConnect' => 0,
        'retryQuery' => 0,
		'proxyDir' => null,
		'hydratorDir' => null,
		'autoGenerateProxyClasses' => false,
		'proxyNamespace' => null,
		'autoGenerateHydratorClasses' => false,
		'hydratorNamespace' => null,
		'metadataDriverImpl' => null,
		'metadataCacheImpl' => null,
		/*
		'host' => '127.0.0.1',
		'port' => 7474,
		'username' => null,
		'password' => null
		 * 
		 */
    );
	
	/**
     * Sets the directory where OGM generates any necessary proxy class files.
     *
     * @param string $dir
     */
    public function setProxyDir($dir)
    {
        $this->attributes['proxyDir'] = $dir;
    }

    /**
     * Gets the directory where OGM generates any necessary proxy class files.
     *
     * @return string
     */
    public function getProxyDir()
    {
        return $this->attributes['proxyDir'];
    }
	
	/**
     * Gets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @return boolean
     */
    public function getAutoGenerateProxyClasses()
    {
        return $this->attributes['autoGenerateProxyClasses'];
    }

    /**
     * Sets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @param boolean $bool
     */
    public function setAutoGenerateProxyClasses($bool)
    {
        $this->attributes['autoGenerateProxyClasses'] = $bool;
    }

    /**
     * Gets the namespace where proxy classes reside.
     *
     * @return string
     */
    public function getProxyNamespace()
    {
        return $this->attributes['proxyNamespace'];
    }

    /**
     * Sets the namespace where proxy classes reside.
     *
     * @param string $ns
     */
    public function setProxyNamespace($ns)
    {
        $this->attributes['proxyNamespace'] = $ns;
    }

    /**
     * Sets the directory where Doctrine generates hydrator class files.
     *
     * @param string $dir
     */
    public function setHydratorDir($dir)
    {
        $this->attributes['hydratorDir'] = $dir;
    }

    /**
     * Gets the directory where Doctrine generates hydrator class files.
     *
     * @return string
     */
    public function getHydratorDir()
    {
        return $this->attributes['hydratorDir'];
    }

    /**
     * Gets a boolean flag that indicates whether hydrator classes should always be regenerated
     * during each script execution.
     *
     * @return boolean
     */
    public function getAutoGenerateHydratorClasses()
    {
        return $this->attributes['autoGenerateHydratorClasses'];
    }

    /**
     * Sets a boolean flag that indicates whether hydrator classes should always be regenerated
     * during each script execution.
     *
     * @param boolean $bool
     */
    public function setAutoGenerateHydratorClasses($bool)
    {
        $this->attributes['autoGenerateHydratorClasses'] = $bool;
    }

    /**
     * Gets the namespace where hydrator classes reside.
     *
     * @return string
     */
    public function getHydratorNamespace()
    {
        return $this->attributes['hydratorNamespace'];
    }

    /**
     * Sets the namespace where hydrator classes reside.
     *
     * @param string $ns
     */
    public function setHydratorNamespace($ns)
    {
        $this->attributes['hydratorNamespace'] = $ns;
    }
	
	/**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param Driver $driverImpl
     * @todo Force parameter to be a Closure to ensure lazy evaluation
     *       (as soon as a metadata cache is in effect, the driver never needs to initialize).
     */
    public function setMetadataDriverImpl(MappingDriver $driverImpl)
    {
        $this->attributes['metadataDriverImpl'] = $driverImpl;
    }

    /**
     * Add a new default annotation driver with a correctly configured annotation reader.
     *
     * @param array $paths
     * @return Mapping\Driver\AnnotationDriver
     */
    public function newDefaultAnnotationDriver($paths = array())
    {
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();

        return new \OGM\Neo4j\Mapping\Driver\AnnotationDriver($reader, (array) $paths);
    }

    /**
     * Gets the cache driver implementation that is used for the mapping metadata.
     *
     * @return Mapping\Driver\Driver
     */
    public function getMetadataDriverImpl()
    {
        return $this->attributes['metadataDriverImpl'];
    }

    /**
     * Gets the cache driver implementation that is used for metadata caching.
     *
     * @return \Doctrine\Common\Cache\Cache
     */
    public function getMetadataCacheImpl()
    {
        return $this->attributes['metadataCacheImpl'];
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param \Doctrine\Common\Cache\Cache $cacheImpl
     */
    public function setMetadataCacheImpl(Cache $cacheImpl)
    {
        $this->attributes['metadataCacheImpl'] = $cacheImpl;
    }
}
