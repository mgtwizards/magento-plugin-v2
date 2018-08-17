<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\ScopeInterface;
use Porterbuddy\Porterbuddy\Helper\Data;

class Availability
{
    const FLAG_POSTCODES_UPDATED = 'porterbuddy_postcodes_updated';

    /**
     * @var \Porterbuddy\Porterbuddy\Model\Api
     */
    protected $api;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory
     */
    protected $configCollectionFactory;

    /**
     *  @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Reports\Model\FlagFactory
     */
    protected $flagFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Porterbuddy\Porterbuddy\Model\Timeslots
     */
    protected $timeslots;

    /**
     * @param Api $api
     * @param \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Reports\Model\FlagFactory $flagFactory
     * @param Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param Timeslots $timeslots
     */
    public function __construct(
        Api $api,
        \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Reports\Model\FlagFactory $flagFactory,
        Data $helper,
        \Psr\Log\LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        Timeslots $timeslots
    ) {
        $this->api = $api;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->configWriter = $configWriter;
        $this->eventManager = $eventManager;
        $this->flagFactory = $flagFactory;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->timeslots = $timeslots;
    }


    /**
     * @return array
     */
    public function getPostcodes()
    {
        // read directly from DB since cron can update value but can't clear config cache
        /** @var \Magento\Config\Model\ResourceModel\Config\Data\Collection $collection */
        $collection = $this->configCollectionFactory->create();
        $collection->addFieldToFilter('scope', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $collection->addFieldToFilter('scope_id', 0);
        $collection->addFieldToFilter('path', Data::XML_PATH_POSTCODES);
        $postcodes = $collection->getFirstItem()->getValue();

        $postcodes = preg_split('/(\r\n|\n)/', $postcodes);
        $postcodes = array_map(function ($row) {
            // normalize format, remove leading 0, e.g. 0563 = 563
            $row = trim($row);
            $row = ltrim($row, '0');
            return strlen($row) ? $row : false;
        }, $postcodes);
        $postcodes = array_filter($postcodes);

        return $postcodes;
    }

    /**
     * @param string $postcode
     * @return bool
     */
    public function isPostcodeSupported($postcode)
    {
        $postcodes = $this->getPostcodes();
        if (!$postcodes) {
            // no restrictions
            return true;
        }

        // normalize format, remove leading 0, e.g. 0563 = 563
        $postcode = trim($postcode);
        $postcode = ltrim($postcode, '0');

        return in_array($postcode, $postcodes);
    }

    /**
     * Returns date for formula "Want it {date}? Order in the next {N} hours"
     *
     * @return \DateTime|false in *local* timezone for convenience
     */
    public function getAvailableUntil()
    {
        $date = $this->helper->getCurrentTime();

        // protect against misconfiguration
        for ($i = 0; $i < 10; $i++) {
            $openHours = $this->timeslots->getOpenHours($date);

            if ($openHours) {
                // Porterbuddy works until is set in local timezone
                $porterbuddyWorksUntil = clone $openHours['close'];

                $minutes = $this->helper->getPorterbuddyUntil(strtolower($date->format('D')));
                $porterbuddyWorksUntil->modify("-{$minutes} minutes");

                if ($date < $porterbuddyWorksUntil) {
                    /** @var \DateTime $result */
                    $result = $porterbuddyWorksUntil;
                    $result->setTimezone($this->helper->getTimezone());
                    return $result;
                }
            }

            // for future days, we don't need specific opening hour, just make sure it's before closing hour
            $date
                ->modify('+1 day')
                ->setTime(0, 0, 0);
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function updatePostcodes()
    {
        // TODO: by website

        try {
            $parameters = $this->preparePostcodesParameters();
            $result = $this->api->getAllAvailability($parameters);
        } catch (\Exception $e) {
            $this->logger->error($e);
            throw $e;
        }

        // extract postcodes
        $postcodes = array_keys($result);

        $this->configWriter->save(
            Data::XML_PATH_POSTCODES,
            implode("\n", $postcodes),
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        $this->setPostcodesUpdated(new \DateTime());
    }

    /**
     * @return array|mixed
     */
    public function preparePostcodesParameters()
    {
        $params = [];
        $params['pickupWindows'] = $this->timeslots->getAvailabilityPickupWindows();

        $originStreet1 = $this->scopeConfig->getValue(Shipment::XML_PATH_STORE_ADDRESS1, ScopeInterface::SCOPE_STORE);
        $originStreet2 = $this->scopeConfig->getValue(Shipment::XML_PATH_STORE_ADDRESS2, ScopeInterface::SCOPE_STORE);
        $params['originAddress'] = [
            'streetName' => trim("$originStreet1 $originStreet2"),
            'streetNumber' => ',', // FIXME: set empty when API is updated
            'postalCode' => $this->scopeConfig->getValue(Shipment::XML_PATH_STORE_ZIP, ScopeInterface::SCOPE_STORE),
            'city' => $this->scopeConfig->getValue(Shipment::XML_PATH_STORE_CITY, ScopeInterface::SCOPE_STORE),
            'country' => $this->scopeConfig->getValue(Shipment::XML_PATH_STORE_COUNTRY_ID, ScopeInterface::SCOPE_STORE),
        ];
        $params['products'] = [Carrier::METHOD_DELIVERY];

        $transport = new DataObject(['params' => $params]);
        $this->eventManager->dispatch('porterbuddy_availability_all_data', [
            'transport' => $transport,
        ]);
        $params = $transport->getData('params');

        return $params;
    }

    /**
     * @return \DateTime|null
     */
    public function getPostcodesUpdated()
    {
        /** @var \Magento\Reports\Model\Flag $flag */
        $flag = $this->flagFactory->create([
            'data' => ['flag_code' => self::FLAG_POSTCODES_UPDATED],
        ])->loadSelf();
        $updated = $flag->getFlagData();

        if ($updated) {
            try {
                $updated = \DateTime::createFromFormat(\DateTime::ATOM, $updated);
            } catch (\Exception $e) {
                // not a big deal
            }
        }

        return $updated;
    }

    /**
     * @param \DateTime $dateTime
     * @return void
     * @throws \Exception
     */
    public function setPostcodesUpdated(\DateTime $dateTime)
    {
        /** @var \Magento\Reports\Model\Flag $flag */
        $flag = $this->flagFactory->create([
            'data' => ['flag_code' => self::FLAG_POSTCODES_UPDATED],
        ])->loadSelf();
        $flag->setFlagData($dateTime->format(\DateTime::ATOM));
        $flag->save();
    }
}
