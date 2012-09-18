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

use Doctrine\Common\Persistence\ObjectRepository;

/**
 * An NodeRepository serves as a repository for nodes with generic as well as
 * business specific methods for retrieving nodes.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate nodes.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class NodeRepository implements ObjectRepository
{
    /**
     * @var string
     */
    protected $nodeName;

    /**
     * @var NodeManager
     */
    protected $gm;

    /**
     * @var UnitOfWork
     */
    protected $uow;

    /**
     * @var OGM\Neo4j\Mapping\ClassMetadata
     */
    protected $class;

    /**
     * Initializes a new <tt>NodeRepository</tt>.
     *
     * @param NodeManager $gm The NodeManager to use.
     * @param UnitOfWork $uow The UnitOfWork to use.
     * @param ClassMetadata $classMetadata The class descriptor.
     */
    public function __construct(NodeManager $gm, UnitOfWork $uow, Mapping\ClassMetadata $class)
    {
        $this->nodeName = $class->name;
        $this->gm           = $gm;
        $this->uow          = $uow;
        $this->class        = $class;
    }

    /**
     * Create a new Query\Builder instance that is prepopulated for this node name
     *
     * @return Query\Builder $qb
     */
    public function createQueryBuilder()
    {
        return $this->gm->createQueryBuilder($this->nodeName);
    }

    /**
     * Clears the repository, causing all managed nodes to become detached.
     */
    public function clear()
    {
        $this->gm->clear($this->class->rootNodeName);
    }

    /**
     * Finds a node by its identifier
     *
     * @throws LockException
     * @param string|object $id The identifier
     * @param int $lockMode
     * @param int $lockVersion
     * @return object The node.
     */
    public function find($id, $lockMode = LockMode::NONE, $lockVersion = null)
    {
        if ($id === null) {
            return;
        }
        if (is_array($id)) {
            list($identifierFieldName) = $this->class->getIdentifierFieldNames();

            if (!isset($id[$identifierFieldName])) {
                throw MongoDBException::missingIdentifierField($this->nodeName, $identifierFieldName);
            }

            $id = $id[$identifierFieldName];
        }
        
        // Check identity map first
        if ($node = $this->uow->tryGetById($id, $this->class->rootNodeName)) {
            if ($lockMode != LockMode::NONE) {
                $this->gm->lock($node, $lockMode, $lockVersion);
            }

            return $node; // Hit!
        }

        if ($lockMode == LockMode::NONE) {
            return $this->uow->getNodePersister($this->nodeName)->load($id);
        } else if ($lockMode == LockMode::OPTIMISTIC) {
            if (!$this->class->isVersioned) {
                throw LockException::notVersioned($this->nodeName);
            }
            $node = $this->uow->getNodePersister($this->nodeName)->load($id);

            $this->uow->lock($node, $lockMode, $lockVersion);

            return $node;
        } else {
            return $this->uow->getNodePersister($this->nodeName)->load($id, null, array(), $lockMode);
        }
    }

    /**
     * Finds all nodes in the repository.
     *
     * @return array The entities.
     */
    public function findAll()
    {
        return $this->findBy(array());
    }

    /**
     * Finds nodes by a set of criteria.
     *
     * @param array $criteria
     * @return array
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->uow->getNodePersister($this->nodeName)->loadAll($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Finds a single node by a set of criteria.
     *
     * @param array $criteria
     * @return object
     */
    public function findOneBy(array $criteria)
    {
        return $this->uow->getNodePersister($this->nodeName)->load($criteria);
    }

    /**
     * Adds support for magic finders.
     *
     * @return array|object The found node/nodes.
     * @throws BadMethodCallException  If the method called is an invalid find* method
     *                                 or no find* method at all and therefore an invalid
     *                                 method call.
     */
    public function __call($method, $arguments)
    {
        if (substr($method, 0, 6) == 'findBy') {
            $by = substr($method, 6, strlen($method));
            $method = 'findBy';
        } elseif (substr($method, 0, 9) == 'findOneBy') {
            $by = substr($method, 9, strlen($method));
            $method = 'findOneBy';
        } else {
            throw new \BadMethodCallException(
                "Undefined method '$method'. The method name must start with ".
                "either findBy or findOneBy!"
            );
        }

        if ( ! isset($arguments[0])) {
            throw MongoDBException::findByRequiresParameter($method.$by);
        }

        $fieldName = lcfirst(\Doctrine\Common\Util\Inflector::classify($by));

        if ($this->class->hasField($fieldName)) {
            return $this->$method(array($fieldName => $arguments[0]));
        } else {
            throw MongoDBException::invalidFindByCall($this->nodeName, $fieldName, $method.$by);
        }
    }

    /**
     * @return string
     */
    public function getNodeName()
    {
        return $this->nodeName;
    }

    /**
     * @return NodeManager
     */
    public function getNodeManager()
    {
        return $this->gm;
    }

    /**
     * @return Mapping\ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->getNodeName();
    }
}
