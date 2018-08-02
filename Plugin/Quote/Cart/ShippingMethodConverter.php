<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Plugin\Quote\Cart;

use Porterbuddy\Porterbuddy\Model\Carrier;
use Magento\Quote\Api\Data\ShippingMethodInterface;

class ShippingMethodConverter
{
    /**
     * @var \Magento\Framework\Api\ExtensionAttributesFactory
     */
    protected $extensionAttributesFactory;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    /**
     * @var \Porterbuddy\Porterbuddy\Model\Timeslots
     */
    protected $timeslots;

    /**
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Porterbuddy\Porterbuddy\Model\Timeslots $timeslots
     */
    public function __construct(
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionAttributesFactory,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Porterbuddy\Porterbuddy\Model\Timeslots $timeslots
    ) {
        $this->extensionAttributesFactory = $extensionAttributesFactory;
        $this->helper = $helper;
        $this->localeDate = $localeDate;
        $this->timeslots = $timeslots;
    }

    /**
     * Parses Porterbuddy rate codes and adds additional information for frontend rendering
     *
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $subject
     * @param \Closure $proceed
     * @param \Magento\Quote\Model\Quote\Address\Rate $rateModel
     * @param string $quoteCurrencyCode
     * @return ShippingMethodInterface
     *
     * @see \Magento\Quote\Model\Cart\ShippingMethodConverter::modelToDataObject
     */
    public function aroundModelToDataObject(
        \Magento\Quote\Model\Cart\ShippingMethodConverter $subject,
        \Closure $proceed,
        $rateModel,
        $quoteCurrencyCode
    ) {
        /** @var ShippingMethodInterface $rateDataObject */
        $rateDataObject = $proceed($rateModel, $quoteCurrencyCode);

        $extensionAttributes = $rateDataObject->getExtensionAttributes();
        if (!$extensionAttributes) {
            /** @var \Magento\Quote\Api\Data\ShippingMethodExtensionInterface $extensionAttributes */
            $extensionAttributes = $this->extensionAttributesFactory->create(ShippingMethodInterface::class);
        }

        if (Carrier::CODE == $rateDataObject->getCarrierCode()) {
            $methodInfo = $this->helper->parseMethod($rateDataObject->getMethodCode());
            $startTime = new \DateTime($methodInfo->getStart());

            $label = $this->timeslots->formatTimeslot($methodInfo, false);

            if ($this->helper->getShowTimeslots()) {
                $dateKey = $startTime->format('Y-m-d');
                $dateLabel = $this->localeDate->formatDate($startTime, \IntlDateFormatter::FULL);
                $dateLabel = preg_replace('/\s+\d+$/', '', $dateLabel); // remove year
                $dateLabel = rtrim($dateLabel, ', ');
                if (Carrier::METHOD_EXPRESS == $methodInfo->getType()) {
                    $label = $this->helper->getAsapName();
                }
            } else {
                $dateKey = 'delivery-type';
                $dateLabel = __('Select later');
                if (Carrier::METHOD_DELIVERY == $methodInfo->getType()) {
                    $label = $rateModel->getMethodTitle();
                }
            }

            $methodInfo->setDateKey($dateKey);
            $methodInfo->setDateLabel($dateLabel);
            $methodInfo->setLabel($label);
            $extensionAttributes->setPorterbuddyInfo($methodInfo);
        }

        $rateDataObject->setExtensionAttributes($extensionAttributes);

        return $rateDataObject;
    }
}
