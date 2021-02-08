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
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;


    /**
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->helper = $helper;
        $this->priceCurrency = $priceCurrency;
        $this->session = $session;
        $this->urlBuilder = $urlBuilder;
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
                'checkoutWidgetTitle' => $this->helper->getCheckoutWidgetTitle(),
                'enterPostcodeText' => $this->helper->getEnterPostcodeText(),
                'homeDeliveryTitle' => $this->helper->getHomeDeliveryTitle(),
                'pickupPointTitle' => $this->helper->getPickupPointTitle(),
                'collectInStoreTitle' => $this->helper->getCollectInStoreTitle(),
                'shippingRates' => $this->helper->getRates(),
                'preselectLocation' => $this->helper->isPreselectLocation(),
                'leaveDoorstepEnabled' => $this->helper->isLeaveDoorstepEnabled(),
                'leaveDoorstepText' => $this->helper->getLeaveDoorstepText(),
                'commentText' => $this->helper->getCommentText(),
                'refreshOptionsTimeout' => $this->helper->getRefreshOptionsTimeout(),
                'comment' => $quote->getPbComment(),
                'leave_doorstep' => (bool)$quote->getPbLeaveDoorstep(),
                'apiMode' => $this->helper->getApiMode(),
                'publicKey' => $this->helper->getPublicKey(),
                'discount' => $this->session->getPbDiscount(),
                'addressWarningText' => $this->helper->getAddressWarningText(),
                'addressWarningCloseLabel' => $this->helper->getAddressWarningCloseLabel(),
                'addressWarningTitle' => $this->helper->getAddressWarningTitle()
            ],
        ];
    }
}
