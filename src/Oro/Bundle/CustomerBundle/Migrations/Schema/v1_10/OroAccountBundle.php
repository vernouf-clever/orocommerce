<?php

namespace Oro\Bundle\CustomerBundle\Migrations\Schema\v1_10;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Extension\RenameExtension;
use Oro\Bundle\MigrationBundle\Migration\Extension\RenameExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\MigrationConstraintTrait;
use Oro\Bundle\MigrationBundle\Migration\OrderedMigrationInterface;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAccountBundle implements Migration, RenameExtensionAwareInterface, OrderedMigrationInterface
{
    use MigrationConstraintTrait;

    /**
     * @var RenameExtension
     */
    private $renameExtension;

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->renameCustomerUserSidebarWidget($schema, $queries);
        $this->renameAccountUserSidebarState($schema, $queries);
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    private function renameCustomerUserSidebarWidget(Schema $schema, QueryBag $queries)
    {

        $table = $schema->getTable("oro_account_user_sdbar_wdg");

        $table->dropIndex("oro_acc_sdbr_wdgs_usr_place_idx");
        $table->dropIndex("oro_acc_sdar_wdgs_pos_idx");

        $fk = $this->getConstraintName($table, 'account_user_id');
        $table->removeForeignKey($fk);
        $this->renameExtension->renameColumn($schema, $queries, $table, "account_user_id", "customer_user_id");

        $this->renameExtension->renameTable(
            $schema,
            $queries,
            "oro_account_user_sdbar_wdg",
            "oro_customer_user_sdbar_wdg"
        );
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    private function renameAccountUserSidebarState(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable("oro_account_user_sdbar_st");

        $table->dropIndex("oro_acc_sdbar_st_unq_idx");

        $fk = $this->getConstraintName($table, 'account_user_id');
        $table->removeForeignKey($fk);
        $this->renameExtension->renameColumn($schema, $queries, $table, "account_user_id", "customer_user_id");

        $this->renameExtension->renameTable(
            $schema,
            $queries,
            "oro_account_user_sdbar_st",
            "oro_customer_user_sdbar_st"
        );
    }

    /**
     * Sets the RenameExtension
     *
     * @param RenameExtension $renameExtension
     */
    public function setRenameExtension(RenameExtension $renameExtension)
    {
        $this->renameExtension = $renameExtension;
    }

    /**
     * Get the order of this migration
     *
     * @return integer
     */
    public function getOrder()
    {
        return 1;
    }
}
