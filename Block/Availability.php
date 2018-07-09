<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Block;

use Porterbuddy\Porterbuddy\Model\Carrier;

class Availability extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Directory\Helper\Data
     */
    protected $directoryHelper;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    public function __construct(
        \Magento\Directory\Helper\Data $directoryHelper,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        $this->directoryHelper = $directoryHelper;
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * @return \Porterbuddy\Porterbuddy\Helper\Data
     */
    public function getHelper()
    {
        return $this->helper;
    }

    public function getDefaultCountry()
    {
        return $this->directoryHelper->getDefaultCountry();
    }


    /**
     * @return bool
     */
    public function isAlwaysShow()
    {
        return Carrier::AVAILABILITY_ALWAYS == $this->helper->showAvailability();
    }
}
