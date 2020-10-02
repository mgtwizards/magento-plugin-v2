<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Delivery;

use Magento\Framework\App\Action\Context;
use Magento\Framework\DataObject;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Porterbuddy\Porterbuddy\Model\InventoryApi\GetProductSalableQtyInstance as GetProductSalableQtyInterface;
use Porterbuddy\Porterbuddy\Model\InventoryApi\IsProductSalableInstance as IsProductSalableInterface;

class Availability extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var GetProductSalableQtyInterface
     */
    protected $getProductSalableQty;

    /**
     * @var IsProductSalableInterface
     */
    protected $isProductSalable;

    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Magento\Catalog\Model\ProductFactory $catalogProductFactory
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param Context $context
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        GetProductSalableQtyInterface $getProductSalableQty,
        IsProductSalableInterface $isProductSalable,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\App\Action\Context $context

    ) {
        $this->eventManager = $eventManager;
        $this->helper = $helper;
        $this->productFactory = $catalogProductFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->getProductSalableQty = $getProductSalableQty->get();
        $this->isProductSalable = $isProductSalable->get();
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

        $productId = $this->getRequest()->getParam('productId');
        $qty = $this->getRequest()->getParam('qty');


        if (!$productId) {
            return $result->setData([
                'error' => true,
                'messages' => __('Product ID is required'),
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

        $messages = "";
        if ($this->getProductSalableQty && $this->isProductSalable) {
            if ($this->helper->getInventoryStock() != null) {
                if($product->getTypeId() == 'simple') {
                    $qtyInStock = $this->getProductSalableQty->execute($product->getSku(), $this->helper->getInventoryStock());
                    if ($qty > $qtyInStock) {
                        return $result->setData([
                            'error' => true,
                            'messages' => $this->helper->processPlaceholders(
                                $this->helper->getAvailabilityTextOutOfStock()
                            )
                        ]);
                    } else {
                        $messages = "stock = " . $this->helper->getInventoryStock() . " " . $product->getSku() . " " . $qty . " in stock " . $qtyInStock . " " . $product->getTypeId();
                    }
                }else{
                    if($this->isProductSalable->execute($product->getSku(), $this->helper->getInventoryStock())){
                        $messages = "product is saleable";
                    }else{
                        return $result->setData([
                            'error' => true,
                            'messages' => $this->helper->processPlaceholders(
                                $this->helper->getAvailabilityTextOutOfStock()
                            )
                        ]);
                    }
                }
            } else {
                $messages = "inventory stock null";
            }
        } else {
            $messages = $e->getLogMessage() . " " . $product->getSku() . " " . $product->getTypeId();
            //probably just means MSI not supported here.
        }


        $transport = new DataObject([
            'available' => true,
        ]);
        $this->eventManager->dispatch('porterbuddy_availability', array(
            'product' => $product,
            'qty' => $qty,
            'result' => $transport,
        ));

        if ($transport->getError()) {
            return $result->setData([
                'error' => true,
                'messages' => $transport->getMessage() ?: $this->helper->processPlaceholders(
                    $this->helper->getAvailabilityTextOutOfStock()
                )
            ]);
        }

        return $result->setData([
            'error' => false,
            'available' => true,
            'messages' => $messages
        ]);
    }
}
