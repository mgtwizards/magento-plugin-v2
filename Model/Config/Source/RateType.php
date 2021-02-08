<?php
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

use Magento\Framework\View\Element\Html\Select;
use Porterbuddy\Porterbuddy\Model\Carrier;

class RateType extends Select
{
    /**
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        if (!$this->getOptions()) {

            $options = [
                [
                    'value' => Carrier::RATE_TYPE_HOME,
                    'label' => __('Home Delivery'),
                ],
                [
                    'value' => Carrier::RATE_TYPE_PICKUP_POINT,
                    'label' => __('Pickup Point'),

                ],
                [
                    'value' => Carrier::RATE_TYPE_IN_STORE,
                    'label' => __('Collect in Store'),
                ]
            ];
            $this->setOptions($options);
        }

        return parent::_toHtml();
    }

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
}