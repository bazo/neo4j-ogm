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

namespace OGM\Neo4j\Mapping;

use Doctrine\Common\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Common\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Common\Persistence\Mapping\ReflectionService;

use OGM\Neo4j\NodeManager;
use OGM\Neo4j\Configuration;
use OGM\Neo4j\Mapping\ClassMetadata;
use OGM\Neo4j\Mapping\MappingException;
use OGM\Neo4j\Events;

/**
 * The ClassMetadataFactory is used to create ClassMetadata objects that contain all the
 * metadata mapping informations of a class which describes how a class should be mapped
 * to a graph database.
 *
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class ClassMetadataFactory extends AbstractClassMetadataFactory
{
    protected $cacheSalt = "\$MONGODBODMCLASSMETADATA";

    /** @var NodeManager The NodeManager instance */
    private $nm;

    /** @var Configuration The Configuration instance */
    private $config;

    /** @var \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver The used metadata driver. */
    private $driver;

    /** @var \Doctrine\Common\EventManager The event manager instance */
    private $evm;

    /**
     * Sets the NodeManager instance for this class.
     *
     * @param NodeManager $nm The NodeManager instance
     */
    public function setNodeManager(NodeManager $nm)
    {
        $this->nm = $nm;
    }

    /**
     * Sets the Configuration instance
     *
     * @param Configuration $config
     */
    public function setConfiguration(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Lazy initialization of this stuff, especially the metadata driver,
     * since these are not needed at all when a metadata cache is active.
     */
    protected function initialize()
    {
        $this->driver = $this->config->getMetadataDriverImpl();
        $this->evm = $this->nm->getEventManager();
        $this->initialized = true;
    }

    /**
     * {@inheritDoc}
     */
    protected function getFqcnFromAlias($namespaceAlias, $simpleClassName)
    {
        return $this->config->getNodeNamespace($namespaceAlias) . '\\' . $simpleClassName;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDriver()
    {
        return $this->driver;
    }

    /**
     * {@inheritDoc}
     */
    protected function wakeupReflection(ClassMetadataInterface $class, ReflectionService $reflService)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeReflection(ClassMetadataInterface $class, ReflectionService $reflService)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function isEntity(ClassMetadataInterface $class)
    {
        return ! $class->isMappedSuperclass && ! $class->isEmbeddedNode;
    }

    /**
     * {@inheritDoc}
     */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents = array())
    {
        /** @var $class ClassMetadata */
        /** @var $parent ClassMetadata */
        if ($parent) {
            $class->setInheritanceType($parent->inheritanceType);
            $class->setDiscriminatorField($parent->discriminatorField);
            $class->setDiscriminatorMap($parent->discriminatorMap);
            $class->setIdGeneratorType($parent->generatorType);
            $this->addInheritedFields($class, $parent);
            $this->addInheritedIndexes($class, $parent);
            $class->setIdentifier($parent->identifier);
            $class->setVersioned($parent->isVersioned);
            $class->setVersionField($parent->versionField);
            $class->setLifecycleCallbacks($parent->lifecycleCallbacks);
            $class->setChangeTrackingPolicy($parent->changeTrackingPolicy);
            $class->setFile($parent->getFile());
            if ($parent->isMappedSuperclass) {
                $class->setCustomRepositoryClass($parent->customRepositoryClassName);
            }
        }

        // Invoke driver
        try {
            $this->driver->loadMetadataForClass($class->getName(), $class);
        } catch (\ReflectionException $e) {
            throw MappingException::reflectionFailure($class->getName(), $e);
        }

        $this->validateIdentifier($class);

        if ($parent && $rootEntityFound) {
            if ($parent->generatorType) {
                $class->setIdGeneratorType($parent->generatorType);
            }
            if ($parent->generatorOptions) {
                $class->setIdGeneratorOptions($parent->generatorOptions);
            }
            if ($parent->idGenerator) {
                $class->setIdGenerator($parent->idGenerator);
            }
        } else {
            $this->completeIdGeneratorMapping($class);
        }

        if ($parent && $parent->isInheritanceTypeSingleCollection()) {
            $class->setDatabase($parent->getDatabase());
            $class->setCollection($parent->getCollection());
        }

        $class->setParentClasses($nonSuperclassParents);

        if ($this->evm->hasListeners(Events::loadClassMetadata)) {
            $eventArgs = new \OGM\Neo4j\Event\LoadClassMetadataEventArgs($class, $this->nm);
            $this->evm->dispatchEvent(Events::loadClassMetadata, $eventArgs);
        }
    }

    /**
     * Validates the identifier mapping.
     *
     * @param ClassMetadata $class
     */
    protected function validateIdentifier($class)
    {
        if ( ! $class->identifier && ! $class->isMappedSuperclass && ! $class->isEmbeddedNode) {
            throw MappingException::identifierRequired($class->name);
        }
    }

    /**
     * Creates a new ClassMetadata instance for the given class name.
     *
     * @param string $className
     * @return OGM\Neo4j\Mapping\ClassMetadata
     */
    protected function newClassMetadataInstance($className)
    {
        return new ClassMetadata($className);
    }

    private function completeIdGeneratorMapping(ClassMetadataInfo $class)
    {
        $idGenOptions = $class->generatorOptions;
        switch ($class->generatorType) {
            case ClassMetadata::GENERATOR_TYPE_AUTO:
                $class->setIdGenerator(new \OGM\Neo4j\Id\AutoGenerator($class));
                break;
            case ClassMetadata::GENERATOR_TYPE_INCREMENT:
                $incrementGenerator = new \OGM\Neo4j\Id\IncrementGenerator($class);
                if (isset($idGenOptions['key'])) {
                    $incrementGenerator->setKey($idGenOptions['key']);
                }
                if (isset($idGenOptions['collection'])) {
                    $incrementGenerator->setCollection($idGenOptions['collection']);
                }
                $class->setIdGenerator($incrementGenerator);
                break;
            case ClassMetadata::GENERATOR_TYPE_UUID:
                $uuidGenerator = new \OGM\Neo4j\Id\UuidGenerator($class);
                $uuidGenerator->setSalt(isset($idGenOptions['salt']) ? $idGenOptions['salt'] : php_uname('n'));
                $class->setIdGenerator($uuidGenerator);
                break;
            case ClassMetadata::GENERATOR_TYPE_ALNUM:
                $alnumGenerator = new \OGM\Neo4j\Id\AlnumGenerator($class);
                if (isset($idGenOptions['pad'])) {
                    $alnumGenerator->setPad($idGenOptions['pad']);
                }
                if (isset($idGenOptions['chars'])) {
                    $alnumGenerator->setChars($idGenOptions['chars']);
                } elseif (isset($idGenOptions['awkwardSafe'])) {
                    $alnumGenerator->setAwkwardSafeMode($idGenOptions['awkwardSafe']);
                }

                $class->setIdGenerator($alnumGenerator);
                break;
            case ClassMetadata::GENERATOR_TYPE_NONE;
                break;
            default:
                throw new MappingException("Unknown generator type: " . $class->generatorType);
        }
    }

    /**
     * Adds inherited fields to the subclass mapping.
     *
     * @param OGM\Neo4j\Mapping\ClassMetadata $subClass
     * @param OGM\Neo4j\Mapping\ClassMetadata $parentClass
     */
    private function addInheritedFields(ClassMetadata $subClass, ClassMetadata $parentClass)
    {
        foreach ($parentClass->fieldMappings as $fieldName => $mapping) {
            if ( ! isset($mapping['inherited']) && ! $parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }
            if ( ! isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }
            $subClass->addInheritedFieldMapping($mapping);
        }
        foreach ($parentClass->reflFields as $name => $field) {
            $subClass->reflFields[$name] = $field;
        }
    }

    /**
     * Adds inherited indexes to the subclass mapping.
     *
     * @param OGM\Neo4j\Mapping\ClassMetadata $subClass
     * @param OGM\Neo4j\Mapping\ClassMetadata $parentClass
     */
    private function addInheritedIndexes(ClassMetadata $subClass, ClassMetadata $parentClass)
    {
        foreach ($parentClass->indexes as $index) {
            $subClass->addIndex($index['keys'], $index['options']);
        }
    }
}
