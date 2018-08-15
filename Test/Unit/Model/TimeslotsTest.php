<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Test\Unit\Model;

use Porterbuddy\Porterbuddy\Helper\Data;
use Porterbuddy\Porterbuddy\Model\MethodInfo;
use Porterbuddy\Porterbuddy\Model\Timeslots;

class TimeslotsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Timeslots
     */
    protected $timeslots;

    /**
     * @var Data|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $helper;

    protected $openHours = array(
        'mon' => array('open' => '09:00', 'close' => '18:00'), // 07:00-16:00 UTC
        'tue' => array('open' => '09:00', 'close' => '18:00'),
        'wed' => array('open' => '09:00', 'close' => '18:00'),
        'thu' => array('open' => '09:00', 'close' => '18:00'),
        'fri' => array('open' => '09:00', 'close' => '18:00'),
        'sat' => array('open' => '10:00', 'close' => '16:00'), // 08:00-14:00 UTC
        'sun' => false, // closed
    );

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->helper = $this->getMockBuilder(Data::class)
            ->setMethodsExcept(['formatApiDateTime'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper
            ->method('getTimezone')
            ->willReturn(new \DateTimeZone('Europe/Warsaw'));
        $this->helper
            ->method('getExtraPickupWindows')
            ->willReturn(3);

        $objectManagerHelper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->timeslots = $objectManagerHelper->getObject(Timeslots::class, [
            'helper' => $this->helper,
        ]);
    }

    /**
     * @param string $description
     * @param string $now
     * @param array $methodInfo
     * @param array $expected
     *
     * @dataProvider getPickupWindowsProvider
     */
    public function testGetPickupWindows($description, $now, $methodInfo, array $expected)
    {
        $this->helper
            ->method('getCurrentTime')
            ->willReturnCallback(function() use ($now) {
                return new \DateTime($now);
            });
        $this->helper
            ->method('getOpenHours')
            ->willReturnCallback(function($dayOfWeek) {
                return $this->openHours[$dayOfWeek];
            });
        $this->helper
            ->method('getPackingTime')
            ->willReturn(15);

        $methodInfo = new MethodInfo($methodInfo);

        $result = $this->timeslots->getPickupWindows($methodInfo);
        $this->assertEquals(
            $result,
            $expected,
            $description
        );
    }

    public function getPickupWindowsProvider()
    {
        return array(
            array(
                'About to close',
                // now
                '2018-08-15T17:53:00+02:00',
                // method info
                array(
                    'type' => 'delivery',
                    'start' => '2018-08-17T16:00:00+02:00',
                    'end' => '2018-08-17T18:00:00+02:00',
                    'return' => false,
                ),
                // expected
                array(
                    array ('start' => '2018-08-16T09:15:00+02:00', 'end' => '2018-08-16T18:00:00+02:00'),
                    array ('start' => '2018-08-17T09:00:00+02:00', 'end' => '2018-08-17T18:00:00+02:00'),
                    array ('start' => '2018-08-18T10:00:00+02:00', 'end' => '2018-08-18T16:00:00+02:00'),
                    array ('start' => '2018-08-20T09:00:00+02:00', 'end' => '2018-08-20T18:00:00+02:00'),
                ),
            ),
            array(
                'Espresso in the morning',
                // now Wed 10:22 CET - 2 minutes after availability request (before options refresh)
                '2018-06-06T08:22:00+00:00',
                // method info
                array(
                    'type' => 'express',
                    'start' => '2018-06-06T10:40:00+02:00',
                    'end' => '2018-06-06T12:00:00+02:00',
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-06-06T10:37:00+02:00', 'end' => '2018-06-06T18:00:00+02:00'),
                    array('start' => '2018-06-07T09:00:00+02:00', 'end' => '2018-06-07T18:00:00+02:00'),
                    array('start' => '2018-06-08T09:00:00+02:00', 'end' => '2018-06-08T18:00:00+02:00'),
                    array('start' => '2018-06-09T10:00:00+02:00', 'end' => '2018-06-09T16:00:00+02:00'), // sat, short
                ),
            ),
            array(
                'Order late evening, pickup next opening hour + packing time',
                // now Mon 21:23 CET
                '2018-06-04T19:23:00+00:00',
                // method info
                array(
                    'type' => 'delivery',
                    'start' => '2018-06-05T15:00:00+02:00',
                    'end' => '2018-06-05T17:00:00+02:00',
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-06-05T09:15:00+02:00', 'end' => '2018-06-05T18:00:00+02:00'), // +3
                    array('start' => '2018-06-06T09:00:00+02:00', 'end' => '2018-06-06T18:00:00+02:00'),
                    array('start' => '2018-06-07T09:00:00+02:00', 'end' => '2018-06-07T18:00:00+02:00'),
                ),
            ),
            array(
                'Order now, pickup now + packing time',
                // now Tue 11:00 CET
                '2018-05-08T09:00:00+00:00',
                // method info
                array(
                    'type' => 'delivery',
                    'start' => '2018-05-08T13:00:00+02:00',
                    'end' => '2018-05-08T15:00:00+02:00',
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-05-08T11:15:00+02:00', 'end' => '2018-05-08T18:00:00+02:00'), // Today
                    array('start' => '2018-05-09T09:00:00+02:00', 'end' => '2018-05-09T18:00:00+02:00'), // +3
                    array('start' => '2018-05-10T09:00:00+02:00', 'end' => '2018-05-10T18:00:00+02:00'),
                    array('start' => '2018-05-11T09:00:00+02:00', 'end' => '2018-05-11T18:00:00+02:00'),
                ),
            ),
            array(
                'Delivery, select timeslot later',
                // now Tue 11:00 CET
                '2018-05-08T09:00:00+00:00',
                // method info
                array(
                    'type' => 'delivery',
                    'start' => null,
                    'end' => null,
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-05-08T11:15:00+02:00', 'end' => '2018-05-08T18:00:00+02:00'), // Today
                    array('start' => '2018-05-09T09:00:00+02:00', 'end' => '2018-05-09T18:00:00+02:00'), // +3
                    array('start' => '2018-05-10T09:00:00+02:00', 'end' => '2018-05-10T18:00:00+02:00'),
                ),
            ),
            array(
                'Order at night, pickup next opening hour + packing time',
                // now Tue 05:00 CET
                '2018-05-08T02:00:00+00:00',
                // method info
                array(
                    'type' => 'delivery',
                    'start' => '2018-05-08T13:00:00+02:00',
                    'end' => '2018-05-08T15:00:00+02:00',
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-05-08T09:15:00+02:00', 'end' => '2018-05-08T18:00:00+02:00'), // Today
                    array('start' => '2018-05-09T09:00:00+02:00', 'end' => '2018-05-09T18:00:00+02:00'), // +3
                    array('start' => '2018-05-10T09:00:00+02:00', 'end' => '2018-05-10T18:00:00+02:00'),
                    array('start' => '2018-05-11T09:00:00+02:00', 'end' => '2018-05-11T18:00:00+02:00'),
                ),
            ),
            array(
                'Order on Tue morning, deliver during the day, pickup today +3 days',
                // now Tue 11:00 CET
                '2018-05-08T09:00:00+00:00',
                // method info
                array(
                    'type' => 'delivery', // Tue 13:00-15:00 CET
                    'start' => '2018-05-08T13:00:00+02:00',
                    'end' => '2018-05-08T15:00:00+02:00',
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-05-08T11:15:00+02:00', 'end' => '2018-05-08T18:00:00+02:00'), // Today
                    array('start' => '2018-05-09T09:00:00+02:00', 'end' => '2018-05-09T18:00:00+02:00'), // +3
                    array('start' => '2018-05-10T09:00:00+02:00', 'end' => '2018-05-10T18:00:00+02:00'),
                    array('start' => '2018-05-11T09:00:00+02:00', 'end' => '2018-05-11T18:00:00+02:00'),
                ),
            ),
            array(
                'Order on Fri, deliver on Mon +3 days',
                // now Fri 11:00 CET
                '2018-05-11T09:00:00+00:00',
                // method info
                array(
                    'type' => 'delivery', // Mon 13:00-15:00 CET
                    'start' => '2018-05-14T13:00:00+02:00',
                    'end' => '2018-05-14T15:00:00+02:00',
                    'return' => false
                ),
                array(
                    array('start' => '2018-05-11T11:15:00+02:00', 'end' => '2018-05-11T18:00:00+02:00'), // Today
                    array('start' => '2018-05-12T10:00:00+02:00', 'end' => '2018-05-12T16:00:00+02:00'), // Sat
                    // Sun holiday
                    array('start' => '2018-05-14T09:00:00+02:00', 'end' => '2018-05-14T18:00:00+02:00'), // Mon
                    array('start' => '2018-05-15T09:00:00+02:00', 'end' => '2018-05-15T18:00:00+02:00'), // +3
                    array('start' => '2018-05-16T09:00:00+02:00', 'end' => '2018-05-16T18:00:00+02:00'),
                    array('start' => '2018-05-17T09:00:00+02:00', 'end' => '2018-05-17T18:00:00+02:00'),
                ),
            ),
            array(
                'Express delivery',
                // now Tue 10:30 CET
                '2018-05-08T08:30:00+00:00',
                // method info
                array(
                    'type' => 'express',
                    'start' => '2018-05-08T12:30:00+02:00',
                    'end' => '2018-05-08T14:30:00+02:00',
                    'return' => false
                ),
                // expected
                array(
                    array('start' => '2018-05-08T10:45:00+02:00', 'end' => '2018-05-08T18:00:00+02:00'), // Today
                    array('start' => '2018-05-09T09:00:00+02:00', 'end' => '2018-05-09T18:00:00+02:00'), // +3
                    array('start' => '2018-05-10T09:00:00+02:00', 'end' => '2018-05-10T18:00:00+02:00'),
                    array('start' => '2018-05-11T09:00:00+02:00', 'end' => '2018-05-11T18:00:00+02:00'),
                ),
            ),
        );
    }
}
