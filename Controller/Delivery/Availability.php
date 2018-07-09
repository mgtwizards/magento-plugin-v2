<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Delivery;

use Magento\Framework\App\Action\Context;

class Availability extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Porterbuddy\Porterbuddy\Model\Timeslots
     */
    protected $timeslots;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Magento\Catalog\Model\ProductFactory $catalogProductFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Porterbuddy\Porterbuddy\Model\Timeslots $timeslots
     * @param Context $context
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Porterbuddy\Porterbuddy\Model\Timeslots $timeslots,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->helper = $helper;
        $this->productFactory = $catalogProductFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->timeslots = $timeslots;
        parent::__construct($context);
    }

    /**
     * Checks postcode is available, product is in stock and calculates closest deadline
     *
     * - available today before 16:00 - Want it today? order within 5 hrs 30 minutes
     * - today it's too late, can ship tomorrow - Want it tomorrow? order within 1 day 5 hrs 30 minutes
     * - today it's too late and then there's a weekend - Want it Monday? order within 3 days 5 hrs 30 minutes
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        $postcode = $this->getRequest()->getParam('postcode');
        $productId = $this->getRequest()->getParam('productId');

        if (!$postcode) {
            return $result->setData([
                'error' => true,
                'messages' => __('Postcode is required'),
            ]);
        }
        if (!$productId) {
            return $result->setData([
                'error' => true,
                'messages' => __('Product ID is required'),
            ]);
        }

        if (!$this->helper->isPostcodeSupported($postcode)) {
            return $result->setData([
                'error' => true,
                'messages' => $this->helper->processPlaceholders(
                    $this->helper->getAvailabilityTextPostcodeError()
                )
            ]);
        }

        // check product is in stock
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->productFactory->create();
        $product->load($productId);
        if (!$product->getId()) {
            return $result->setData([
                'error' => true,
                'messages' => __('Product not found'),
            ]);
        }
        if (!$product->isAvailable()) {
            // TODO: product placeholders
            return $result->setData([
                'error' => true,
                'messages' => $this->helper->processPlaceholders(
                    $this->helper->getAvailabilityTextOutOfStock()
                )
            ]);
        }
        if ($product->isVirtual()) {
            return $result->setData([
                'error' => true,
                'messages' => __('Virtual products cannot be shipped'),
            ]);
        }

        // check store working hours + Porterbuddy hours
        $date = $this->timeslots->getAvailableUntil();
        if (!$date) {
            return $result->setData([
                'error' => true,
                'messages' => $this->helper->processPlaceholders(
                    $this->helper->getAvailabilityTextNoDate()
                )
            ]);
        }

        $now = $this->helper->getCurrentTime();
        // server-based countdown in case browser's clocks lie
        $timeRemaining = floor(($date->getTimestamp() - $now->getTimestamp())/60); // minutes

        // today, tomorrow, Monday, May 28
        if ($now->format('Y-m-d') == $date->format('Y-m-d')) {
            $humanDate = mb_convert_case(__('Today'), MB_CASE_LOWER);
        } elseif ($now->modify('+1 day')->format('Y-m-d') == $date->format('Y-m-d')) {
            $humanDate = mb_convert_case(__('Tomorrow'), MB_CASE_LOWER);
        } else {
            $humanDate = __($date->format('D'));
        }

        return $result->setData([
            'error' => false,
            'available' => true,
            'date' => $date->format(\DateTime::ATOM),
            'humanDate' => $humanDate,
            'timeRemaining' => $timeRemaining,
        ]);
    }
}
