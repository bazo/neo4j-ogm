<?php

namespace OGM\Neo4j\Tools\Console\Command\Schema;

use OGM\Neo4j\SchemaManager;

/**
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class DropCommand extends AbstractCommand
{
    protected $commandName = 'drop';

    protected function processDocumentCollection(SchemaManager $sm, $document)
    {
        $sm->dropDocumentCollection($document);
    }

    protected function processCollection(SchemaManager $sm)
    {
        $sm->dropCollections();
    }

    protected function processDocumentDb(SchemaManager $sm, $document)
    {
        $sm->dropDocumentDatabase($document);
    }

    protected function processDb(SchemaManager $sm)
    {
        $sm->dropDatabases();
    }

    protected function processDocumentIndex(SchemaManager $sm, $document)
    {
        $sm->deleteDocumentIndexes($document);
    }

    protected function processIndex(SchemaManager $sm)
    {
        $sm->deleteIndexes();
    }
}