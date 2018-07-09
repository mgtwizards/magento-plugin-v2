<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Plugin\Shipping\Block\Adminhtml;

use Porterbuddy\Porterbuddy\Model\Carrier;

class Packing
{
    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     */
    public function __construct(\Porterbuddy\Porterbuddy\Helper\Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Updates admin packaging popup template
     *
     * - add weight in grams
     *
     * @param \Magento\Shipping\Block\Adminhtml\Order\Packaging $block
     * @param $html
     * @return string
     */
    public function afterToHtml(
        \Magento\Shipping\Block\Adminhtml\Order\Packaging $block,
        $html
    ) {
        // add gram weight unit
        $html = $this->updateWeightUnits($html);

        return $html;
    }

    /**
     * @param string $html
     * @return string
     */
    protected function updateWeightUnits($html)
    {
        $start = strpos($html, '<select name="container_weight_units"');
        if (false === $start) {
            return $html;
        }
        $start = strpos($html, '<option', $start);
        if (false === $start) {
            return $html;
        }

        $end = strpos($html, '</select>', $start);
        if (false === $end) {
            return $html;
        }

        $options = substr($html, $start, $end-$start);

        // prepend grams option
        $option = sprintf(
            "<option value=\"%s\">%s</option>\n",
            Carrier::WEIGHT_GRAM,
            mb_strtolower(__('Gram'))
        );
        $options = $option . $options;

        // remove default pounds selection
        $options = str_replace(' selected="selected"', '', $options);

        // set selected gram/kg depending on config
        $weightUnit = $this->helper->getWeightUnit();
        $options = str_replace(
            sprintf('value="%s"', $weightUnit),
            sprintf('value="%s" selected="selected"', $weightUnit),
            $options
        );

        // replace options html
        $html = substr($html, 0, $start) . $options . substr($html, $start + strlen($options));

        return $html;
    }
}
