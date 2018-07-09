<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

class Api
{
    /**
     * @var \Magento\Framework\HTTP\ClientFactory
     */
    protected $clientFactory;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Magento\Framework\HTTP\ClientFactory $clientFactory,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Retrieves delivery options via API
     *
     * @param array $parameters
     * @return array
     * @throws \Porterbuddy\Porterbuddy\Exception
     * @throws \Exception
     */
    public function getAvailability(array $parameters)
    {
        $apiKey = $this->helper->getApiKey();
        if (!strlen($apiKey)) {
            throw new \Porterbuddy\Porterbuddy\Exception(__('Porterbuddy API key must be configured.'));
        }

        $httpClient = $this->clientFactory->create();
        $uri = $this->helper->getApiUrl() . '/availability';

        $logData = [
            'api_url' => $uri,
            'api_key' => $apiKey,
            'parameters' => $parameters,
        ];

        $httpClient->setHeaders([
            'x-api-key' => $apiKey,
            'Content-type' => 'application/json',
        ]);
        $httpClient->setTimeout($this->helper->getApiTimeout());

        try {
            $httpClient->post($uri, json_encode($parameters));
        } catch (\Exception $e) {
            $this->logger->error('getAvailability error - ' . $e->getMessage(), $logData);
            $this->logger->error($e);
            throw $e;
        }

        $logData['status'] = $httpClient->getStatus();
        $logData['response'] = $httpClient->getBody();

        $data = json_decode($httpClient->getBody(), true);

        if ($data) {
            // log decoded
            $logData['response'] = $data;
        }

        if (isset($data['deliveryWindows'])) {
            $this->logger->debug('getAvailability success', $logData);
            return $data['deliveryWindows'];
        }

        $message = __('Get availability options error');
        $this->logger->error('getAvailability error', $logData);

        if (/*422 === $httpClient->getStatus() && */is_array($data)) {
            $errors = [];
            foreach ($data as $error) {
                if (isset($error['message'], $error['propertyPath'])) {
                    $errors[] = __("%1 {$error['message']}", $error['propertyPath']);
                }
            }
            $message = __($message . ' - ' . implode(', ', $errors));
        }

        throw new \Porterbuddy\Porterbuddy\Exception($message);
    }

    /**
     * Create new order
     *
     * @param array $parameters
     * @return array
     * @throws \Zend_Http_Client_Exception
     * @throws \Porterbuddy\Porterbuddy\Exception
     * @throws \Porterbuddy\Porterbuddy\Exception\ApiException
     */
    public function createOrder(array $parameters)
    {
        $apiKey = $this->helper->getApiKey();
        if (!strlen($apiKey)) {
            throw new \Porterbuddy\Porterbuddy\Exception(__('Porterbuddy API key must be configured.'));
        }

        $httpClient = $this->clientFactory->create();
        $uri = $this->helper->getApiUrl() . '/order';

        $logData = [
            'api_url' => $uri,
            'api_key' => $apiKey,
            'parameters' => $parameters,
        ];

        $httpClient->setHeaders([
            'x-api-key' => $apiKey,
            'Content-type' => 'application/json',
        ]);
        $httpClient->setTimeout($this->helper->getApiTimeout());

        try {
            $httpClient->post($uri, json_encode($parameters));
        } catch (\Exception $e) {
            $this->logger->error('createOrder error', $logData);
            $this->logger->error($e);
            throw new \Porterbuddy\Porterbuddy\Exception\ApiException(
                __('Connection error - %1', $e->getMessage()),
                $logData,
                $e
            );
        }

        $logData['status'] = $httpClient->getStatus();
        $logData['response'] = $httpClient->getBody();

        $data = json_decode($httpClient->getBody(), true);

        if (!empty($data['orderId'])) { // status 100 or 200
            $this->logger->notice('createOrder success', $logData);
            return $data;
        }

        $message = __('Create order error');
        $this->logger->error('createOrder error', $logData, \Zend_Log::ERR);

        if (/*422 === $httpClient->getStatus() && */is_array($data)) {
            $errors = [];
            foreach ($data as $error) {
                if (isset($error['message'], $error['propertyPath'])) {
                    $errors[] = __("%1 {$error['message']}", $error['propertyPath']);
                }
            }
            $message = __($message . ' - ' . implode(', ', $errors));
        }

        throw new \Porterbuddy\Porterbuddy\Exception\ApiException($message, $logData);
    }
}
