<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

use Magento\Framework\DataObject;
use Porterbuddy\Porterbuddy\Api\Data\MethodInfoInterface;

class Timeslots
{
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Porterbuddy\Porterbuddy\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    /**
     * @param array|null $data
     * @param \Porterbuddy\Porterbuddy\Helper\Data|null $helper
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Porterbuddy\Porterbuddy\Helper\Data $helper,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
    ) {
        $this->eventManager = $eventManager;
        $this->helper = $helper;
        $this->localeDate = $localeDate;
    }

    /**
     * Returns open hours range in UTC timezone
     *
     * @param \DateTime
     * @return \DateTime[]|false Date range or false if not working
     */
    public function getOpenHours(\DateTime $baseDate)
    {
        $localTimezone = $this->helper->getTimezone();
        $defaultTimezone = new \DateTimeZone('UTC');

        // ensure local timezone
        $baseDate = clone $baseDate;
        $baseDate->setTimezone($localTimezone);

        $openHours = $this->helper->getOpenHours(strtolower($baseDate->format('D')));
        if (false === $openHours) {
            // not working
            return false;
        }

        $openTime = $openHours['open'];
        $closeTime = $openHours['close'];

        // set time in local timezone and convert to UTC
        $openDatetime = clone $baseDate;
        $parts = explode(':', $openTime);
        $openDatetime->setTimezone($localTimezone);
        $openDatetime->setTime($parts[0], $parts[1], 0);
        $openDatetime->setTimezone($defaultTimezone);

        $closeDatetime = clone $baseDate;
        $parts = explode(':', $closeTime);
        $closeDatetime->setTimezone($localTimezone);
        $closeDatetime->setTime($parts[0], $parts[1], 0);
        $closeDatetime->setTimezone($defaultTimezone);

        if ($openDatetime >= $closeDatetime) {
            // misconfig, treat as day off
            return false;
        }

        return [
            'open' => $openDatetime,
            'close' => $closeDatetime,
        ];
    }

    /**
     * Generate pickup windows as large as possible over several days
     *
     * @param \DateTime|null $deliveryDate
     * @param int $extraWindows
     * @return array
     */
    public function getAvailabilityPickupWindows()
    {
        // generate up to delivery date + extra windows
        $windows = [];
        $currentTime = $this->helper->getCurrentTime();
        $date = $this->helper->getCurrentTime();

        $addedExtra = 0;
        $triedExtra = 0;
        $extraWindows = $this->helper->getDaysAhead();
        while ($addedExtra < $extraWindows) {
            $hours = $this->getOpenHours($date);
            if ($hours && $currentTime < $hours['close']) {
                $hours['open'] = max($hours['open'], $currentTime);
                $windows[] = [
                    'start' => $hours['open'],
                    'end' => $hours['close'],
                ];
                $addedExtra++;
            }
            $date->modify('+1 day');
            if ($triedExtra++ > 20) {
                // prevent infinite loop in case of misconfigured working hours
                break;
            }
        }

        // add packing time to first window
        $addTime = $this->helper->getPackingTime() + $this->helper->getRefreshOptionsTimeout();
        /** @var \DateTime[] $window */
        $window = reset($windows);
        if ($window) {
            $window['start']->modify("+$addTime minutes");
        }

        // convert to API formst
        $windows = array_map(function ($window) {
            return [
                'start' => $this->helper->formatApiDateTime($window['start']),
                'end' => $this->helper->formatApiDateTime($window['end']),
            ];
        }, $windows);

        return $windows;
    }

    /**
     * Generate pickup windows as large as possible over several days
     *
     * @param MethodInfoInterface $methodInfo
     * @return array
     */
    public function getPickupWindows(MethodInfoInterface $methodInfo)
    {
        // generate up to delivery date + extra windows
        $windows = [];
        $currentTime = $this->helper->getCurrentTime();
        $date = $this->helper->getCurrentTime();

        // can be unknown if method delivery with pickup timeslots later
        if ($methodInfo->getStart()) {
            $deliveryDate = new \DateTime($methodInfo->getStart());
            while ($date <= $deliveryDate) {
                $hours = $this->getOpenHours($date); // 09-18
                if ($hours && $currentTime < $hours['close']) {
                    // don't send 9 am when it's already 13
                    $hours['open'] = max($hours['open'], $currentTime);
                    $windows[] = [
                        'start' => $hours['open'],
                        'end' => $hours['close'],
                    ];
                }
                $date->modify('+1 day');
            }
        }

        $addedExtra = 0;
        $triedExtra = 0;
        $extraWindows = $this->helper->getExtraPickupWindows();
        while ($addedExtra < $extraWindows) {
            $hours = $this->getOpenHours($date);
            if ($hours && $currentTime < $hours['close']) {
                $hours['open'] = max($hours['open'], $currentTime);
                $windows[] = [
                    'start' => $hours['open'],
                    'end' => $hours['close'],
                ];
                $addedExtra++;
            }
            $date->modify('+1 day');
            if ($triedExtra++ > 20) {
                // prevent infinite loop in case of misconfigured working hours
                break;
            }
        }

        // add packing time to first window
        $packingTime = $this->helper->getPackingTime();
        /** @var \DateTime[] $window */
        $window = reset($windows);
        if ($window) {
            $window['start']->modify("+$packingTime minutes");
        }

        // convert to API formst
        $windows = array_map(function ($window) {
            return [
                'start' => $this->helper->formatApiDateTime($window['start']),
                'end' => $this->helper->formatApiDateTime($window['end']),
            ];
        }, $windows);

        return $windows;
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
            $openHours = $this->getOpenHours($date);

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
     * Formats timeslot title, e.g. "Friday 10:00 - 12:00" or "Today 14:00 - 16:00"
     *
     * @param MethodInfoInterface $methodInfo
     * @param bool $moreInfo
     * @return string
     */
    public function formatTimeslot(MethodInfoInterface $methodInfo, $moreInfo = true)
    {
        // local time - shift timezone
        $timezone = $this->helper->getTimezone();
        $startTime = new \DateTime($methodInfo->getStart(), $timezone);
        $endTime = new \DateTime($methodInfo->getEnd(), $timezone);

        $parts = [];

        if ($moreInfo) {
            $today = $this->helper->getCurrentTime();
            $tomorrow = clone $today;
            $tomorrow->modify('+1 day');

            if (Carrier::METHOD_EXPRESS == $methodInfo->getType()) {
                $dayOfWeek = $this->helper->getAsapName();
            } elseif ($startTime->format('Y-m-d') == $today->format('Y-m-d')) {
                $dayOfWeek = __('Today');
            } elseif ($startTime->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
                $dayOfWeek = __('Tomorrow');
            } else {
                $dayOfWeek = __($startTime->format('l'));
            }

            $parts[] = $dayOfWeek;
        }

        $parts[] = $startTime->format('H:i') . '–' . $endTime->format('H:i');

        if ($moreInfo && $methodInfo->isReturn()) {
            $parts[] = $this->helper->getReturnShortText();
        }

        return implode(' ', $parts);
    }
}
