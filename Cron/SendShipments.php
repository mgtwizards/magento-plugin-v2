<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Cron;

use Porterbuddy\Porterbuddy\Helper\Data;
use Porterbuddy\Porterbuddy\Model\Carrier;
use Porterbuddy\Porterbuddy\Model\Shipment;

class SendShipments
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Shipment
     */
    protected $shipment;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @param Data $helper
     * @param Shipment $shipment
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Data $helper,
        \Psr\Log\LoggerInterface $logger,
        Shipment $shipment,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->shipment = $shipment;
    }

    /**
     * Automatically create shipments for paid Porterbuddy orders
     */
    public function execute()
    {
        if (!$this->helper->getAutoCreateShipment()) {
            return;
        }

        $lastOrderId = null;
        $order = $this->getNextOrder($lastOrderId);

        while ($order->getId()) {
            try {
                $this->shipment->lockShipmentCreation(
                    $order,
                    Data::SHIPMENT_CREATOR_CRON,
                    function ($order) {
                        $this->logger->notice('Creating shipment by cron.', ['order_id' => $order->getId()]);
                        $this->shipment->createShipment($order);
                    }
                );
            } catch (\Exception $e) {
                // already logged
            }

            $lastOrderId = $order->getId();
            $order = $this->getNextOrder($lastOrderId);
        }
    }

    /**
     * Loads orders one by one so that other cron instance doesn't start processing same orders before current instance finishes
     *
     * @param int $lastOrderId (optional)
     * @return \Magento\Sales\Model\Order
     */
    public function getNextOrder($lastOrderId = null)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()->joinLeft(
            ['shipment' => 'sales_shipment'],
            'main_table.entity_id = shipment.order_id',
            []
        );
        $collection
            ->addFieldToFilter('shipping_method', ['like' => Carrier::CODE . '\_%'])
            ->addFieldToFilter('pb_paid_at', ['notnull' => true])
            ->addFieldToFilter('pb_autocreate_status', ['null' => true]) // not processed
            ->addFieldToFilter('shipment.entity_id', ['null' => true]) // shipment not created
            ->setPageSize(1);

        if ($lastOrderId) {
            $collection->addFieldtoFilter('main_table.entity_id', ['gt' => $lastOrderId]);
        }

        $collection->load();

        return $collection->getFirstItem();
    }
}
