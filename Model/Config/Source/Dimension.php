<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Dimension implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $dimensionUnits = [];
        $dimensionUnits[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::UNIT_MILLIMETER,
            'label' => __('Millimeter'),
        ];
        $dimensionUnits[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::UNIT_CENTIMETER,
            'label' => __('Centimeter'),
        ];

        return $dimensionUnits;
    }
}
