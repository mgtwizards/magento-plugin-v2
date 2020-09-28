<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Klarna;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Module\Manager;
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
     * @var Manager
     */
    private $moduleManager;

    /**
     * KasperFactory constructor.
     *
     * @param Manager $moduleManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        Manager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @return \Klarna\Kco\Model\Checkout\Kco\Address|null
     */
    public function get()
    {
        $instanceName = \Klarna\Kco\Model\Checkout\Kco\Address::class;
        if ($this->moduleManager->isEnabled('Klarna_Kco') && class_exists($instanceName)) {
            return $this->objectManager->get($instanceName);
        }
        return null;
    }
}
