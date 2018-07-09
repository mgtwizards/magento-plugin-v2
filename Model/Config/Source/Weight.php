<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Weight implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $weightUnits = [];
        $weightUnits[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::WEIGHT_GRAM,
            'label' => __('Gram'),
        ];
        $weightUnits[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::WEIGHT_KILOGRAM,
            'label' => __('Kilogram'),
        ];

        return $weightUnits;
    }
}
