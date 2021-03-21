<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface;
use Magento\Sales\Model\Order\Shipment\Validation\QuantityValidator;
use Porterbuddy\Porterbuddy\Exception;

class Shipment
{
    const AUTOCREATE_CREATED = 'created';
    const AUTOCREATE_FAILED = 'failed';

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader
     */
    protected $shipmentLoader;

    /**
     * @var ShipmentValidatorInterface
     */
    protected $shipmentValidator;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\OrderFactory $salesOrderFactory
     * @param \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader
     * @param ShipmentValidatorInterface $shipmentValidator
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param Registry $registry

     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader,
        ShipmentValidatorInterface $shipmentValidator,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        Registry $registry
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->orderFactory = $salesOrderFactory;
        $this->shipmentLoader = $shipmentLoader;
        $this->shipmentValidator = $shipmentValidator;
        $this->transactionFactory = $transactionFactory;
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Sales\Model\Order\Shipment
     * @throws \Exception
     */
    public function createShipment(\Magento\Sales\Model\Order $order)
    {
        if (count($order->getShipmentsCollection())) {
            // shipment created
            $this->logger->warning('Can\' create shipment, already exists.', ['order_id' => $order->getId()]);
            throw new \Porterbuddy\Porterbuddy\Exception(__('This order has already been shipped.'));
        }

        $origInProcess = $order->getIsInProcess();

        $this->logger->debug('Auto creating shipment.', ['order_id' => $order->getId()]);

        try {
            $this->shipmentLoader->setOrderId($order->getId());
            $this->shipmentLoader->setShipmentId(null);
            $this->shipmentLoader->setShipment(null);
            $this->shipmentLoader->setTracking(null);
            try {
                $shipment = $this->shipmentLoader->load();
            }catch(\RuntimeException $e){
                $this->registry->unregister('current_shipment');
                $shipment = $this->shipmentLoader->load();
            }

            // shipment->getOrder is a separate order model copy
            $shipmentOrder = $shipment->getOrder();

            if (!$shipment) {
                throw new Exception(__('Cannot create shipment'));
            }

            $validationResult = $this->shipmentValidator->validate($shipment, [QuantityValidator::class]);

            if ($validationResult->hasMessages()) {
                $message = __("Shipment Document Validation Error(s):\n" . implode("\n", $validationResult->getMessages()));
                throw new Exception($message);
            }
            $shipment->register();

            $this->logger->notice('Shipment created automatically.');
            $shipment->addComment(__('Shipment created automatically.'));

            $shipmentOrder->setIsInProcess(true);
            $shipmentOrder->setPbAutocreateStatus(self::AUTOCREATE_CREATED);

            // @see \Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save::_saveShipment
            /** @var \Magento\Framework\DB\Transaction $transactionSave */
            $transactionSave = $this->transactionFactory->create();
            $transactionSave
                ->addObject($shipment)
                ->addObject($shipmentOrder)
                ->save();

            // TODO: send shipment email to admin (copy to) if enabled in config
            // $this->shipmentSender->send($shipment);

            $shipmentOrder->setIsInProcess($origInProcess);

            return $shipment;
        } catch (\Exception $e) {
            $this->logger->error($e);
            $order->setIsInProcess($origInProcess);

            // current $order object is spoilt by unsuccessful shipment, so add comment separately
            /** @var \Magento\Sales\Model\Order $orderCopy */
            $orderCopy = $this->orderFactory->create();
            $orderCopy->load($order->getId());
            $orderCopy->addStatusHistoryComment(
                __('Porterbuddy shipment wasn\'t ordered!')
                . ' ' . strip_tags($e->getMessage())
            );
            $orderCopy->setPbAutocreateStatus(self::AUTOCREATE_FAILED);
            $orderCopy->save();

            throw $e;
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string $startingBy
     * @param callable $callback
     * @throws \Exception
     */
    public function lockShipmentCreation($order, $startingBy, $callback)
    {
        $started = null;
        try {
            $started = $this->setShipmentCreationStarted($order, $startingBy);
            if ($started) {
                $callback($order);
                $this->unsetShipmentCreationStarted($order);
            }
        } catch (\Exception $e) {
            // logged
            if ($started) {
                $this->unsetShipmentCreationStarted($order);
            }

            throw $e;
        }
    }

    /**
     * Sets the flag 'ShipmentCreatingBy' for the order
     * @param \Magento\Sales\Model\Order $order
     * @param string $startingBy
     * @return boolean
     */
    protected function setShipmentCreationStarted($order, $startingBy)
    {
        /** @var Order $orderCopy */
        $orderCopy = $this->orderFactory->create();
        $orderCopy->load($order->getId());

        if ($startedBy = $orderCopy->getPbShipmentCreatingBy()) {
            $this->logger->notice(
                'Shipment creation request is prohibited for ' . $startingBy
                . ' because already started by ' . $startedBy . '. Order: ' . $order->getId()
            );
            return false;
        }
        $orderCopy->setPbShipmentCreatingBy($startingBy)
            ->save();
        $this->logger->notice(
            'Shipment creation started by ' . $startingBy . '. Order: ' . $order->getId()
        );
        return true;
    }

    /**
     * Unsets the flag 'ShipmentCreatingBy' for the order
     * @param \Magento\Sales\Model\Order $order
     */
    protected function unsetShipmentCreationStarted($order)
    {
        /** @var Order $orderCopy */
        $orderCopy = $this->orderFactory->create();
        $orderCopy->load($order->getId());
        $orderCopy->setPbShipmentCreatingBy(null)
            ->save();
        $this->logger->notice('Shipment creation ended. Order: ' . $order->getId());
    }
}
