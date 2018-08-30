<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Test\Unit\Helper;

use Porterbuddy\Porterbuddy\Api\Data\MethodInfoInterfaceFactory;
use Porterbuddy\Porterbuddy\Helper\Data;
use Porterbuddy\Porterbuddy\Model\MethodInfo;

class DataTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Data
     */
    protected $helper;

    protected $methodInfoFactory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $methodInfo;

    protected function setUp()
    {
        $objectManagerHelper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $this->methodInfo = new MethodInfo();
        $this->methodInfoFactory = $this->getMockBuilder(MethodInfoInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->methodInfoFactory
            ->method('create')
            ->willReturn($this->methodInfo);

        $arguments = $objectManagerHelper->getConstructArguments(Data::class, [
            'methodInfoFactory' => $this->methodInfoFactory,
        ]);
        $this->helper = $objectManagerHelper->getObject(Data::class, $arguments);
    }

    /**
     * @dataProvider makeMethodCodeProvider
     */
    public function testMakeMethodCode(array $option, $skipDate, $expected)
    {
        $result = $this->helper->makeMethodCode($option, $skipDate);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function makeMethodCodeProvider()
    {
        return [
            [
                // option as returned from API
                ['product' => 'express', 'start' => '2018-08-24T09:30:00+02:00', 'end' => '2018-08-24T11:00:00+02:00'],
                // if timeslot selection on confirmation selected
                false,
                // expected method code
                'x_180824+02:00_0930_1100',
            ],
            [
                ['product' => 'express', 'start' => '2018-05-11T11:00:00+02:00', 'end' => '2018-05-11T13:00:00+02:00'],
                false,
                'x_180511+02:00_1100_1300',
            ],
            [
                ['product' => 'delivery', 'start' => '2018-05-11T11:00:00+02:00', 'end' => '2018-05-11T13:00:00+02:00'],
                false,
                'd_180511+02:00_1100_1300',
            ],
            [
                ['product' => 'express-with-return', 'start' => '2018-05-11T13:00:00+02:00', 'end' => '2018-05-11T15:00:00+02:00'],
                false,
                'xr_180511+02:00_1300_1500',
            ],
            [
                ['product' => 'delivery-with-return', 'start' => '2018-05-11T13:00:00+02:00', 'end' => '2018-05-11T15:00:00+02:00'],
                false,
                'dr_180511+02:00_1300_1500',
            ],
            [
                ['product' => 'delivery'],
                true,
                'd',
            ],
            [
                ['product' => 'delivery-with-return'],
                true,
                'dr',
            ],
        ];
    }

    /**
     * @dataProvider parseMethodProvider
     */
    public function testParseMethod($methodCode, $product, $type, $start, $end, $return)
    {
        $result = $this->helper->parseMethod($methodCode);
        $this->assertInstanceOf(\Porterbuddy\Porterbuddy\Api\Data\MethodInfoInterface::class, $result);
        $this->assertEquals($product, $result->getProduct(), 'Product');
        $this->assertEquals($type, $result->getType(), 'Type');
        $this->assertEquals($start, $result->getStart(), 'Start');
        $this->assertEquals($end, $result->getEnd(), 'End');
        $this->assertEquals($return, $result->isReturn(), 'Is return');
    }

    /**
     * @return array
     */
    public function parseMethodProvider()
    {
        // input, type, date, timeslotLength, return
        return [
            // old format - up to 80 characters
            ['porterbuddy_express_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'express', 'express', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false],
            ['express_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'express', 'express', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false],
            ['porterbuddy_delivery_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'delivery', 'delivery', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false],
            ['delivery_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'delivery', 'delivery', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false],
            // returns
            ['porterbuddy_express-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'express-with-return', 'express', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true],
            ['express-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'express-with-return', 'express', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true],
            ['porterbuddy_delivery-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'delivery-with-return', 'delivery', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true],
            ['delivery-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'delivery-with-return', 'delivery', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true],
            // select delivery time later
            ['delivery', 'delivery', 'delivery', null, null, false],
            ['delivery-with-return', 'delivery-with-return', 'delivery', null, null, true],

            // new format - fit in 40 characters
            ['porterbuddy_x_180511+02:00_1100_1300', 'express', 'express', '2018-05-11T11:00:00+02:00', '2018-05-11T13:00:00+02:00', false],
            ['x_180511+02:00_1100_1300', 'express', 'express', '2018-05-11T11:00:00+02:00', '2018-05-11T13:00:00+02:00', false],
            ['porterbuddy_d_180511+02:00_1100_1300', 'delivery', 'delivery', '2018-05-11T11:00:00+02:00', '2018-05-11T13:00:00+02:00', false],
            ['d_180511+02:00_1100_1300', 'delivery', 'delivery', '2018-05-11T11:00:00+02:00', '2018-05-11T13:00:00+02:00', false],
            // returns
            ['porterbuddy_xr_180511+02:00_1300_1500', 'express-with-return', 'express', '2018-05-11T13:00:00+02:00', '2018-05-11T15:00:00+02:00', true],
            ['xr_180511+02:00_1300_1500', 'express-with-return', 'express', '2018-05-11T13:00:00+02:00', '2018-05-11T15:00:00+02:00', true],
            ['porterbuddy_dr_180511+02:00_1300_1500', 'delivery-with-return', 'delivery', '2018-05-11T13:00:00+02:00', '2018-05-11T15:00:00+02:00', true],
            ['dr_180511+02:00_1300_1500', 'delivery-with-return', 'delivery', '2018-05-11T13:00:00+02:00', '2018-05-11T15:00:00+02:00', true],
            // select delivery time later
            ['d', 'delivery', 'delivery', null, null, false],
            ['dr', 'delivery-with-return', 'delivery', null, null, true],
        ];
    }

    public function testSplitPhoneCodeNumber()
    {
        $phone = '';
        $this->assertEquals(
            ['', ''],
            $this->helper->splitPhoneCodeNumber($phone),
            'Empty number'
        );

        $phone = '40123456';
        $this->assertEquals(
            ['', '40123456'],
            $this->helper->splitPhoneCodeNumber($phone),
            'Number without postcode'
        );

        $phone = '+47 22 86 24 00';
        $this->assertEquals(
            ['+47', '22862400'],
            $this->helper->splitPhoneCodeNumber($phone),
            'Norwegian postcode'
        );

        $phone = '+46 40 10 16 20';
        $this->assertEquals(
            ['+46', '40101620'],
            $this->helper->splitPhoneCodeNumber($phone),
            'Swedish postcode'
        );
    }
}
