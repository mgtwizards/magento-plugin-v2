<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class DiscountType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $types = [];
        $types[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::DISCOUNT_TYPE_NONE,
            'label' => __('None'),
        ];
        $types[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::DISCOUNT_TYPE_FIXED,
            'label' => __('Fixed'),
        ];
        $types[] = [
            'value' => \Porterbuddy\Porterbuddy\Model\Carrier::DISCOUNT_TYPE_PERCENT,
            'label' => __('Percent'),
        ];

        return $types;
    }
}
