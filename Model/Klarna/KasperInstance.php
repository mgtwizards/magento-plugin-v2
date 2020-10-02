<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Klarna;

use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class KasperFactory
 * @package Model\Klarna
 */
class KasperInstance
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
     * @return \Klarna\Kco\Model\Api\Builder\Kasper|null
     */
    public function get()
    {
        $instanceName = \Klarna\Kco\Model\Api\Builder\Kasper::class;
        if ($this->moduleManager->isEnabled('Klarna_Kco') && class_exists($instanceName)) {
                return $this->objectManager->get($instanceName);
        }
        return null;
    }
}
