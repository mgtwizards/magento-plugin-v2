<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Observer;

use Magento\Framework\Event\Observer;
use Porterbuddy\Porterbuddy\Model\Carrier;
use Porterbuddy\Porterbuddy\Model\ErrorNotifier\NotifierInterface;

class ShipmentSaveBeforeSendShipment implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $authSession;

    /**
     * @var \Magento\Shipping\Model\CarrierFactory
     */
    protected $carrierFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var NotifierInterface
     */
    protected $errorNotifier;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Shipping\Model\Shipping\LabelsFactory
     */
    protected $labelFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\User\Model\UserFactory
     */
    protected $userFactory;

    /**
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Shipping\Model\Shipping\LabelsFactory $labelFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\User\Model\UserFactory $userFactory
     */
    public function __construct(
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Shipping\Model\CarrierFactory $carrierFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        NotifierInterface $errorNotifier,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Shipping\Model\Shipping\LabelsFactory $labelFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\User\Model\UserFactory $userFactory
    ) {
        $this->authSession = $authSession;
        $this->carrierFactory = $carrierFactory;
        $this->dateTime = $dateTime;
        $this->errorNotifier = $errorNotifier;
        $this->eventManager = $eventManager;
        $this->labelFactory = $labelFactory;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->userFactory = $userFactory;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getShipment();

        $carrier = $this->carrierFactory->create(
            $shipment->getOrder()->getShippingMethod(true)->getCarrierCode()
        );

        if (!$carrier instanceof Carrier) {
            return false;
        }

        if (!$shipment->isObjectNew()) {
            return false;
        }

        // if admin created shipment and entered label params explicitly, we have already processed this shipment
        if ($shipment->getIsPorterbuddySent()) {
            return false;
        }

        // requestToShipment requires current admin user in session
        // contact person name: firstname, lastname, name, email
        $adminSession = $this->authSession;
        $fakeAdmin = null;
        if (!$adminSession->getUser()) {
            /** @var \Magento\User\Model\User $fakeAdmin */
            $fakeAdmin = $this->userFactory->create();
            // @see Mage_Shipping_Model_Shipping::requestToShipment
            $fakeAdmin->setFirstname('Store');
            $fakeAdmin->setLastname('Admin');
            $fakeAdmin->setEmail(
                $this->scopeConfig->getValue(
                    'trans_email/ident_general/email',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $shipment->getStoreId()
                )
            );
            $adminSession->setUser($fakeAdmin);
        }

        // can throw exception and abort shipment creation
        try {
            // normally errors when creating labels don't throw exceptions
            // but we want any error to prevent shipment creation, so we use exceptions
            $response = $this->labelFactory->create()->requestToShipment($shipment);  // carrier->requestToShipment
        } catch (\Exception $e) {
            // if missing admin shipping origin fields, carrier.requestToShipment will not be called and flag not set
            // if flat is true, all errors are logged
            if (!$shipment->getIsPorterbuddySent()) {
                $this->logger->error('Auto request to shipment failed - ' . $e->getMessage(), [
                    'order_id' => $shipment->getOrderId(),
                    'shipment_id' => $shipment->getId(),
                ]);
                $this->logger->error($e);
            }

            // Magento can throw error before carrier can intercept it, e.g. missing shipping settings
            if (!$shipment->getPorterbuddyErrorNotified()) {
                $this->errorNotifier->notify($e, $shipment);
                $shipment->setPorterbuddyErrorNotified(true);
            }
            throw $e;
        } finally {
            if ($fakeAdmin) {
                $adminSession->setUser(null);
            }
        }

        return true;
    }
}
