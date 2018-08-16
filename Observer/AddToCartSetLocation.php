<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Porterbuddy\Porterbuddy\Model\Carrier;

class AddToCartSetLocation implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->eventManager = $eventManager;
        $this->request = $request;
    }

    /**
     * Preselect shipping address city and postcode from Porterbuddy location
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

        $location = @json_decode($location, true);

        if (!$location || !$this->shouldSetLocation($location)) {
            return;
        }

        if (!$shippingAddress->getCity() && !empty($location['city'])) {
            $shippingAddress->setCity($location['city']);
        }
        if (!$shippingAddress->getPostcode() && !empty($location['postcode'])) {
            $shippingAddress->setPostcode($location['postcode']);
        }
    }

    /**
     * @param mixed $location
     * @return bool
     */
    public function shouldSetLocation($location)
    {
        if (!empty($location['source'])) {
            $result = Carrier::SOURCE_IP !== $location['source'];
        } else {
            $result = true;
        }

        $transport = new DataObject(array('result' => $result));
        $this->eventManager->dispatch('porterbuddy_should_set_shipping_location', array(
            'location' => $location,
            'transport' => $transport,
        ));
        $result = $transport->getData('result');

        return $result;
    }
}
