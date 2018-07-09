<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\Exception\LocalizedException;
use Porterbuddy\Porterbuddy\Exception;

class Geoip
{
    protected $cacheType = Config::TYPE_IDENTIFIER;

    const CACHE_TAG = 'geoip';

    const NOT_FOUND = 'not_found';

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * Cache State
     *
     * @var \Magento\Framework\App\Cache\StateInterface
     */
    protected $cacheState;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Tobai\GeoIp2\Model\Database\ReaderFactory
     */
    protected $readerFactory;

    /**
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Framework\App\Cache\StateInterface $cacheState
     * @param \Porterbuddy\Porterbuddy\Helper\Data $helper
     * @param \Tobai\GeoIp2\Model\Database\ReaderFactory $readerFactory
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     */
    public function __construct(
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\App\Cache\StateInterface $cacheState,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Tobai\GeoIp2\Model\Database\ReaderFactory $readerFactory
    ) {
        $this->cache = $cache;
        $this->cacheState = $cacheState;
        $this->helper = $helper;
        $this->localeResolver = $localeResolver;
        $this->readerFactory = $readerFactory;
    }

    /**
     * @param string $ip
     * @return \GeoIp2\Model\City
     * @throws \GeoIp2\Exception\AddressNotFoundException
     * @throws \MaxMind\Db\Reader\InvalidDatabaseException
     * @throws \Porterbuddy\Porterbuddy\Exception
     * @throws LocalizedException
     */
    public function getInfo($ip)
    {
        if (!$this->readerFactory) {
            throw new Exception(__('Geoip database reader is not installed'));
        }

        $cacheEnabled = $this->cacheState->isEnabled($this->cacheType);
        $cacheId = "GEOIP_INFO_IP_$ip";
        if ($cacheEnabled && $info = $this->cache->load($cacheId)) {
            $info = unserialize($info);
        } else {
            list($locale) = explode('_', $this->localeResolver->getLocale(), 2);
            $reader = $this->readerFactory->create('city', [$locale, 'en']);

            try {
                $info = $reader->city($ip);
            } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
                // cache not found exceptions, throw later
                $info = self::NOT_FOUND;
            }

            if ($cacheEnabled) {
                $this->cache->save(serialize($info), $cacheId, [Config::CACHE_TAG, self::CACHE_TAG]);
            }
        }

        if (self::NOT_FOUND === $info) {
            throw new \GeoIp2\Exception\AddressNotFoundException();
        }

        return $info;
    }
}
