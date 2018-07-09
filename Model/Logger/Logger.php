<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Logger;

class Logger extends \Monolog\Logger
{
    /**
     * Adds a log record.
     *
     * @param  integer $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        if ($message instanceof \Exception) {
            $context['is_exception'] = true;
            $context['exception_message'] = $message->getMessage();
        }

        return parent::addRecord($level, $message, $context);
    }
}
