<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Logger;

/**
 * Logger Exception
 */
class Exception extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/shipping_porterbuddy_exception.log';
}
