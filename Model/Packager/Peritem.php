<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Packager;

use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Porterbuddy\Porterbuddy\Model\Carrier;

class Peritem implements PackagerInterface
{
    const MODE = 'per_item';

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->eventManager = $eventManager;
        $this->helper = $helper;
    }

    public function estimateParcels(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $parcels = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($request->getAllItems() as $item) {
            if (!$this->canShip($item)) {
                continue;
            }
            $product = $item->getProduct();
            $dimensions = $this->getDimensions($product);

            $qty = $item->getQty();
            if ($item->getParentItem() && $item->getParentItem()->getQty() > 0) {
                $qty *= $item->getParentItem()->getQty();
            }

            $weight = $this->helper->convertWeightToGrams(
                $item->getWeight() ?: $this->helper->getDefaultProductWeight()
            );
            for ($i = 0; $i < $qty; $i++) {
                $parcels[] = [
                    'description' => $item->getName(),
                    'widthCm' => $dimensions['width'],
                    'heightCm' => $dimensions['height'],
                    'depthCm' => $dimensions['length'],
                    'weightGrams' => $weight,
                ];
            }
        }

        $transport = new DataObject(['parcels' => $parcels]);
        $this->eventManager->dispatch('porterbuddy_estimate_parcels_per_item', [
            'transport' => $transport,
            'request' => $request,
        ]);
        $parcels = $transport->getData('parcels');

        return $parcels;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return bool
     *
     * @see \Mage_Sales_Model_Service_Order::_canShipItem
     * @see \Mage_Sales_Model_Order_Item::isDummy
     */
    protected function canShip(\Magento\Quote\Model\Quote\Item $item)
    {
        $result = true;

        if ($item->getIsVirtual()) {
            $result = false;
        }

        $hasChildren = count($item->getChildren()) > 0;
        $isShipSeparately = $item->isShipSeparately();
        $hasParent = (bool)$item->getParentItem();

        // TODO: more beautiful and extensible
        if (Type::TYPE_BUNDLE == $item->getProductType()) {
            // always use children
            $result = false;
        }

        if ($hasChildren && $isShipSeparately) {
            // ship separately - skip parent, ship children
            $result = false;
        }

        // TODO: more beautiful and extensible
        if ($hasParent && !$isShipSeparately && Type::TYPE_BUNDLE !== $item->getParentItem()->getProductType()) {
            // Magento default - ship parent, skip children
            $result = false;
        }

        $transport = new DataObject(['result' => $result]);
        $this->eventManager->dispatch('porterbuddy_estimate_parcels_per_item_can_ship', [
            'transport' => $transport,
            'quote_item' => $item,
        ]);
        $result = $transport->getData('result');

        return $result;
    }

    /**
     * Creates single big package out of all items
     *
     * {@inheritdoc}
     */
    public function createPackages(\Magento\Shipping\Model\Shipment\Request $request)
    {
        $packages = [];
        /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
        foreach ($request->getOrderShipment()->getAllItems() as $item) {
            $product = $item->getOrderItem()->getProduct();

            $parent = $item->getOrderItem()->getParentItem();
            if ($parent && Configurable::TYPE_CODE == $parent->getProductType()) {
                // skip child of configurable, all info in parent
                continue;
            }
            if (Type::TYPE_BUNDLE == $item->getOrderItem()->getProductType()) {
                // skip parent bundle item, bundle children items are following
                continue;
            }

            $dimensions = $this->getDimensions($product);

            $weight = $this->helper->convertWeightToGrams(
                $item->getWeight() ?: $this->helper->getDefaultProductWeight()
            );
            $package = [
                'params' => [
                    'container' => '',
                    'weight' => $weight,
                    'weight_unit' => Carrier::WEIGHT_GRAM,
                    'customs_value' => $item->getPrice(),
                    'length' => $dimensions['length'],
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'dimension_units' => Carrier::UNIT_CENTIMETER,
                    // send description via standard fields
                    'content_type' => 'OTHER',
                    'content_type_other' => $item->getName(),
                ],
                'customs_value' => $item->getPrice(),
                'items' => [
                    $item->getOrderItemId() => [
                        'qty' => $item->getQty(),
                        'customs_value' => $item->getPrice(),
                        'price' => $item->getPrice(),
                        'name' => $item->getName(),
                        'weight' => $item->getWeight(),
                        'product_id' => $item->getProductId(),
                        'order_item_id' => $item->getOrderItemId(),
                    ]
                ],
            ];

            $qty = $item->getQty();
            for ($i = 0; $i < $qty; $i++) {
                $packages[] = $package;
            }
        }

        $transport = new DataObject(['packages' => $packages]);
        $this->eventManager->dispatch('porterbuddy_prepare_create_packages_per_item', [
            'transport' => $transport,
            'request' => $request,
        ]);
        $packages = $transport->getData('packages');

        return $packages;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param bool $useDefaults
     * @return array [width, length, height]
     */
    public function getDimensions(\Magento\Catalog\Model\Product $product, $useDefaults = true)
    {
        $definedDimensions = [
            'width' => $this->getAttributeValue($product, $this->helper->getWidthAttribute()),
            'length' => $this->getAttributeValue($product, $this->helper->getLengthAttribute()),
            'height' => $this->getAttributeValue($product, $this->helper->getHeightAttribute()),
        ];

        $definedDimensions = array_filter($definedDimensions);

        if ($useDefaults) {
            $definedDimensions += [
                'width' => $this->helper->getDefaultProductWidth(),
                'length' => $this->helper->getDefaultProductLength(),
                'height' => $this->helper->getDefaultProductHeight(),
            ];
        }

        return $definedDimensions;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attribute
     * @return bool|string
     */
    protected function getAttributeValue(\Magento\Catalog\Model\Product $product, $attribute)
    {
        if ($attribute
            && $product->hasData($attribute)
            && strlen($product->getData($attribute))
            && is_numeric($product->getData($attribute))
        ) {
            return $product->getData($attribute);
        } else {
            return false;
        }
    }
}
