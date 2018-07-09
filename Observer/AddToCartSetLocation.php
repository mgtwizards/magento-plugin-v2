<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Observer;

use Magento\Framework\Event\Observer;
use Porterbuddy\Porterbuddy\Model\Carrier;

class AddToCartSetLocation implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(\Magento\Framework\App\RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Preselect shipping address postcode from Porterbuddy location
     *
     * {@inheritdoc}
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Checkout\Model\Cart $cart */
        $cart = $observer->getCart();
        $shippingAddress = $cart->getQuote()->getShippingAddress();

        $location = $this->request->getCookie(Carrier::COOKIE, null);
        if (!$location) {
            return;
        }

        $location = json_decode($location, true);

        if (!$shippingAddress->getCity() && !empty($location['city'])) {
            $shippingAddress->setCity($location['city']);
        }
        if (!$shippingAddress->getPostcode() && !empty($location['postcode'])) {
            $shippingAddress->setPostcode($location['postcode']);
        }
    }
}
