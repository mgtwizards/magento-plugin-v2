<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Timeslot implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $result = [];
        $result[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::TIMESLOT_CHECKOUT,
            'label' => __('In checkout'),
        ];
        $result[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::TIMESLOT_CONFIRMATION,
            'label' => __('On confirmation page'),
        ];

        return $result;
    }
}
