<?php

namespace Porterbuddy\Porterbuddy\Controller\Delivery;

use Magento\Framework\Exception\LocalizedException;


class Timeslots extends \Magento\Framework\App\Action\Action
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
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Checkout\Model\Session $session,
        \Magento\Quote\Model\QuoteRepository $quoteRepository
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->session = $session;
        $this->quoteRepository = $quoteRepository;
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

        $refresh = $this->getRequest()->getPost('refresh');

        if($refresh || !$this->session->getPbDiscount()) {
            /** @var \Magento\Quote\Model\Quote */
            $quote = $this->session->getQuote();

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->collectTotals();
        }
        return $result->setData([
            'errors' => false,
            'timeslots' => $this->session->getPbOptions(),
            'totalDiscount' => $this->session->getPbDiscount()
        ]);
    }
}
