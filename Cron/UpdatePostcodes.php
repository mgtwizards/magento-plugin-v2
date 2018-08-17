<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Cron;

use Porterbuddy\Porterbuddy\Helper\Data;

class UpdatePostcodes
{
    /**
     * @var \Porterbuddy\Porterbuddy\Model\Availability
     */
    protected $availability;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param \Porterbuddy\Porterbuddy\Model\Availability $availability
     * @param Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Model\Availability $availability,
        Data $helper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->availability = $availability;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Pulls in postcodes from API
     */
    public function execute()
    {
        if (!$this->isActiveInAnyWebsite()) {
            return;
        }

        $this->logger->info('Start updating postcodes by cron');

        try {
            $this->availability->updatePostcodes();
            $this->logger->info('Postcodes have been successfully updated by cron');
        } catch (\Exception $e) {
            $this->logger->error('Postcodes update by cron failed - ' . $e->getMessage());
            // stack trace logged
        }
    }

    /**
     * @return bool
     */
    protected function isActiveInAnyWebsite()
    {
        foreach ($this->storeManager->getWebsites() as $website) {
            if ($this->helper->getActive($website->getCode())) {
                return true;
            }
        }

        return false;
    }
}
