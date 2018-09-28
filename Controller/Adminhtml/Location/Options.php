<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Adminhtml\Location;

class Options extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Magento_Sales::sales_order';

    /**
     * @var \Magento\Shipping\Model\CarrierFactory
     */
    protected $carrierFactory;

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
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Backend\App\Action\Context $context
     */
    public function __construct(
        \Magento\Shipping\Model\CarrierFactory $carrierFactory,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Backend\App\Action\Context $context
    ) {
        $this->carrierFactory = $carrierFactory;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$this->getRequest()->isPost()) {
            $this->messageManager->addError(__('Method not allowed.'));
            return $resultRedirect->setRefererUrl();
        }

        $orderId = $this->getRequest()->getParam('order_id');
        $leaveDoorstep = $this->getRequest()->getPost('leave_doorstep');
        $comment = $this->getRequest()->getPost('comment');

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            $this->messageManager->addError(__('Order not found.'));
            return $resultRedirect->setRefererUrl();
        }

        if (!$order->getShippingMethod()) {
            $this->messageManager->addError(__('This is not a valid order.'));
            return $resultRedirect->setRefererUrl();
        }

        $carrier = $this->carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());
        if (!$carrier instanceof \Porterbuddy\Porterbuddy\Model\Carrier) {
            $this->messageManager->addError(__('This is not a valid order.'));
            return $resultRedirect->setRefererUrl();
        }

        if (count($order->getShipmentsCollection())) {
            $this->messageManager->addError(__('This order has already been shipped.'));
            return $resultRedirect->setRefererUrl();
        }

        $order
            ->setPbLeaveDoorstep($leaveDoorstep)
            ->setPbComment($comment);

        try {
            $order->save();
        } catch (\Exception $e) {
            $this->logger->error($e);
            $this->messageManager->addError($e->getMessage());
            return $resultRedirect->setRefererUrl();
        }

        $this->messageManager->addSuccess(__('Order has been updated.'));
        return $resultRedirect->setRefererUrl();
    }
}
