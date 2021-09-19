<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Delivery;

use Porterbuddy\Porterbuddy\Model\Klarna\AddressUpdateInstance as AddressUpdate;
use Porterbuddy\Porterbuddy\Model\Klarna\KasperInstance as Kasper;
use Porterbuddy\Porterbuddy\Model\Klarna\CheckoutInstance as Checkout;
use Porterbuddy\Porterbuddy\Model\Klarna\AddressInstance as Address;
use Porterbuddy\Porterbuddy\Model\Klarna\SessionInstance as KlarnaSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\QuoteRepository;

class RecipientInfoupdate extends Action
{
    /**
     * @var Validator
     */
    protected $formKeyValidator;
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var KlarnaSession
     */
    protected $session;
    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;
    /**
     * @var AddressUpdate
     */
    protected $addressUpdate;
    /**
     * @var AddressFactory
     */
    protected $quoteAddressFactory;
    /**
     * @var Address
     */
    protected $kcoAddress;
    /**
     * @var DataObjectFactory
     */
    protected $objectFactory;
    /**
     * @var Kasper
     */
    protected $kasper;
    /**
     * @var Checkout
     */
    protected $checkout;

    /**
     * @param Context $context
     * @param Validator $formKeyValidator
     * @param JsonFactory $resultJsonFactory
     * @param KlarnaSession $session
     * @param QuoteRepository $quoteRepository
     * @param AddressUpdate $addressUpdate
     * @param Address $address
     * @param AddressFactory $quoteAddressFactory
     * @param DataObjectFactory $objectFactory
     * @param Kasper $kasper
     * @param Checkout $checkout
     */
    public function __construct(
        Context $context,
        Validator $formKeyValidator,
        JsonFactory $resultJsonFactory,
        KlarnaSession $session,
        QuoteRepository $quoteRepository,
        AddressUpdate $addressUpdate,
        Address $address,
        AddressFactory $quoteAddressFactory,
        DataObjectFactory $objectFactory,
        Kasper $kasper,
        Checkout $checkout
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->objectFactory = $objectFactory;
        $this->kasper = $kasper->get();
        $this->checkout = $checkout->get();
        $this->kcoAddress = $address->get();
        $this->session = $session->get();
        $this->addressUpdate = $addressUpdate->get();
        $this->quoteAddressFactory = $quoteAddressFactory;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();

        if (!$this->getRequest()->isPost()) {
            return $result->setData([
                'errors' => true,
                'message' => __('Method not allowed'),
            ]);
        }

        try {
            $postcode = $this->getRequest()->getPostValue('postcode');
            $street = $this->getRequest()->getPostValue('street');
            $email = $this->getRequest()->getPostValue('email');
            $quote = $this->session->getQuote();

            $shippingAddress = $quote->getShippingAddress();


            // Since country is required field for billing and shipping address,
            // we consider the address information to be empty if country is empty.
            $isEmptyAddress = ($shippingAddress->getCountryId() === null);
            if($isEmptyAddress){
                $shippingAddress = $this->quoteAddressFactory->create();
            }
            $shippingAddress->setPostcode($postcode);
            $shippingAddress->setStreet($street);
            $shippingAddress->setEmail($email);
            $quote->setShippingAddress($shippingAddress);
            $this->quoteRepository->save($quote);

            $address_array = array(
                'postal_code' => $postcode,
                'country' => 'no',
                'email' => $email,
                'street_address' => $street
            );

            $klarnaQuote = $this->session->getKlarnaQuote();

            $create = $this->kasper->generateUpdateRequest($quote);
            $reqData = $create->getRequest();

            $reqData['billing_address'] = $address_array;

            $klarnaResult = $this->checkout->updateOrder($klarnaQuote->getKlarnaCheckoutId(), $reqData);
            return $result->setData(['errors' => false, 'message' => json_encode($klarnaResult)]);

        } catch (LocalizedException $e) {
            return $result->setData([
                'errors' => true,
                'message' => $e->getMessage() . $e->getTraceAsString(),
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'errors' => true,
                'message' => $e->getMessage() . $e->getTraceAsString(),

            ]);
        }
    }
}
