<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Shipping\Model\CarrierFactory;
use Porterbuddy\Porterbuddy\Helper\Data;
use Porterbuddy\Porterbuddy\Model\Carrier;

class Info extends \Magento\Backend\Block\Template
{
    protected $_template = 'Porterbuddy_Porterbuddy::info.phtml';

    /**
     * @var CarrierFactory
     */
    protected $carrierFactory;

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(
        CarrierFactory $carrierFactory,
        Data $helper,
        Context $context,
        array $data = []
    ) {
        $this->carrierFactory = $carrierFactory;
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    public function setOrder(\Magento\Sales\Model\Order $order)
    {
        return $this->setData('order', $order);
    }

    /**
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrder()
    {
        return $this->getData('order');
    }

    protected function _toHtml()
    {
        if ($this->getOrder() && $this->getOrder()->getIsVirtual()) {
            return '';
        }
        $carrier = $this->carrierFactory->create(
            $this->getOrder()->getShippingMethod(true)->getCarrierCode()
        );
        if (!$carrier instanceof Carrier) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * @return bool
     */
    public function canEditOptions()
    {
        $isOrderView = in_array('sales_order_view', $this->getLayout()->getUpdate()->getHandles());
        $shipmentExists = count($this->getOrder()->getShipmentsCollection());

        return $isOrderView && !$shipmentExists;
    }

    /**
     * @return string
     */
    public function getLeaveDoorstepText()
    {
        return $this->helper->getLeaveDoorstepText();
    }

    /**
     * @return bool
     */
    public function getLeaveDoorstep()
    {
        return (bool)$this->getOrder()->getPbLeaveDoorstep();
    }

    /**
     * @return string
     */
    public function getCommentText()
    {
        return $this->helper->getCommentText();
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->getOrder()->getPbComment();
    }

    public function truncate($text, $length)
    {
        return $this->filterManager->truncate($text, ['length' => $length, 'etc' => '']);
    }
}
