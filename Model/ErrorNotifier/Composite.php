<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\ErrorNotifier;

class Composite implements NotifierInterface
{
    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var NotifierInterface[]
     */
    protected $notifiers;

    /**
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     * @param NotifierInterface[] $notifiers
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger,
        array $notifiers
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->notifiers = $notifiers;
    }

    public function notify(
        \Exception $exception,
        \Magento\Sales\Model\Order\Shipment $shipment,
        \Magento\Shipping\Model\Shipment\Request $request = null
    ) {
        foreach ($this->notifiers as $code => $notifier) {
            if (!$notifier instanceof NotifierInterface) {
                $this->logger->error(
                    "Error notifier `$code` must implement " . NotifierInterface::class
                );
                continue;
            }

            $notifier->notify($exception, $shipment, $request);
        }
    }
}
