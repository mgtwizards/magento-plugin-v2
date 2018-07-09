<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Location implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray($isMultiselect = false)
    {
        $options = [];
        $options[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::LOCATION_BROWSER,
            'label' => __('Browser location API lookup'),
        ];
        $options[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::LOCATION_IP,
            'label' => __('IP location lookup'),
        ];

        if (!$isMultiselect) {
            array_unshift($options, ['value'=>'', 'label'=> __('--Please Select--')]);
        }

        return $options;
    }
}
