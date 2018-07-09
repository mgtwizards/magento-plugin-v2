<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Porterbuddy\Porterbuddy\Helper\Data;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var Exception
     */
    protected $exceptionHandler;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/shipping_porterbuddy.log';

    public function __construct(
        Data $helper,
        Exception $exceptionHandler,
        DriverInterface $filesystem,
        $filePath = null,
        $fileName = null
    ) {
        $this->exceptionHandler = $exceptionHandler;
        $this->loggerType = $helper->getDebug() ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO;
        parent::__construct($filesystem, $filePath, $fileName);
    }

    /**
     * Write all messages to product_import, and additionally stack traces to product_import_exception
     *
     * @param array $record
     * @return void
     */
    public function write(array $record)
    {
        // keep stack traces separately
        if (!empty($record['context']['is_exception']) && !empty($record['context']['exception_message'])) {
            $exceptionMessage = $record['context']['exception_message'];
            unset($record['context']['exception_message']);
            unset($record['context']['is_exception']);
            $this->exceptionHandler->handle($record);

            // for exception records, only exception message is written to main log
            $record['message'] = $exceptionMessage;
        }

        $record['formatted'] = $this->getFormatter()->format($record);
        parent::write($record);
    }
}
