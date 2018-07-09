<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $modes = [];
        $modes[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::MODE_DEVELOPMENT,
            'label' => __('Development'),
        ];
        $modes[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::MODE_TESTING,
            'label' => __('Testing'),
        ];
        $modes[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::MODE_PRODUCTION,
            'label' => __('Production'),
        ];

        return $modes;
    }
}
