<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use Magento\Framework\DataObject;
use Porterbuddy\Porterbuddy\Helper\Data;
use Porterbuddy\Porterbuddy\Model\Packager\PackagerInterface;
use Psr\Log\LoggerInterface;

class Packager
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $packagers;

    /**
     * @param Data $helper
     * @param LoggerInterface $logger
     * @param array $packagers
     */
    public function __construct(
        Data $helper,
        LoggerInterface $logger,
        array $packagers = []
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->packagers = $packagers;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array
     * @throws \Porterbuddy\Porterbuddy\Exception
     */
    public function estimateParcels(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $mode = $this->helper->getPackagerMode();
        $packager = $this->getPackager($mode);

        return $packager->estimateParcels($request);
    }

    /**
     * Create package automatically when estimating availability and submitting shipment
     *
     * @param \Magento\Shipping\Model\Shipment\Request $request
     * @return array Packages in Magento format
     * @throws \Porterbuddy\Porterbuddy\Exception
     */
    public function createPackages(\Magento\Shipping\Model\Shipment\Request $request)
    {
        $mode = $this->helper->getPackagerMode();
        $packager = $this->getPackager($mode);

        return $packager->createPackages($request);
    }

    /**
     * @return array code => configuration
     */
    public function getModes()
    {
        return $this->packagers;
    }

    /**
     * @param $mode
     * @return PackagerInterface
     * @throws \Porterbuddy\Porterbuddy\Exception
     */
    protected function getPackager($mode)
    {
        if (!isset($this->packagers[$mode])) {
            $this->logger->warning("Packager mode `$mode` does not exist.");
            $mode = Packager\Peritem::MODE;
        }

        if (!$this->packagers[$mode]['model'] instanceof Packager\PackagerInterface) {
            throw new \Porterbuddy\Porterbuddy\Exception(__(
                'Packager for mode `%1` must implement `%2`',
                $mode,
                Packager\PackagerInterface::class
            ));
        }
        return $this->packagers[$mode]['model'];
    }

    /**
     * Converts Magento shipment packages to Porterbuddy parcels format
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getParcelsFromPackages(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $packages = $shipment->getPackages();
        if ($packages && is_scalar($packages)) {
            $packages = unserialize($packages);
        }

        $parcels = [];
        foreach ($packages as $package) {
            $package = new DataObject($package);
            $description = $package->getData('params/content_type_other');
            if (!$description && is_array($package->getData('items'))) {
                $lines = [];
                foreach ($package->getData('items') as $item) {
                    if (isset($item['qty'], $item['name'])) {
                        $qty = $item['qty'];
                        $name = $item['name'];
                        $lines[] = $qty > 1 ? "$qty x $name" : $name;
                    }
                }
                $description = implode(', ', $lines);
            }
            if (!$description) {
                $description = __('%1 products', count($shipment->getAllItems()));
            }

            $parcels[] = [
                'description' => $description,
                'widthCm' => $this->helper->convertDimensionToCm(
                    $package->getData('params/width'),
                    $package->getData('params/dimension_units')
                ) ?: $this->helper->getDefaultProductWidth(),
                'heightCm' => $this->helper->convertDimensionToCm(
                    $package->getData('params/height'),
                    $package->getData('params/dimension_units')
                ) ?: $this->helper->getDefaultProductHeight(),
                'depthCm' => $this->helper->convertDimensionToCm(
                    $package->getData('params/length'),
                    $package->getData('params/dimension_units')
                ) ?: $this->helper->getDefaultProductLength(),
                'weightGrams' => $this->helper->convertWeightToGrams(
                    $package->getData('params/weight'),
                    $package->getData('params/weight_unit')
                ) ?: $this->helper->getDefaultProductWeight(),
            ];
        }

        return $parcels;
    }
}
