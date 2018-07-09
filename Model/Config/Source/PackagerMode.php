<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class PackagerMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Porterbuddy\Porterbuddy\Model\Packager
     */
    protected $packager;

    /**
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper optional
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Model\Packager $packager
    ) {

        $this->packager = $packager;
    }

    public function toOptionArray()
    {
        $options = [];

        foreach ($this->packager->getModes() as $code => $config) {
            $options[] = [
                'value' => $code,
                'label' => __($config['label']),
            ];
        }

        return $options;
    }
}
