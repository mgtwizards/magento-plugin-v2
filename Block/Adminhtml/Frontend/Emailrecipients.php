<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block\Adminhtml\Frontend;

class Emailrecipients extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    public function _prepareToRender()
    {
        $this->addColumn('email', [
            'label' => __('Email'),
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}
