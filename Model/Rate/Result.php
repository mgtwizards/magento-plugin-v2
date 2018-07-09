<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Rate;

class Result extends \Magento\Shipping\Model\Rate\Result
{
    /**
     * {@inheritdoc}
     */
    public function sortRatesByPrice()
    {
        if (!is_array($this->_rates) || !count($this->_rates)) {
            return $this;
        }

        // don't reorder rates, keep Express first and then delivery as is
        return $this;
    }
}
