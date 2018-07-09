<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Porterbuddy\Porterbuddy\Model\Carrier;

class PaymentPlaceAfterCheckPaid implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Shipping\Model\CarrierFactory
     */
    protected $carrierFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Shipping\Model\CarrierFactory $carrierFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->carrierFactory = $carrierFactory;
        $this->dateTime = $dateTime;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    /**
     * Detects if order has been paid for payment methods that pay right when order is created
     *
     * E.g. PayPal Express, Klarna Checkout
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getPayment();
        $order = $payment->getOrder();

        $carrier = $this->carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());

        if (!$carrier instanceof Carrier) {
            return false;
        }
        if ($order->getPbPaidAt()) {
            return true;
        }

        $paid = false;
        if (\Magento\Sales\Model\Order::STATE_PROCESSING == $order->getState()) {
            $paid = true;
        }

        $transport = new DataObject(['paid' => $paid]);
        $this->eventManager->dispatch('convert_porterbuddy_payment_place_is_paid', [
            'payment' => $payment,
            'transport' => $transport,
        ]);
        $paid = $transport->getData('paid');

        if ($paid) {
            $this->logger->notice(
                'Payment detected - order place after.',
                ['order_id' => $order->getId()]
            );
            $order->setPbPaidAt($this->dateTime->gmtDate());
        }

        return (bool)$paid;
    }
}
