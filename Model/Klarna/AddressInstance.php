<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Klarna;

use Magento\Framework\ObjectManagerInterface;

/**
 * Class KasperFactory
 * @package Model\Klarna
 */
class AddressInstance
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * KasperFactory constructor.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @return \Klarna\Kco\Model\Checkout\Kco\Address|null
     */
    public function get()
    {
        $instanceName = \Klarna\Kco\Model\Checkout\Kco\Address::class;
        if (class_exists($instanceName)) {
            return $this->objectManager->get($instanceName);
        }
        return null;
    }
}
