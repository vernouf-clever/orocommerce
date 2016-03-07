<?php

namespace OroB2B\Bundle\OrderBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroB2BOrderBundle implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('orob2b_order');
        $table->addColumn('source_entity_class', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('source_entity_id', 'integer', ['notnull' => false]);
    }
}
