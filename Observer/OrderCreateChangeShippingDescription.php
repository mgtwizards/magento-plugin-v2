<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Observer;

class OrderCreateChangeShippingDescription implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Shipping\Model\CarrierFactory
     */
    protected $carrierFactory;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    /**
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     */
    public function __construct(
        \Magento\Shipping\Model\CarrierFactory $carrierFactory,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
    ) {
        $this->carrierFactory = $carrierFactory;
        $this->helper = $helper;
        $this->localeDate = $localeDate;
    }

    /**
     * {@inheritdoc}
     *
     * After creating order update shipping description - include full date
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();

        $carrier = $this->carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());

        if (!$carrier instanceof \Porterbuddy\Porterbuddy\Model\Carrier) {
            return;
        }

        $methodInfo = $this->helper->parseMethod($order->getShippingMethod());
        if (!$methodInfo->getStart()) {
            return;
        }

        // we only need date, but still respect timezone for border cases
        $timezone = $this->helper->getTimezone();
        $start = new \DateTime($methodInfo->getStart());
        $start->setTimezone($timezone);
        $date = $this->localeDate->formatDate($start, \IntlDateFormatter::SHORT);

        $order->setShippingDescription($order->getShippingDescription() . " ($date)");
    }
}
