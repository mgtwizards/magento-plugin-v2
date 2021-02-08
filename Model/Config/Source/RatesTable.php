<?php

namespace Porterbuddy\Porterbuddy\Model\Config\Source;

use \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use \Magento\Framework\DataObject;

class RatesTable extends AbstractFieldArray
{
    protected $rateType;

    protected function _prepareToRender()
    {

        $this->addColumn('carrier_code', array(
            'label' => __('CarrierCode'),
            'size' => '50px'
        ));
        $this->addColumn('rate_code', array(
            'label' => __('RateCode'),
            'size' => '50px'
        ));
        $this->addColumn('rate_type', array(
            'label' => __('Rate Type'),
            'renderer' => $this->getRateType(),
        ));
        $this->addColumn('min_delivery_days', array(
            'label' => __('Min Delivery Days'),
        ));
        $this->addColumn('max_delivery_days', array(
            'label' => __('Max Delivery Days (optional)'),
        ));
        $this->addColumn('logo_url', array(
            'label' => __('URL for carrier logo'),
        ));
        $this->addColumn('description', array(
            'label' => __('Description Text'),
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');

    }

    /**
     * @return \Porterbuddy\Porterbuddy\Model\Config\Source\RateType
     */
    protected function getRateType()
    {
        if (!$this->rateType) {
            $this->rateType = $this->getLayout()->createBlock(
                RateType::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->rateType;
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(DataObject $row)
    {

        $rateType = $row->getRateType();
        $options = [];
        if ($rateType) {
            $options['option_' . $this->getRateType()->calcOptionHash($rateType)]
                = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);


    }
}