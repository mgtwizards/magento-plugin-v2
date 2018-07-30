<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Setup;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DB\DataConverter\SerializedToJson;
use Magento\Framework\DB\FieldDataConverterFactory;
use Magento\Framework\DB\Select\QueryModifierFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class Recurring implements \Magento\Framework\Setup\InstallSchemaInterface
{
    /**
     * @var FieldDataConverterFactory
     */
    protected $fieldDataConverterFactory;

    /**
     * @var QueryModifierFactory
     */
    protected $queryModifierFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var string
     */
    protected $convertedPath = 'carriers/porterbuddy/serialized_to_json_converted';

    /**
     * @var array
     */
    protected $serializedPaths = [
        'carriers/porterbuddy/error_email_recipients',
        'carriers/porterbuddy/error_email_recipients_porterbuddy',
    ];

    /**
     * @param ObjectManagerInterface $objectManager
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ProductMetadataInterface $productMetadata
    ) {
        $this->productMetadata = $productMetadata;

        if (version_compare($this->productMetadata->getVersion(), '2.2.0', '>=')) {
            $this->fieldDataConverterFactory = $objectManager->get(FieldDataConverterFactory::class);
            $this->queryModifierFactory = $objectManager->get(QueryModifierFactory::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($this->productMetadata->getVersion(), '2.2.0', '>=')
            && !$this->isConvertedDataSerializedToJson($setup)
        ) {
            $this->convertDataSerializedToJson($setup);
            $this->setConvertedDataSerializedToJson($setup);
        }

        $setup->endSetup();
    }

    /**
     * Reads a flag whether module configuration has once been converted
     *
     * @param SchemaSetupInterface $setup
     * @return bool
     */
    protected function isConvertedDataSerializedToJson(SchemaSetupInterface $setup)
    {
        $select = $setup->getConnection()->select()
            ->from($setup->getTable('core_config_data'), 'value')
            ->where('path = ?', $this->convertedPath);
        $result = $setup->getConnection()->fetchOne($select);
        return (bool)$result;
    }

    /**
     * Sets a flag after configuration has been converted
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    protected function setConvertedDataSerializedToJson(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->insert(
            $setup->getTable('core_config_data'),
            [
                'path' => $this->convertedPath,
                'value' => 1,
            ]
        );
    }

    /**
     * Converts module's serialized fields to JSON
     *
     * @param SchemaSetupInterface $setup
     * @throws \Magento\Framework\DB\FieldDataConversionException
     */
    protected function convertDataSerializedToJson(SchemaSetupInterface $setup)
    {
        $queryModifier = $this->queryModifierFactory->create(
            'in',
            [
                'values' => [
                    'path' => $this->serializedPaths,
                ]
            ]
        );
        $fieldDataConverter = $this->fieldDataConverterFactory->create(SerializedToJson::class);
        $fieldDataConverter->convert(
            $setup->getConnection(),
            $setup->getTable('core_config_data'),
            'config_id',
            'value',
            $queryModifier
        );
    }
}
