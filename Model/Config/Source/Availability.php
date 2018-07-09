<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Availability implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $options = [];
        $options[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::AVAILABILITY_HIDE,
            'label' => __('Hide'),
        ];
        $options[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::AVAILABILITY_ONLY_AVAILABLE,
            'label' => __('Show only when available'),
        ];
        $options[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::AVAILABILITY_ALWAYS,
            'label' => __('Always show'),
        ];

        return $options;
    }
}
