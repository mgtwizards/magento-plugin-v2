<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */

namespace Porterbuddy\Porterbuddy\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;


class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup,
                            ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            $connection = $setup->getConnection();
            $tables = [
                $setup->getTable('quote'),
                $setup->getTable('sales_order'),
            ];
            foreach ($tables as $tableName) {
                $connection->addColumn(
                    $tableName,
                    'pb_token',
                    [
                        'type' => Table::TYPE_TEXT,
                        'default' => '',
                        'nullable' => false,
                        'comment' => 'PB Token for chosen delivery window',
                    ]
                );
            }
        }
        $setup->endSetup();
    }
}