<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->helper = $helper;
        $this->priceCurrency = $priceCurrency;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        /** @var \Magento\Quote\Model\Quote  */
        $quote = $this->session->getQuote();

        return [
            'porterbuddy' => [
                'title' => $this->helper->getTitle(),
                'description' => $this->helper->getDescription(),
                'preselectLocation' => $this->helper->isPreselectLocation(),
                'leaveDoorstepEnabled' => $this->helper->isLeaveDoorstepEnabled(),
                'leaveDoorstepText' => $this->helper->getLeaveDoorstepText(),
                'returnEnabled' => $this->helper->getReturnEnabled(),
                'returnText' => $this->helper->getReturnText(),
                'commentText' => $this->helper->getCommentText(),
                'showTimeslots' => $this->helper->getShowTimeslots(),
                'refreshOptionsTimeout' => $this->helper->getRefreshOptionsTimeout(),
                'comment' => $quote->getPbComment(),
                'leave_doorstep' => (bool)$quote->getPbLeaveDoorstep(),
            ],
        ];
    }
}
