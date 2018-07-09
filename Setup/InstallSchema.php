<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();

        $tables = [
            $setup->getTable('quote'),
            $setup->getTable('sales_order'),
        ];
        foreach ($tables as $tableName) {
            $connection->addColumn(
                $tableName,
                'pb_leave_doorstep',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'default' => 0,
                    'nullable' => false,
                    'comment' => 'Porterbuddy leave at doorstep allowed',
                ]
            );
            $connection->addColumn(
                $tableName,
                'pb_comment',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 512,
                    'nullable' => true,
                    'comment' => 'Porterbuddy comment',
                ]
            );
            $connection->addColumn(
                $tableName,
                'pb_timeslot_selection',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 32,
                    'default' => 'checkout',
                    'nullable' => false,
                    'comment' => 'Porterbuddy timeslot selection',
                ]
            );
        }

        $tableName = $setup->getTable('sales_order');
        $connection->addColumn(
            $tableName,
            'pb_shipment_creating_by',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 32,
                'nullable' => true,
                'comment' => 'Porterbuddy shipment is creating by',
            ]
        );
        $connection->addColumn(
            $tableName,
            'pb_paid_at',
            [
                'type' => Table::TYPE_TIMESTAMP,
                'nullable' => true,
                'comment' => 'Paid At',
            ]
        );
        $connection->addColumn(
            $tableName,
            'pb_autocreate_status',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 32,
                'nullable' => true,
                'comment' => 'Porterbuddy auto-create status',
            ]
        );

        // Porterbuddy method name can take up to ~100 characters. In Magento 2.2 column length is 120 characters,
        // Magento < 2.2 - quote_address.shipping_method 40 -> 120, sales_order.shipping_method 32 -> 120
        $tables = [
            $setup->getTable('quote_address'),
            $setup->getTable('sales_order'),
        ];
        foreach ($tables as $tableName) {
            $info = $connection->describeTable($tableName);
            if (isset($info['shipping_method']['LENGTH']) && $info['shipping_method']['LENGTH'] < 120) {
                $connection->modifyColumn(
                    $tableName,
                    'shipping_method',
                    [
                        'type' => Table::TYPE_TEXT,
                        'length' => 120
                    ]
                );
            }
        }

        $setup->endSetup();
    }
}
