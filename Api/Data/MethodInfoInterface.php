<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Api\Data;

interface MethodInfoInterface
{
    /**
     * Product as returned from API - express, express-with-return, delivery, delivery-with-return
     *
     * @return string
     */
    public function getProduct();

    /**
     * @param string $product
     * @return $this
     */
    public function setProduct($product);

    /**
     * More generic type - express or delivery
     *
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type);

    /**
     * @return string
     */
    public function getStart();

    /**
     * @param string $start
     * @return $this
     */
    public function setStart($start);

    /**
     * @return string
     */
    public function getEnd();

    /**
     * @param string $end
     * @return $this
     */
    public function setEnd($end);

    /**
     * @return bool
     */
    public function isReturn();

    /**
     * @param bool $return
     * @return $this
     */
    public function setReturn($return);

    /**
     * @return bool
     */
    public function isConsolidated();

    /**
     * @param bool $consolidated
     * @return $this
     */
    public function setConsolidated($consolidated);

    /**
     * @return string
     */
    public function getDateKey();

    /**
     * @param string $dateKey
     * @return $this
     */
    public function setDateKey($dateKey);

    /**
     * @return string|null
     */
    public function getDateLabel();

    /**
     * @param string $dateLabel
     * @return $this
     */
    public function setDateLabel($dateLabel);

    /**
     * @return string|null
     */
    public function getLabel();

    /**
     * @param string $timeslotLabel
     * @return $this
     */
    public function setLabel($timeslotLabel);

    /**
     * @return
     */
    public function getWindows();

    /**
     * @param $windows
     * @return $this
     */
    public function setWindows($windows);
}
