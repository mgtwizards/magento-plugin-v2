<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Hours implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 15) {
                $value = sprintf('%02d:%02d', $hour, $minute);
                $options[] = [
                    'value' => $value,
                    'label' => $value,
                ];
            }
        }

        // final brush
        $value = '24:00';
        $options[] = [
            'value' => $value,
            'label' => $value,
        ];

        return $options;
    }
}
