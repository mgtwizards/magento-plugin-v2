<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block;

use Magento\Catalog\Model\Product;
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

    public function __construct(
        \Magento\Directory\Helper\Data $directoryHelper,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        $this->directoryHelper = $directoryHelper;
        $this->helper = $helper;
        $this->registry = $registry;
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
        if (!$product->isSalable() || $product->isVirtual()) {
            return '';
        }

        return parent::_toHtml();
    }
}
