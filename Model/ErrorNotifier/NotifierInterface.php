<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\ErrorNotifier;

interface NotifierInterface
{
    public function notify(
        \Exception $exception,
        \Magento\Sales\Model\Order\Shipment $shipment,
        \Magento\Shipping\Model\Shipment\Request $request = null
    );
}
