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

namespace OGM\Neo4j\Hydrator;

use OGM\Neo4j\Mapping\ClassMetadata;
use OGM\Neo4j\GraphManager;
use OGM\Neo4j\Mapping\Types\Type;
use OGM\Neo4j\UnitOfWork;
use OGM\Neo4j\Events;
use Doctrine\Common\EventManager;
use OGM\Neo4j\Event\LifecycleEventArgs;
use OGM\Neo4j\Event\PreLoadEventArgs;
use OGM\Neo4j\PersistentCollection;
use Doctrine\Common\Collections\ArrayCollection;
use OGM\Neo4j\Proxy\Proxy;

/**
 * The HydratorFactory class is responsible for instantiating a correct hydrator
 * type based on node's ClassMetadata
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class HydratorFactory
{
    /**
     * The GraphManager this factory is bound to.
     *
     * @var OGM\Neo4j\GraphManager
     */
    private $gm;

    /**
     * The UnitOfWork used to coordinate object-level transactions.
     *
     * @var OGM\Neo4j\UnitOfWork
     */
    private $unitOfWork;

    /**
     * The EventManager associated with this Hydrator
     *
     * @var Doctrine\Common\EventManager
     */
    private $evm;

    /**
     * Whether to automatically (re)generate hydrator classes.
     *
     * @var boolean
     */
    private $autoGenerate;

    /**
     * The namespace that contains all hydrator classes.
     *
     * @var string
     */
    private $hydratorNamespace;

    /**
     * The directory that contains all hydrator classes.
     *
     * @var string
     */
    private $hydratorDir;

    /**
     * Array of instantiated node hydrators.
     *
     * @var array
     */
    private $hydrators = array();

    public function __construct(GraphManager $gm, EventManager $evm, $hydratorDir, $hydratorNs, $autoGenerate)
    {
        if ( ! $hydratorDir) {
            throw HydratorException::hydratorDirectoryRequired();
        }
        if ( ! $hydratorNs) {
            throw HydratorException::hydratorNamespaceRequired();
        }
        $this->gm                = $gm;
        $this->evm               = $evm;
        $this->hydratorDir       = $hydratorDir;
        $this->hydratorNamespace = $hydratorNs;
        $this->autoGenerate      = $autoGenerate;
    }

    /**
     * Sets the UnitOfWork instance.
     *
     * @param UnitOfWork $uow
     */
    public function setUnitOfWork(UnitOfWork $uow)
    {
        $this->unitOfWork = $uow;
    }

    /**
     * Gets the hydrator object for the given node class.
     *
     * @param string $className
     * @return OGM\Neo4j\Hydrator\HydratorInterface $hydrator
     */
    public function getHydratorFor($className)
    {
        if (isset($this->hydrators[$className])) {
            return $this->hydrators[$className];
        }
        $hydratorClassName = str_replace('\\', '', $className) . 'Hydrator';
        $fqn = $this->hydratorNamespace . '\\' . $hydratorClassName;
        $class = $this->gm->getClassMetadata($className);

        if (! class_exists($fqn, false)) {
            $fileName = $this->hydratorDir . DIRECTORY_SEPARATOR . $hydratorClassName . '.php';
            if ($this->autoGenerate) {
                $this->generateHydratorClass($class, $hydratorClassName, $fileName);
            }
            require $fileName;
        }
        $this->hydrators[$className] = new $fqn($this->gm, $this->unitOfWork, $class);
        return $this->hydrators[$className];
    }

    /**
     * Generates hydrator classes for all given classes.
     *
     * @param array $classes The classes (ClassMetadata instances) for which to generate hydrators.
     * @param string $toDir The target directory of the hydrator classes. If not specified, the
     *                      directory configured on the Configuration of the GraphManager used
     *                      by this factory is used.
     */
    public function generateHydratorClasses(array $classes, $toDir = null)
    {
        $hydratorDir = $toDir ?: $this->hydratorDir;
        $hydratorDir = rtrim($hydratorDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach ($classes as $class) {
            $hydratorClassName = str_replace('\\', '', $class->name) . 'Hydrator';
            $hydratorFileName = $hydratorDir . $hydratorClassName . '.php';
            $this->generateHydratorClass($class, $hydratorClassName, $hydratorFileName);
        }
    }

    private function generateHydratorClass(ClassMetadata $class, $hydratorClassName, $fileName)
    {
        $code = '';

        foreach ($class->fieldMappings as $fieldName => $mapping) {
            if (isset($mapping['alsoLoadFields'])) {
                foreach ($mapping['alsoLoadFields'] as $name) {
                    $code .= sprintf(<<<EOF

        /** @AlsoLoad("$name") */
        if (isset(\$data['$name'])) {
            \$data['$fieldName'] = \$data['$name'];
        }

EOF
                    );
                }
            }

            if ($mapping['type'] === 'date') {
                $code .= sprintf(<<<EOF

        /** @Field(type="date") */
        if (isset(\$data['%1\$s'])) {
            \$value = \$data['%1\$s'];
            %3\$s
            \$this->class->reflFields['%2\$s']->setValue(\$node, clone \$return);
            \$hydratedData['%2\$s'] = \$return;
        }

EOF
                ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    Type::getType($mapping['type'])->closureToPHP()
                );


            } elseif ( ! isset($mapping['association'])) {
                $code .= sprintf(<<<EOF

        /** @Field(type="{$mapping['type']}") */
        if (isset(\$data['%1\$s'])) {
            \$value = \$data['%1\$s'];
            %3\$s
            \$this->class->reflFields['%2\$s']->setValue(\$node, \$return);
            \$hydratedData['%2\$s'] = \$return;
        }

EOF
                ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    Type::getType($mapping['type'])->closureToPHP()
                );
            } elseif ($mapping['association'] === ClassMetadata::REFERENCE_ONE && $mapping['isOwningSide']) {
                $code .= sprintf(<<<EOF

        /** @ReferenceOne */
        if (isset(\$data['%1\$s'])) {
            \$reference = \$data['%1\$s'];
            if (isset(\$this->class->fieldMappings['%2\$s']['simple']) && \$this->class->fieldMappings['%2\$s']['simple']) {
                \$className = \$this->class->fieldMappings['%2\$s']['targetNode'];
                \$mongoId = \$reference;
            } else {
                \$className = \$this->gm->getClassNameFromDiscriminatorValue(\$this->class->fieldMappings['%2\$s'], \$reference);
                \$mongoId = \$reference['\$id'];
            }
            \$targetMetadata = \$this->gm->getClassMetadata(\$className);
            \$id = \$targetMetadata->getPHPIdentifierValue(\$mongoId);
            \$return = \$this->gm->getReference(\$className, \$id);
            \$this->class->reflFields['%2\$s']->setValue(\$node, \$return);
            \$hydratedData['%2\$s'] = \$return;
        }

EOF
                ,
                    $mapping['name'],
                    $mapping['fieldName']
                );
            } elseif ($mapping['association'] === ClassMetadata::REFERENCE_ONE && $mapping['isInverseSide']) {
                if (isset($mapping['repositoryMethod']) && $mapping['repositoryMethod']) {
                    $code .= sprintf(<<<EOF

        \$className = \$this->class->fieldMappings['%2\$s']['targetNode'];
        \$return = \$this->gm->getRepository(\$className)->%3\$s(\$node);
        \$this->class->reflFields['%2\$s']->setValue(\$node, \$return);
        \$hydratedData['%2\$s'] = \$return;

EOF
                ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    $mapping['repositoryMethod']
                );
                } else {
                    $code .= sprintf(<<<EOF

        \$mapping = \$this->class->fieldMappings['%2\$s'];
        \$className = \$mapping['targetNode'];
        \$targetClass = \$this->gm->getClassMetadata(\$mapping['targetNode']);
        \$mappedByMapping = \$targetClass->fieldMappings[\$mapping['mappedBy']];
        \$mappedByFieldName = isset(\$mappedByMapping['simple']) && \$mappedByMapping['simple'] ? \$mapping['mappedBy'] : \$mapping['mappedBy'].'.id';
        \$criteria = array_merge(
            array(\$mappedByFieldName => \$data['_id']),
            isset(\$this->class->fieldMappings['%2\$s']['criteria']) ? \$this->class->fieldMappings['%2\$s']['criteria'] : array() 
        );
        \$sort = isset(\$this->class->fieldMappings['%2\$s']['sort']) ? \$this->class->fieldMappings['%2\$s']['sort'] : array();
        \$return = \$this->unitOfWork->getNodePersister(\$className)->load(\$criteria, null, array(), 0, \$sort);
        \$this->class->reflFields['%2\$s']->setValue(\$node, \$return);
        \$hydratedData['%2\$s'] = \$return;

EOF
                    ,
                        $mapping['name'],
                        $mapping['fieldName']
                    );
                }
            } elseif ($mapping['association'] === ClassMetadata::REFERENCE_MANY || $mapping['association'] === ClassMetadata::EMBED_MANY) {
                $code .= sprintf(<<<EOF

        /** @Many */
        \$mongoData = isset(\$data['%1\$s']) ? \$data['%1\$s'] : null;
        \$return = new \OGM\Neo4j\PersistentCollection(new \Doctrine\Common\Collections\ArrayCollection(), \$this->gm, \$this->unitOfWork, '$');
        \$return->setHints(\$hints);
        \$return->setOwner(\$node, \$this->class->fieldMappings['%2\$s']);
        \$return->setInitialized(false);
        if (\$mongoData) {
            \$return->setMongoData(\$mongoData);
        }
        \$this->class->reflFields['%2\$s']->setValue(\$node, \$return);
        \$hydratedData['%2\$s'] = \$return;

EOF
                ,
                    $mapping['name'],
                    $mapping['fieldName']
                );
            } elseif ($mapping['association'] === ClassMetadata::EMBED_ONE) {
                $code .= sprintf(<<<EOF

        /** @EmbedOne */
        if (isset(\$data['%1\$s'])) {
            \$embeddedNode = \$data['%1\$s'];
            \$className = \$this->gm->getClassNameFromDiscriminatorValue(\$this->class->fieldMappings['%2\$s'], \$embeddedNode);
            \$embeddedMetadata = \$this->gm->getClassMetadata(\$className);
            \$return = \$embeddedMetadata->newInstance();

            \$embeddedData = \$this->gm->getHydratorFactory()->hydrate(\$return, \$embeddedNode, \$hints);
            \$this->unitOfWork->registerManaged(\$return, null, \$embeddedData);
            \$this->unitOfWork->setParentAssociation(\$return, \$this->class->fieldMappings['%2\$s'], \$node, '%1\$s');

            \$this->class->reflFields['%2\$s']->setValue(\$node, \$return);
            \$hydratedData['%2\$s'] = \$return;
        }

EOF
                ,
                    $mapping['name'],
                    $mapping['fieldName']
                );
            }
        }

        $className = $class->name;
        $namespace = $this->hydratorNamespace;
        $code = sprintf(<<<EOF
<?php

namespace $namespace;

use OGM\Neo4j\GraphManager;
use OGM\Neo4j\Mapping\ClassMetadata;
use OGM\Neo4j\Hydrator\HydratorInterface;
use OGM\Neo4j\UnitOfWork;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ODM. DO NOT EDIT THIS FILE.
 */
class $hydratorClassName implements HydratorInterface
{
    private \$gm;
    private \$unitOfWork;
    private \$class;

    public function __construct(GraphManager \$gm, UnitOfWork \$uow, ClassMetadata \$class)
    {
        \$this->gm = \$gm;
        \$this->unitOfWork = \$uow;
        \$this->class = \$class;
    }

    public function hydrate(\$node, \$data, array \$hints = array())
    {
        \$hydratedData = array();
%s        return \$hydratedData;
    }
}
EOF
          ,
          $code
        );

        file_put_contents($fileName, $code);
    }

    /**
     * Hydrate array of Neo4j node data into the given node object.
     *
     * @param object $node  The node object to hydrate the data into.
     * @param array $data The array of node data.
     * @param array $hints Any hints to account for during reconstitution/lookup of the node.
     * @return array $values The array of hydrated values.
     */
    public function hydrate($node, $data, array $hints = array())
    {
        $metadata = $this->gm->getClassMetadata(get_class($node));
        // Invoke preLoad lifecycle events and listeners
        if (isset($metadata->lifecycleCallbacks[Events::preLoad])) {
            $args = array(&$data);
            $metadata->invokeLifecycleCallbacks(Events::preLoad, $node, $args);
        }
        if ($this->evm->hasListeners(Events::preLoad)) {
            $this->evm->dispatchEvent(Events::preLoad, new PreLoadEventArgs($node, $this->gm, $data));
        }

        // Use the alsoLoadMethods on the node object to transform the data before hydration
        if (isset($metadata->alsoLoadMethods)) {
            foreach ($metadata->alsoLoadMethods as $fieldName => $method) {
                if (isset($data[$fieldName])) {
                    $node->$method($data[$fieldName]);
                }
            }
        }

        $data = $this->getHydratorFor($metadata->name)->hydrate($node, $data, $hints);
        if ($node instanceof Proxy) {
            $node->__isInitialized__ = true;
        }

        // Invoke the postLoad lifecycle callbacks and listeners
        if (isset($metadata->lifecycleCallbacks[Events::postLoad])) {
            $metadata->invokeLifecycleCallbacks(Events::postLoad, $node);
        }
        if ($this->evm->hasListeners(Events::postLoad)) {
            $this->evm->dispatchEvent(Events::postLoad, new LifecycleEventArgs($node, $this->gm));
        }

        return $data;
    }
}
