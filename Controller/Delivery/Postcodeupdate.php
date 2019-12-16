<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Delivery;



use Klarna\Kco\Model\Api\Builder\Kasper;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

class Postcodeupdate extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $formKeyValidator;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;
    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var \Klarna\Kco\Controller\Api\AddressUpdate
     */
    protected $addressUpdate;

    /**
     * @var \Magento\Quote\Model\Quote\AddressFactory
     */
    protected $quoteAddressFactory;

    /**
     * @var \Klarna\Kco\Model\Checkout\Kco\Address
     */
    protected $kcoAddress;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $objectFactory;

protected $kasper;
protected $checkout;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Klarna\Kco\Model\Checkout\Kco\Session $session
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \Klarna\Kco\Controller\Api\AddressUpdate $addressUpdate
     * @param \Klarna\Kco\Model\Checkout\Kco\Address $address
     * @param \Magento\Quote\Model\Quote\AddressFactory $quoteAddressFactory
     * @param DataObjectFactory $objectFactory
     * @param Kasper $kasper
     * @param Checkout $checkout
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Klarna\Kco\Model\Checkout\Kco\Session $session,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Klarna\Kco\Controller\Api\AddressUpdate $addressUpdate,
        \Klarna\Kco\Model\Checkout\Kco\Address $address,
        \Magento\Quote\Model\Quote\AddressFactory $quoteAddressFactory,
        DataObjectFactory $objectFactory,
    Kasper $kasper,
        Checkout $checkout

    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->session = $session;
        $this->quoteRepository = $quoteRepository;
        $this->addressUpdate = $addressUpdate;
        $this->kcoAddress = $address;
        $this->objectFactory = $objectFactory;
        $this->kasper = $kasper;
        $this->checkout = $checkout;
        $this->quoteAddressFactory = $quoteAddressFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        if (!$this->getRequest()->isPost()) {
            return $result->setData([
                'errors' => true,
                'message' => __('Method not allowed'),
            ]);
        }

        try {
            $postcode = $this->getRequest()->getPostValue('postcode');
            $quote = $this->session->getQuote();

            $shippingAddress = $quote->getShippingAddress();


            // Since country is required field for billing and shipping address,
            // we consider the address information to be empty if country is empty.
            $isEmptyAddress = ($shippingAddress->getCountryId() === null);
            if($isEmptyAddress){
                $shippingAddress = $this->quoteAddressFactory->create();
            }
            $shippingAddress->setPostcode($postcode);
            $quote->setShippingAddress($shippingAddress);
            $this->quoteRepository->save($quote);


            $klarnaQuote = $this->session->getKlarnaQuote();

            $create = $this->kasper->generateUpdateRequest($quote);
            $reqData = $create->getRequest();

            $reqData['billing_address'] = ['postal_code'=> $postcode];

            $klarnaResult = $this->checkout->updateOrder($klarnaQuote->getKlarnaCheckoutId(), $reqData);
            return $result->setData(['errors' => false, 'message' => json_encode($klarnaResult)]);

            // $shippingAddress = $this->objectFactory->create(['data' => [ $quote->getShippingAddress()->getPostcode()]]);

       // $shippingAddress->setPostalCode($postcode);
       // $shippingAddress->setCountryCode('NO');
        //$this->kcoAddress->updateCheckoutAddress($shippingAddress);
            return $result->setData(['errors' => false, 'message' => $quote->getKlarnaCheckoutId()]);


            $this->quoteRepository->save($quote);
        } catch (LocalizedException $e) {
            return $result->setData([
                'errors' => true,
                'message' => $e->getMessage() . $e->getTraceAsString(),
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'errors' => true,
       //         'message' => __('An error occurred when updating cart'),
                'message' => $e->getMessage() . $e->getTraceAsString(),

            ]);
        }

        return $result->setData(['errors' => false]);
    }
}
