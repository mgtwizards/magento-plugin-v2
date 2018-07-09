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

    public function parseMethodProvider()
    {
        // input, type, date, timeslotLength, return
        return array(
            array('porterbuddy_express_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'express', 'express', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false),
            array('express_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'express', 'express', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false),
            array('porterbuddy_delivery_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'delivery', 'delivery', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false),
            array('delivery_2018-05-11T11:00:00+00:00_2018-05-11T13:00:00+00:00', 'delivery', 'delivery', '2018-05-11T11:00:00+00:00', '2018-05-11T13:00:00+00:00', false),
            // returns
            array('porterbuddy_express-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'express-with-return', 'express', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true),
            array('express-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'express-with-return', 'express', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true),
            array('porterbuddy_delivery-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'delivery-with-return', 'delivery', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true),
            array('delivery-with-return_2018-05-11T13:00:00+00:00_2018-05-11T15:00:00+00:00', 'delivery-with-return', 'delivery', '2018-05-11T13:00:00+00:00', '2018-05-11T15:00:00+00:00', true),
            // select delivery time later
            array('delivery', 'delivery', 'delivery', null, null, false),
            array('delivery-with-return', 'delivery-with-return', 'delivery', null, null, true),
        );
    }

    public function testSplitPhoneCodeNumber()
    {
        $phone = '';
        $this->assertEquals(
            array('', ''),
            $this->helper->splitPhoneCodeNumber($phone),
            'Empty number'
        );

        $phone = '40123456';
        $this->assertEquals(
            array('', '40123456'),
            $this->helper->splitPhoneCodeNumber($phone),
            'Number without postcode'
        );

        $phone = '+47 22 86 24 00';
        $this->assertEquals(
            array('+47', '22862400'),
            $this->helper->splitPhoneCodeNumber($phone),
            'Norwegian postcode'
        );

        $phone = '+46 40 10 16 20';
        $this->assertEquals(
            array('+46', '40101620'),
            $this->helper->splitPhoneCodeNumber($phone),
            'Swedish postcode'
        );
    }
}
