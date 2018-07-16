<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\ErrorNotifier;

use Magento\Framework\DataObject;

class EmailNotifier implements NotifierInterface
{
    /**
     * @var \Magento\Sales\Model\Order\Address\Renderer
     */
    protected $addressRenderer;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @param \Magento\Sales\Model\Order\Address\Renderer $addressRenderer
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     */
    public function __construct(
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
    ) {
        $this->addressRenderer = $addressRenderer;
        $this->eventManager = $eventManager;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(
        \Exception $exception,
        \Magento\Sales\Model\Order\Shipment $shipment,
        \Magento\Shipping\Model\Shipment\Request $request = null
    ) {
        if (!$this->helper->getErrorEmailEnabled()) {
            return;
        }

        $emails = $this->helper->getErrorEmailRecipients();

        if ($exception instanceof \Porterbuddy\Porterbuddy\Exception\ApiException) {
            $emails = array_merge($emails, $this->helper->getErrorEmailRecipientsPorterbuddy());
        }

        $storeId = $shipment->getStoreId();
        $sender = $this->helper->getErrorEmailIdentify($storeId);

        if (!$sender || !$emails) {
            $this->logger->alert(
                'Email error notify - sender and recipients must be defined',
                ['order_id' => $shipment->getOrderId()]
            );
            return;
        }

        $this->transportBuilder->setTemplateIdentifier($this->helper->getErrorEmailTemplate($storeId));
        $this->transportBuilder->setTemplateOptions([
            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
            'store' => $storeId,
        ]);
        $this->transportBuilder->setTemplateVars($this->getTemplateParams($exception, $shipment, $request));
        $this->transportBuilder->setFrom($sender);

        foreach ($emails as $email) {
            $this->transportBuilder->addTo($email);
        }

        $transport = $this->transportBuilder->getTransport();

        try {
            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->alert('Send error email failure - ' . $e->getMessage());
            $this->logger->error($e);
        }
    }

    /**
     * @param \Exception $exception
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param \Magento\Shipping\Model\Shipment\Request|null $request
     * @return array
     */
    public function getTemplateParams(
        \Exception $exception,
        \Magento\Sales\Model\Order\Shipment $shipment,
        \Magento\Shipping\Model\Shipment\Request $request = null
    ) {
        $packages = $shipment->getPackages();
        if ($packages && is_scalar($packages)) {
            $packages = unserialize($packages);
        }

        $logData = new DataObject();
        if ($exception instanceof \Porterbuddy\Porterbuddy\Exception\ApiException) {
            $logData->setData($exception->getLogData());
        }

        $params = [
            'shipment' => $shipment,
            'packages' => $packages,
            'packages_json' => json_encode($packages, JSON_PRETTY_PRINT),
            'order' => $shipment->getOrder(),
            'exception' => $exception,
            'message' => $exception->getMessage(),
            'apiUrl' => $logData->getData('api_url'),
            'apiKey' => $logData->getData('api_key'),
            'parameters' => $logData->getData('parameters'),
            'parameters_json' => json_encode($logData->getData('parameters'), JSON_PRETTY_PRINT),
            'formattedShippingAddress' => $this->getFormattedShippingAddress($shipment->getOrder()),
            'store' => $shipment->getOrder()->getStore(),
            'status' => $logData->getData('status'), // optional
            'response' => $logData->getData('response'), // optional
        ];

        $transport = new DataObject(['params' => $params]);
        $this->eventManager->dispatch('error_email_params', [
            'request' => $request,
            'transport' => $transport,
        ]);
        $params = $transport->getData('params');

        return $params;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     */
    protected function getFormattedShippingAddress($order)
    {
        return $this->addressRenderer->format($order->getShippingAddress(), 'html');
    }
}
