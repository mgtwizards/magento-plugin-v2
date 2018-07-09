<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Packager;

interface PackagerInterface
{
    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array
     * @throws \Porterbuddy\Porterbuddy\Exception
     */
    public function estimateParcels(\Magento\Quote\Model\Quote\Address\RateRequest $request);

    /**
     * Creates packages automatically
     *
     * @param \Magento\Shipping\Model\Shipment\Request $request
     * @return array
     * @throws \Porterbuddy\Porterbuddy\Exception
     */
    public function createPackages(\Magento\Shipping\Model\Shipment\Request $request);
}
