<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use DateInterval;
use DateTime;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Format;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Store\Model\ScopeInterface;
use Magento\Email\Model\Template\SenderResolver;
use Porterbuddy\Porterbuddy\Api\Data\MethodInfoInterface;
use Porterbuddy\Porterbuddy\Exception as PorterbuddyException;
use Porterbuddy\Porterbuddy\Exception;
use Porterbuddy\Porterbuddy\Helper\Data;
use Porterbuddy\Porterbuddy\Model\ErrorNotifier\NotifierInterface;
use Porterbuddy\Porterbuddy\Model\InventoryApi\GetProductSalableQtyInstance as GetProductSalableQtyInterface;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    const CODE = 'porterbuddy';

    const METHOD_EXPRESS = 'express';
    const METHOD_EXPRESS_RETURN = 'express-with-return';
    const METHOD_DELIVERY = 'delivery';
    const METHOD_DELIVERY_RETURN = 'delivery-with-return';

    const CONSOLIDATION_FLAG = 'consolidation';

    const SHORTCUT_EXPRESS = 'x';
    const SHORTCUT_EXPRESS_RETURN = 'xr';
    const SHORTCUT_DELIVERY = 'd';
    const SHORTCUT_DELIVERY_RETURN = 'dr';

    const MODE_PRODUCTION = 'production';
    const MODE_TESTING = 'test';

    const WEIGHT_GRAM = 'GRAM';
    const WEIGHT_KILOGRAM = 'KILOGRAM';

    const UNIT_MILLIMETER = 'MILLIMETER';
    const UNIT_CENTIMETER = 'CENTIMETER';

    const AVAILABILITY_HIDE = 'hide';
    const AVAILABILITY_ONLY_AVAILABLE = 'only_available';
    const AVAILABILITY_ALWAYS = 'always';

    const RATE_TYPE_HOME = 'home_delivery';
    const RATE_TYPE_PICKUP_POINT = 'pickup_point';
    const RATE_TYPE_IN_STORE = 'collect_in_store';

    const COOKIE = 'porterbuddy_location';

    const SOURCE_BROWSER = 'browser';
    const SOURCE_IP = 'ip';
    const SOURCE_USER = 'user';

    protected $_code = self::CODE;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var NotifierInterface
     */
    protected $errorNotifier;

    /**
     * @var CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Format
     */
    protected $localeFormat;

    /**
     * @var TrackFactory
     */
    protected $salesOrderShipmentTrackFactory;

    /**
     * @var Packager
     */
    protected $packager;

    /**
     * @var Timeslots
     */
    protected $timeslots;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var GetProductSalableQtyInterface
     */
    protected $getProductSalableQty;

    /**
     * @var float
     */
    protected $baseCurrencyRate;

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var SenderResolver
     */
    protected $senderResolver;

    /**
     * @var Session
     */
    protected $session;

    public function __construct(
        Api $api,
        NotifierInterface $errorNotifier,
        CurrencyFactory $currencyFactory,
        ManagerInterface $eventManager,
        Data $helper,
        Format $localeFormat,
        MethodFactory $rateMethodFactory,
        ResultFactory $rateResultFactory,
        TrackFactory $trackFactory,
        Packager $packager,
        Timeslots $timeslots,
        ScopeConfigInterface $scopeConfig,
        SenderResolver $senderResolver,
        ErrorFactory $rateErrorFactory,
        GetProductSalableQtyInterface $getProductSalableQty,
        LoggerInterface $logger,
        Session $session,
        array $data = []
    ) {
        $this->api = $api;
        $this->errorNotifier = $errorNotifier;
        $this->currencyFactory = $currencyFactory;
        $this->eventManager = $eventManager;
        $this->helper = $helper;
        $this->localeFormat = $localeFormat;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->rateResultFactory = $rateResultFactory;
        $this->salesOrderShipmentTrackFactory = $trackFactory;
        $this->packager = $packager;
        $this->timeslots = $timeslots;
        $this->senderResolver = $senderResolver;
        $this->session = $session;
        $this->getProductSalableQty = $getProductSalableQty->get();
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function proccessAdditionalValidation(DataObject $request)
    {
        if ('NO' !== $request->getDestCountryId()) {
            $this->_logger->warning("Destination country `{$request->getDestCountryId()}` is not supported.");
            return false;
        }
        if (!strlen($request->getDestPostcode())) {
            $this->_logger->warning("Empty postcode ignored.");
            return false;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryConfirmationTypes(DataObject $params = null)
    {
        // make default option preselected as it goes first
        if ($this->helper->isRequireSignatureDefault()) {
            return [
                1 => __('Signature Required'),
                0 => __('Not Required'),
            ];
        } else {
            return [
                0 => __('Not Required'),
                1 => __('Signature Required'),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContentTypes(DataObject $params)
    {
        return [
            '' => __('-- Product names --'),
            'OTHER' => __('Other'),
        ];
    }

    /**
     * Interface method used to get additional tracking info in tracking popup
     *
     * @param string $number
     * @return DataObject|array
     */
    public function getTrackingInfo($number)
    {
        /** @var Track $track */
        $track = $this->salesOrderShipmentTrackFactory->create()->load($number, 'track_number');

        return [
            'title' => $track->getTitle(),
            'number' => $number,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->helper->getActive()) {
            return false;
        }

        /** @var Result $result */
        $result = $this->rateResultFactory->create();

        // multi warehousing may disable this method if items are physically located in different places
        $this->eventManager->dispatch('porterbuddy_collect_rates', [
            'request' => $request,
            'result' => $result,
        ]);

        $discountAmount = $this->getDiscounts($request);

        if ($result->getError()) {
            return $result;
        }

        if (!$request->getDestPostcode()){
            //address not entered yet, so we can't help you
            return $result;
        }

        $items = $request->getAllItems();
        if (!$items) {
            return $result;
        }
        foreach ($items as $item) {
            $_product = $item->getProduct();
            if ($this->getProductSalableQty) {
                $inventoryStock = $this->helper->getInventoryStock();

                if($inventoryStock != null){
                    $qtyInStock = $this->getProductSalableQty->execute($_product->getSku(), $inventoryStock);
                    if($qtyInStock < $item->getQty()){
                        return $result;
                    }
                }


            } else {
                $this->_logger->debug(__('MSI not supported'));
                //probably means MSI not supported?
            }
            if (!$_product->isSaleable()) {
                //item not in stock
                return $result;
            }

        }

        /** @var \Magento\Quote\Model\Quote\Item $item */
        $item = reset($items);
        $quote = $item->getQuote();

        try {
            $parameters = $this->prepareAvailabilityData($request, $quote);
            $pbPreviousRequest = $this->session->getPbPreviousRequest();
            $pbCurrentRequest = array('destinationAddress' => $parameters['destinationAddress'], 'parcels' => $parameters['parcels']);
            $options = $this->session->getPbOptions();
            $now = new DateTime("now");

            if(!$pbPreviousRequest || !$options || !$pbPreviousRequest['expiry'] || $pbPreviousRequest['expiry'] < $now || !$pbPreviousRequest['request'] || $pbPreviousRequest['request'] != $pbCurrentRequest ){

                $options = $this->api->getAvailability($parameters);
                $this->session->setPbOptions($options);
                $refreshTime = new DateTime("now");
                $refreshTime->add(new DateInterval('P0DT0H1M0S'));
                if($options && $options['deliveryWindows'] && $options['deliveryWindows'][0]){
                    $expirytime = new DateTime($options['deliveryWindows'][0]['expiresAt']);
                    $refreshTime = $refreshTime > $expirytime?$expirytime:$refreshTime;
                }
                $pbPreviousRequest = array( 'request' => $pbCurrentRequest, 'expiry' => $refreshTime );
                $this->session->setPbPreviousRequest($pbPreviousRequest);

            }

        } catch (Exception $e) {
            // details logged
            return $result;
        } catch (\Exception $e) {
            // other unexpected errors
            $this->_logger->error($e);
            return $result;
        }
        $expressOptions = []; // no return + with return
        $scheduledOptions = [];

        foreach ($options['deliveryWindows'] as $option) {
            if (in_array($option['product'], [self::METHOD_EXPRESS, self::METHOD_EXPRESS_RETURN])) {
                $expressOptions[] = $option;
            } elseif (in_array($option['product'], [self::METHOD_DELIVERY, self::METHOD_DELIVERY_RETURN])) {
                $scheduledOptions[] = $option;
            }
        }
        if($options['consolidatedWindow']) {
            $scheduledOptions[] = $options['consolidatedWindow'];
        }
        if (true === $request->getFreeShipping()) {
            $this->_logger->info("collectRates - free shipping for quote {$quote->getId()}", [
                'applied_rule_ids' => $quote->getAppliedRuleIds(),
                'discount_description' => $quote->getShippingAddress()->getDiscountDescription(),
            ]);
        }

        if ($expressOptions) {
            foreach ($expressOptions as $option) {
                try {
                    $result = $this->addRateResult($request, $option, $result);
                } catch (Exception $e) {
                    $this->_logger->warning("Availability option error - {$e->getMessage()}.", $option);
                    $this->_logger->warning($e, $option);
                }
            }
        }

        foreach ($scheduledOptions as $option) {
            try {

                $result = $this->addRateResult($request, $option, $result);

            } catch (Exception $e) {
                $this->_logger->warning("Availability option error - {$e->getMessage()}.", $option);
                $this->_logger->warning($e, $option);
            }
        }


        $result = $this->applyDiscounts($discountAmount, $result);


        if($this->helper->getUseDonor()) {
            $donorRate = $this->rateMethodFactory->create();

            $donorRate->setCarrier(self::CODE);
            $donorRate->setCarrierTitle($this->helper->getTitle());

            $donorRate->setMethod("donor");
            $donorRate->setMethodTitle("Porterbuddy");

            $donorRate->setPrice(0);
            $donorRate->setCost(0);

            $result->append($donorRate);
        }

        // enable to construct new result object
        $transport = new DataObject(['result' => $result]);
        $this->eventManager->dispatch('porterbuddy_collect_rates_after', [
            'request' => $request,
            'transport' => $transport,
        ]);
        $result = $transport->getData('result');

        return $result;
    }

    /**
     * @param RateRequest $request
     * @param array $option
     * @param Result $result
     * @return Result
     * @throws Exception
     */
    public function addRateResult(
        RateRequest $request,
        array $option,
        Result $result
    ) {
        $methodCode = $this->helper->makeMethodCode($option);

        // convert to standard interface
        $methodInfo = $this->helper->parseMethod($methodCode);

        // Local timezone
        $methodTitle = $this->timeslots->formatTimeslot($methodInfo);

        /** @var Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier(self::CODE);
        $method->setCarrierTitle($this->helper->getTitle());

        $method->setMethod($methodCode);
        $method->setMethodTitle($this->helper->getTitle() . " " . $methodTitle); // $this->helper->getScheduledName()
        //$method->setMethodDescription($this->helper->getScheduledDescription());

        if ($request->getFreeShipping() === true) {
            $shippingPrice = '0.00';
        } else {
            switch ($methodInfo->getProduct()) {
                case self::METHOD_EXPRESS:
                    $shippingPrice = $this->helper->getPriceOverrideExpress();
                    break;
                case self::METHOD_EXPRESS_RETURN:
                    $shippingPrice = $this->helper->getPriceOverrideExpressReturn();
                    break;
                case self::METHOD_DELIVERY:
                    $shippingPrice = $this->helper->getPriceOverrideDelivery();
                    break;
                case self::METHOD_DELIVERY_RETURN:
                    $shippingPrice = $this->helper->getPriceOverrideDeliveryReturn();
                    break;
                default:
                    $shippingPrice = null;
            }

            if (null === $shippingPrice
                && isset($option['price']['fractionalDenomination'], $option['price']['currency'])
            ) {
                $apiPrice = $this->localeFormat->getNumber($option['price']['fractionalDenomination']) / 100;
                $rate = $this->getBaseCurrencyRate($request, $option['price']['currency']);
                $shippingPrice = $apiPrice * $rate;
            }
            if (null === $shippingPrice) {
                $this->_logger->warning('Skip option with undefined price', ['option' => $option]);
                return $result;
            }
        }

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $result->append($method);

        return $result;
    }

    /**
     * @param RateRequest $request
     * @return float
     */
    public function getBaseCurrencyRate(RateRequest $request, $responseCurrencyCode = 'NOK')
    {
        if (null === $this->baseCurrencyRate) {
            // TODO: throw error if base currency rate is not defined
            /** @var Currency $currency */
            $currency = $this->currencyFactory->create();
            $this->baseCurrencyRate = $currency
                ->load($responseCurrencyCode)
                ->getAnyRate($request->getBaseCurrency()->getCode());
        }
        return $this->baseCurrencyRate;
    }

    /**
     * Get discounts based on cart subtotal threshold
     *
     * @param RateRequest $request
     * @return int
     */
    public function getDiscounts(
        RateRequest $request
    ) {

        // known possible problem: $request->getBaseSubtotalInclTax can be empty in some cases, same problem with
        // free shipping. This is because shipping total collector is called before tax subtotal collector, and so
        // BaseSubtotalInclTax is not updated yet.

        $basketValue = $request->getBaseSubtotalInclTax();
        if ($basketValue == 0 && $request->getPackageValueWithDiscount()>0) {
            $basketValue = $request->getPackageValueWithDiscount() * 1.25;
        }

        $discounts = $this->helper->getDiscounts();

        $discountAmount = 0;
        $this->_logger->debug(json_encode($discounts));
        foreach($discounts as $discount) {
            $this->_logger->debug(json_encode($discounts));
            $this->_logger->debug($basketValue);
            if ((int)trim($discount['minimumbasket']) < $basketValue) {
                //Basket is eligible
                if ((int)trim($discount['discount']) > $discountAmount) {
                    //best discount
                    $discountAmount = (int)trim($discount['discount']);
                }
            }
        }

        $this->session->setPbDiscount($discountAmount);
        $this->_logger->debug('pbdiscount' . $this->session->getPbDiscount());
        return $discountAmount;
    }

    /**
     * Applies discounts based on cart subtotal threshold
     *
     * @param $discountAmount
     * @param Result $result
     * @return Result
     */
    public function applyDiscounts(
        $discountAmount,
        Result $result
    ) {

        if($discountAmount > 0){
            foreach ($result->getAllRates() as $method) {
                $price = $method->getPrice();
                if ($price > 0) {
                    $price -= $discountAmount;
                    $price = max($price, 0.00);
                    $method->setPrice(max($price, 0.00));
                }
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     * @throws LocalizedException
     */
    public function requestToShipment($request)
    {
        $result = new DataObject();

        $shipment = $request->getOrderShipment();

        $this->_logger->notice('requestToShipment - start.', [
            'order_id' => $shipment->getOrderId(),
            'shipment_id' => $shipment->getId(),
        ]);

        // mark it processed to disable auto creating label, we already processed it
        $shipment->setIsPorterbuddySent(true);

        try {
            $parameters = $this->prepareCreateOrderData($request);
            $idempotencyKey = $this->getShipmentIdempotencyKey($request);
            $orderDetails = $this->api->createOrder($parameters, $idempotencyKey);
        } catch (Exception $e) {
            $this->errorNotifier->notify($e, $shipment, $request);
            $shipment->setPorterbuddyErrorNotified(true);

            // details logged
            $result->setErrors($e->getMessage());
            //return $result;
            throw $e;
        } catch (\Exception $e) {
            $this->errorNotifier->notify($e, $shipment, $request);
            $shipment->setPorterbuddyErrorNotified(true);

            // other unexpected errors
            $this->_logger->error($e);
            $result->setErrors($e->getMessage());
            //return $result;
            throw $e;
        }


        $comment = __('Porterbuddy shipment has been ordered.');
        if (!empty($orderDetails['deliveryReference'])) {
            $comment .= ' ' . __('Delivery reference %1', $orderDetails['deliveryReference']);
        }
        $shipment->addComment($comment);

        // Magento requires returning pairs shipping label-tracking number. As we don't support actual labels yet,
        // we can't provide tracking numbers by standard mechanism either. So we will assign them manually to shipment

        $trackingNumber = $orderDetails['orderId'];
        /** @var Track $track */
        $track = $this->salesOrderShipmentTrackFactory->create();
        $track
            ->setNumber($trackingNumber)
            ->setCarrierCode($this->getCarrierCode())
            ->setTitle(__('Order ID'));
        $shipment->addTrack($track);

        if (!empty($orderDetails['deliveryReference'])) {
            $trackingNumber = $orderDetails['deliveryReference'];
            /** @var Track $track */
            $track = $this->salesOrderShipmentTrackFactory->create();
            $track
                ->setNumber($trackingNumber)
                ->setCarrierCode($this->getCarrierCode())
                ->setTitle(__('Delivery Reference'));
            $shipment->addTrack($track);
        }

        $result->setInfo([]); // mark as success

        $this->_logger->notice('requestToShipment - success.', [
            'order_id' => $shipment->getOrderId(),
            'shipment_id' => $shipment->getId(),
        ]);

        return $result;
    }

    /**
     * @param Request $request
     * @return string|null
     */
    public function getShipmentIdempotencyKey(Request $request)
    {
        $shipment = $request->getOrderShipment();
        $order = $shipment->getOrder();

        // TODO: for part shipping, include items
        return $order->getIncrementId();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedMethods()
    {
        return [
            self::SHORTCUT_EXPRESS => __('Express'),
            self::SHORTCUT_DELIVERY => __('Delivery'),
        ];
    }

    /**
     * @param RateRequest $request
     * @return array
     * @throws Exception
     */
    public function prepareAvailabilityData(RateRequest $request, Quote $quote)
    {
        $params = [];

        $params['pickupWindows'] = $this->timeslots->getAvailabilityPickupWindows($request);

        $originStreet1 = $this->_scopeConfig->getValue(Shipment::XML_PATH_STORE_ADDRESS1, ScopeInterface::SCOPE_STORE);
        $originStreet2 = $this->_scopeConfig->getValue(Shipment::XML_PATH_STORE_ADDRESS2, ScopeInterface::SCOPE_STORE);
        $params['originAddress'] = [
            'streetName' => trim("$originStreet1 $originStreet2"),
            'streetNumber' => ',', // FIXME: set empty when API is updated
            'postalCode' => $this->_scopeConfig->getValue(Shipment::XML_PATH_STORE_ZIP, ScopeInterface::SCOPE_STORE),
            'city' => $this->_scopeConfig->getValue(Shipment::XML_PATH_STORE_CITY, ScopeInterface::SCOPE_STORE),
            'country' => $this->_scopeConfig->getValue(Shipment::XML_PATH_STORE_COUNTRY_ID, ScopeInterface::SCOPE_STORE),
        ];

        $streetName = $request->getDestStreet();
        if (is_array($streetName)) {
            $streetName = implode(' ', $streetName);
        }

        $params['destinationAddress'] = [
            'streetName' => $streetName,
            //'streetNumber' => '',
            'postalCode' => $request->getDestPostcode(),
            'city' => $request->getDestCity(),
            'country' => $request->getDestCountryId(),
        ];

        $params['recipient'] = [
            'email' => $quote->getCustomerEmail()
        ];
        // create availability check context
        $params['parcels'] = $this->packager->estimateParcels($request);
        $params['items'] = $this->getItems($request->getAllItems());
        $params['products'] = [self::METHOD_EXPRESS, self::METHOD_DELIVERY];

        if ($this->helper->getReturnEnabled()) {
            $params['products'][] = self::METHOD_EXPRESS_RETURN;
            $params['products'][] = self::METHOD_DELIVERY_RETURN;
        }

        $transport = new DataObject(['params' => $params]);
        $this->eventManager->dispatch('porterbuddy_availability_data', [
            'request' => $request,
            'transport' => $transport,
        ]);
        $params = $transport->getData('params');

        return $params;
    }

    /**
     * Prepares request payload for create order API call
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    protected function prepareCreateOrderData(Request $request)
    {
        $shipment = $request->getOrderShipment();
        $order = $shipment->getOrder();
        $method = $request->getShippingMethod();
        $methodInfo = $this->helper->parseMethod($method);

        $methodInfo = $this->checkExpiredTimeslot($methodInfo);
        $parcels = $this->getParcels($request, $shipment);

        if (!$request->getRecipientEmail()) {
            $request->setRecipientEmail($order->getCustomerEmail());
        }

        $defaultPhoneCode = $this->helper->getDefaultPhoneCode();
        $pickupPhone = $this->helper->splitPhoneCodeNumber($request->getShipperContactPhoneNumber());
        $deliveryPhone = $this->helper->splitPhoneCodeNumber($request->getRecipientContactPhoneNumber());


        $senderlIdentity = $this->helper->getOrderEmailIdentity($shipment->getStoreId());
        $senderResult = $this->senderResolver->resolve($senderlIdentity,$shipment->getStoreId());

        $parameters = [
            'origin' => [
                'name' => $senderResult['name'],
                'address' => [
                    'streetName' => $request->getShipperAddressStreet(),
                    'streetNumber' => ',', // TODO: remove when API ready
                    'postalCode' => $request->getShipperAddressPostalCode(),
                    'city' => $request->getShipperAddressCity(),
                    'country' => $request->getShipperAddressCountryCode(),
                ],
                'email' => $senderResult['email'],
                'phoneCountryCode' => $pickupPhone[0] ?: $defaultPhoneCode,
                'phoneNumber' => $pickupPhone[1],
                'pickupWindows' => $this->timeslots->getPickupWindows($methodInfo),
            ],
            'destination' => [
                'name' => $request->getRecipientContactPersonName(),
                'address' => [
                    'streetName' => $request->getRecipientAddressStreet(),
                    'streetNumber' => ',', // TODO: remove when API ready
                    'postalCode' => $request->getRecipientAddressPostalCode(),
                    'city' => $request->getRecipientAddressCity(),
                    'country' => $request->getRecipientAddressCountryCode(),
                ],
                'email' => $request->getRecipientEmail(),
                'phoneCountryCode' => $deliveryPhone[0] ?: $defaultPhoneCode,
                'phoneNumber' => $deliveryPhone[1],
                'verifications' => $this->getVerifications($shipment),
            ],
            'parcels' => $parcels,
            'items' => $this->getItems($shipment->getAllItems()),
            'product' => $methodInfo->getProduct(),
            'orderReference' => $order->getIncrementId(),
            'courierInstructions' => $order->getPbComment() ?: '',
        ];
        if($methodInfo->isConsolidated()){
            $parameters['destination']['consolidatedWindow'] = [
                'token' => $order->getPbToken(),
            ];
        }else{
            $parameters['destination']['deliveryWindow'] = [
                'start' => $this->helper->formatApiDateTime($methodInfo->getStart()),
                'end' => $this->helper->formatApiDateTime($methodInfo->getEnd()),
                'token' => $order->getPbToken(),
            ];
        }

        $transport = new DataObject(['parameters' => $parameters]);
        $this->eventManager->dispatch('porterbuddy_create_order_data', [
            'transport' => $transport,
            'request' => $request,
        ]);
        $parameters = $transport->getData('parameters');

        return $parameters;
    }

    /**
     * Returns items output from an array of Magento order line item
     *
     * @param array \Magento\Quote\Model\Quote\Item $items
     * @return array
     */
    public function getItems(array $items)
    {
        $returnItems = [];

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach($items as $item) {
            $_product = $item->getProduct();
            $widthCm = $this->helper->convertDimensionToCm(
                    $item->getData('params/width'),
                    $item->getData('params/dimension_units')
                ) ?: $this->helper->getDefaultProductWidth();

            $heightCm = $this->helper->convertDimensionToCm(
                $item->getData('params/height'),
                $item->getData('params/dimension_units')
                ) ?: $this->helper->getDefaultProductHeight();

            $depthCm = $this->helper->convertDimensionToCm(
                $item->getData('params/length'),
                $item->getData('params/dimension_units')
                ) ?: $this->helper->getDefaultProductLength();

            $weightGrams = $this->helper->convertWeightToGrams(
                $item->getData('params/weight'),
                $item->getData('params/weight_unit')
                ) ?: $this->helper->getDefaultProductWeight();


            $category = '';
            if($_product->getCategory()){
                $category = $_product->getCategory()->getName();
            }
            $imageUrls = [];
            foreach($_product->getMediaGalleryImages() as $image){
                $imageUrls[] = $image->getData('url');
            }
            $returnItems[] = array(

                'weightGrams' => $weightGrams,
                'widthCm' => $widthCm,
                'heightCm' => $heightCm,
                'depthCm' => $depthCm,
                'name' => $_product->getName(),
                'sku' => $_product->getSku(),
                'price' => array(
                    'fractionalDenomination' => round($_product->getPrice()*100),
                    'currency' => 'NOK'
                ),
                'category' => $category,
                'brand' => $_product->getAttributeText('manufacturer'),
                'imageUrlArray' => $imageUrls,
                'quantity' => $_product->getQty()
            );
        }
        return $returnItems;
    }

    /**
     * For passed dates, get new closest timeslot
     *
     * @param MethodInfoInterface $methodInfo
     * @return MethodInfoInterface
     * @throws Exception
     */
    public function checkExpiredTimeslot(MethodInfoInterface $methodInfo)
    {
        $scheduledDate = new DateTime($methodInfo->getStart());
        $currentTime = $this->helper->getCurrentTime();
        if ($currentTime > $scheduledDate) {
            $this->_logger->error("Delivery timeslot `{$methodInfo->getStart()}` expired.");
            // FIXME
            // throw new \Porterbuddy\Porterbuddy\Exception(__('Delivery timeslot %1 expired', $methodInfo->getStart()));
        }
        return $methodInfo;
    }

    /**
     * Creates shipment packages if needed, exports to Porterbuddy API format
     *
     * @param Request $request
     * @param Shipment $shipment
     * @return array
     * @throws Exception
     * @throws LocalizedException
     */
    public function getParcels(
        Request $request,
        Shipment $shipment
    ) {
        if (!$shipment->getPackages() || !is_array($shipment->getPackages())) {
            $packages = $this->packager->createPackages($request);
            $shipment->setPackages($packages);
            $this->_logger->notice('Automatically created packages.');
        } else {
            $this->_logger->notice('Packages already created.');
        }

        $shipment->setPackages($shipment->getPackages());

        $parcels = $this->packager->getParcelsFromPackages($shipment);
        if (!$parcels) {
            $this->_logger->error(
                "Error preparing order data for shipment `{$shipment->getId()}`, empty parcels",
                [
                    'packages' => $shipment->getPackages()
                ]
            );
            throw new Exception(__('There was an error preparing parcels.'));
        }
        return $parcels;
    }

    /**
     * Returns verification options based on shipment packages params, products and default settings
     *
     * @param Shipment $shipment
     * @return array
     */
    public function getVerifications(Shipment $shipment)
    {
        $order = $shipment->getOrder();
        $packages = $shipment->getPackages();
        if ($packages && is_scalar($packages)) {
            $packages = unserialize($packages);
        }

        $verifications = [];
        $verifications['leaveAtDoorstep'] = (bool)$order->getPbLeaveDoorstep();

        // when creating packages manually, admin selects signature confirmation.
        // if at least one package requires signature. treat whole order requiring as well
        $requireSignature = null;
        foreach ($packages as $package) {
            $package = new DataObject($package);
            $value = $package->getData('params/delivery_confirmation');
            if (null !== $value) {
                $requireSignature = $requireSignature || $value;
            }
        }

        if (null !== $requireSignature) {
            $verifications['requireSignature'] = $requireSignature;
        } else {
            // signature requirement not set explicitly in packages, check products
            $verifications['requireSignature'] = $this->isVerificationRequired(
                $shipment,
                $this->helper->isRequireSignatureDefault(),
                $this->helper->getRequireSignatureAttr()
            );
        }

        $minAge = $this->helper->getMinAgeCheckDefault();
        $minAgeAttr = $this->helper->getMinAgeCheckAttr();
        if ($minAgeAttr) {
            /** @var Item $item */
            foreach ($shipment->getAllItems() as $item) {
                $product = $item->getOrderItem()->getProduct();
                $value = $product->getData($minAgeAttr);
                if ($product->hasData($minAgeAttr) && is_numeric($value) && $value > 0) {
                    $minAge = $minAge ? min($minAge, $value) : $value;
                }
            }
        }
        if ($minAge) {
            $verifications['minimumAgeCheck'] = $minAge;
        }

        $verifications['idCheck'] = $this->isVerificationRequired(
            $shipment,
            $this->helper->isIdCheckDefault(),
            $this->helper->getIdCheckAttr()
        );

        $verifications['onlyToRecipient'] = $this->isVerificationRequired(
            $shipment,
            $this->helper->isOnlyToRecipientDefault(),
            $this->helper->getOnlyToRecipientAttr()
        );

        return $verifications;
    }

    /**
     * Checks if verification is required
     *
     * - always required if set by default
     * - required if product attribute is set and at least one product in order is marked true
     *
     * @param Shipment $shipment
     * @param int $default
     * @param string|null $attributeCode
     * @return bool
     */
    protected function isVerificationRequired(Shipment $shipment, $default, $attributeCode)
    {
        $result = $default;

        if (!$result && $attributeCode) {
            // true if at least one product is true
            /** @var Item $item */
            foreach ($shipment->getAllItems() as $item) {
                $product = $item->getOrderItem()->getProduct();
                if ($product->getData($attributeCode)) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }
}
