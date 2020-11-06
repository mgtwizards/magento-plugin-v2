<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use Porterbuddy\Porterbuddy\Exception;
use Porterbuddy\Porterbuddy\Exception\ApiException;

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
     * @throws Exception
     * @throws ApiException
     * @throws \Exception
     */
    public function getAvailability(array $parameters)
    {
        $apiKey = $this->helper->getApiKey();
        if (!strlen($apiKey)) {
            throw new Exception(__('Porterbuddy API key must be configured.'));
        }

        $httpClient = $this->clientFactory->create();
        $uri = $this->helper->getApiUrl() . '/availability';

        $logData = [
            'api_url' => $uri,
            'api_key' => $apiKey,
            'parameters' => $parameters,
        ];

        $headers = [
            'x-api-key' => $apiKey,
            'Content-type' => 'application/json',
        ];
        $httpClient->setHeaders($headers);
        $httpClient->setTimeout($this->helper->getApiTimeout());

        try {
            $httpClient->post($uri, json_encode($parameters));
        } catch (\Exception $e) {
            $this->logger->error(__FUNCTION__ . ' error - ' . $e->getMessage(), $logData);
            $this->logger->error($e);
            throw new ApiException(
                __('Connection error - %1', $e->getMessage()),
                $logData,
                $e
            );
        }

        $logData['status'] = $httpClient->getStatus();
        $logData['response'] = $httpClient->getBody();

        $data = json_decode($httpClient->getBody(), true);

        if ($data) {
            // log decoded
            $logData['response'] = $data;
        }

        if (isset($data['deliveryWindows'])) {
            $this->logger->debug(__FUNCTION__ . ' success', $logData);
            return $data;
        }

        $message = __('Get availability options error');
        $this->logger->error(__FUNCTION__ . ' error', $logData);

        if (/*422 === $httpClient->getStatus() && */is_array($data)) {
            $errors = [];
            foreach ($data as $error) {
                if (isset($error['message'], $error['propertyPath'])) {
                    $errors[] = __("%1 {$error['message']}", $error['propertyPath']);
                }
            }
            $message = __($message . ' - ' . implode(', ', $errors));
        }

        throw new ApiException($message);
    }

    /**
     * Create new order
     *
     * @param array $parameters
     * @param string $idempotencyKey optional
     * @return array
     * @throws \Zend_Http_Client_Exception
     * @throws Exception
     * @throws ApiException
     */
    public function createOrder(array $parameters, $idempotencyKey = null)
    {
        $apiKey = $this->helper->getApiKey();
        if (!strlen($apiKey)) {
            throw new Exception(__('Porterbuddy API key must be configured.'));
        }

        $httpClient = $this->clientFactory->create();
        $uri = $this->helper->getApiUrl() . '/order';

        $logData = [
            'api_url' => $uri,
            'api_key' => $apiKey,
            'parameters' => $parameters,
            'idempotency_key' => $idempotencyKey,
        ];

        $headers = [
            'x-api-key' => $apiKey,
            'Content-type' => 'application/json',
        ];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $httpClient->setHeaders($headers);
        $httpClient->setTimeout($this->helper->getApiTimeout());

        try {
            $httpClient->post($uri, json_encode($parameters));
        } catch (\Exception $e) {
            $this->logger->error(__FUNCTION__ . ' error - ' . $e->getMessage(), $logData);
            $this->logger->error($e);
            throw new ApiException(
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

        throw new ApiException($message, $logData);
    }

}
