<?php

namespace OGM\Neo4j\Tools\Console\Helper;

use Symfony\Component\Console\Helper\Helper;
use OGM\Neo4j\NodeManager;

/**
 * @author Martin Bazik <martin@bazo.sk>
 */
class NodeManagerHelper extends Helper
{
	/** @var OGM\Neo4j\NodeManager */
    protected $nm;
	
    public function __construct(NodeManager $nm)
    {
        $this->nm = $nm;
    }
    public function getNodeManager()
    {
        return $this->nm;
    }
    public function getName()
    {
        return 'nodeManager';
    }
}