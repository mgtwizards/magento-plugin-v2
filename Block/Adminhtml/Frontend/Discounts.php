<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block\Adminhtml\Frontend;

class Discounts extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    public function _prepareToRender()
    {
        $this->addColumn('discount', [
            'label' => __('discount'),
        ]);
        $this->addColumn('minimumbasket', [
            'label' => __('minimumbasket'),
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}
