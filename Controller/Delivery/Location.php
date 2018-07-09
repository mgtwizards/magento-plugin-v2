<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Delivery;

use Magento\Framework\App\Action\Context;

class Location extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Porterbuddy\Porterbuddy\Model\Geoip
     */
    protected $geoip;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    public function __construct(
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Porterbuddy\Porterbuddy\Model\Geoip\Proxy $geoip,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Context $context
    ) {
        $this->helper = $helper;
        $this->geoip = $geoip;
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        if (!$this->helper->ipDiscoveryEnabled()) {
            return $result->setData([
                'error' => true,
                'messages' => __('GeoIp lookup is disabled'),
            ]);
        }

        $ip = $this->getRequest()->getClientIp();

        // In case of chained IP addresses "34.242.90.202, 127.0.0.1, 127.0.0.1", use first
        $pos = strpos($ip, ',');
        if ($pos) {
            $ip = substr($ip, 0, $pos);
        }

        try {
            $info = $this->geoip->getInfo($ip);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // don't log IP not found errors
            return $result->setData([
                'error' => true,
                'messages' => __('IP address not found in database')
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Get postcode by IP error - ' . $e->getMessage(), ['ip' => $ip]);
            $this->logger->warning($e);
            return $result->setData([
                'error' => true,
                'messages' => __('IP address lookup error'),
            ]);
        }

        // while city may be found, postcode is crucial for availability
        if (!$info->postal->code) {
            // address found but no postcode
            return $result->setData([
                'error' => true,
                'messages' => __('Postcode is unknown for IP address'),
            ]);
        } else {
            return $result->setData([
                'error' => false,
                'postcode' => $info->postal->code,
                'city' => $info->city->name,
                'country' => $info->country->name,
            ]);
        }
    }
}
