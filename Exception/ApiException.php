<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Exception;

use Magento\Framework\Phrase;

class ApiException extends \Porterbuddy\Porterbuddy\Exception
{
    /**
     * @var array
     */
    protected $logData;

    public function __construct(Phrase $phrase, array $logData = [], \Exception $cause = null, $code = 0)
    {
        $this->logData = $logData;
        parent::__construct($phrase, $cause, $code);
    }

    /**
     * @return array
     */
    public function getLogData()
    {
        return $this->logData;
    }

    /**
     * @param array $logData
     * @return $this
     */
    public function setLogData(array $logData)
    {
        $this->logData = $logData;
        return $this;
    }
}
