<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    /**
     * {@inheritdoc}
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $uninstaller = $setup;

        $tables = [
            $setup->getTable('quote'),
            $setup->getTable('sales_order'),
        ];
        foreach ($tables as $tableName) {
            $uninstaller->getConnection()->dropColumn($tableName, 'pb_leave_doorstep');
            $uninstaller->getConnection()->dropColumn($tableName, 'pb_comment');
            $uninstaller->getConnection()->dropColumn($tableName, 'pb_timeslot_selection');
        }

        $tableName = $setup->getTable('sales_order');
        $uninstaller->getConnection()->dropColumn($tableName, 'pb_shipment_creating_by');
        $uninstaller->getConnection()->dropColumn($tableName, 'pb_paid_at');
        $uninstaller->getConnection()->dropColumn($tableName, 'pb_autocreate_status');
    }
}
