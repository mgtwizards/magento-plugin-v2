<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Porterbuddy\Porterbuddy\Model\InventoryApi\GetProductSalableQtyInstance as GetProductSalableQtyInterface;
use Porterbuddy\Porterbuddy\Model\InventoryApi\IsProductSalableInstance as IsProductSalableInterface;
use Porterbuddy\Porterbuddy\Model\Carrier;

class Availability extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Directory\Helper\Data
     */
    protected $directoryHelper;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var GetProductSalableQtyInterface
     */
    protected $getProductSalableQty;

    /**
     * @var IsProductSalableInterface
     */
    protected $isProductSalable;


    public function __construct(
        \Magento\Directory\Helper\Data $directoryHelper,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Registry $registry,
        GetProductSalableQtyInterface $getProductSalableQty,
        IsProductSalableInterface $isProductSalable,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        $this->directoryHelper = $directoryHelper;
        $this->helper = $helper;
        $this->registry = $registry;
        $this->getProductSalableQty = $getProductSalableQty->get();
        $this->isProductSalable = $isProductSalable->get();
        parent::__construct($context, $data);
    }

    /**
     * @return \Porterbuddy\Porterbuddy\Helper\Data
     */
    public function getHelper()
    {
        return $this->helper;
    }

    public function getDefaultCountry()
    {
        return $this->directoryHelper->getDefaultCountry();
    }

    /**
     * Retrieve currently viewed product object
     *
     * @return Product
     */
    public function getProduct()
    {
        if (!$this->hasData('product')) {
            $this->setData('product', $this->registry->registry('product'));
        }
        return $this->getData('product');
    }

    /**
     * @return bool
     */
    public function isAlwaysShow()
    {
        return Carrier::AVAILABILITY_ALWAYS == $this->helper->showAvailability();
    }

    /**
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        if (!$this->helper->getActive()
            || Carrier::AVAILABILITY_HIDE == $this->helper->showAvailability()
        ) {
            return '';
        }
        $product = $this->getProduct();

        if ($this->getProductSalableQty && $this->isProductSalable) {
            if ($this->helper->getInventoryStock() != null) {
                if ($product->getTypeId() === 'simple') {
                    $qtyInStock = $this->getProductSalableQty->execute($product->getSku(), $this->helper->getInventoryStock());
                    if (1 > $qtyInStock) {
                        return '';
                    }
                } else {
                    if (!$this->isProductSalable->execute($product->getSku(), $this->helper->getInventoryStock())) {
                        return '';
                    }
                }
            }
        }

        if (!$product->isSalable() || $product->isVirtual()) {
            return parent::_toHtml();
        }

        return parent::_toHtml();
    }
}
