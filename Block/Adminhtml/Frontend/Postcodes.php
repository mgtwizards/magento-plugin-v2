<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block\Adminhtml\Frontend;

class Postcodes extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \Porterbuddy\Porterbuddy\Model\Availability
     */
    protected $availability;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    /**
     * @param \Porterbuddy\Porterbuddy\Model\Availability $availability
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Model\Availability $availability,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        $this->availability = $availability;
        $this->localeDate = $localeDate;
        parent::__construct($context, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $updated = $this->availability->getPostcodesUpdated();

        $comment = __('Last Update') . ': '
            . ($updated ? $this->localeDate->formatDateTime($updated, \IntlDateFormatter::MEDIUM) : __('Never'));

        $element->setComment($comment);

        return parent::render($element);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->setStyle('width:70px;');
        $element->setValue(__('Update'));

        $url = $this->getUrl('porterbuddy/postcodes/update');
        $element->setOnclick("location.href = '$url'; this.disabled = true");

        return $element->getElementHtml();
    }
}
