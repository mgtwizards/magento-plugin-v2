<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model;

class MethodInfo extends \Magento\Framework\DataObject
    implements \Porterbuddy\Porterbuddy\Api\Data\MethodInfoInterface
{
    /**#@+
     * Constants
     */
    const KEY_PRODUCT = 'product';
    const KEY_TYPE = 'type';
    const KEY_START = 'start';
    const KEY_END = 'end';
    const KEY_RETURN = 'return';
    const KEY_DATE_KEY = 'date_key';
    const KEY_DATE_LABEL = 'date_label';
    const KEY_LABEL = 'label';
    /**#@-*/

    /**
     * {@inheritdoc}
     */
    public function getProduct()
    {
        return $this->getData(self::KEY_PRODUCT);
    }

    /**
     * {@inheritdoc}
     */
    public function setProduct($product)
    {
        return $this->setData(self::KEY_PRODUCT, $product);
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->getData(self::KEY_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        return $this->setData(self::KEY_TYPE, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function getStart()
    {
        return $this->getData(self::KEY_START);
    }

    /**
     * {@inheritdoc}
     */
    public function setStart($start)
    {
        return $this->setData(self::KEY_START, $start);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnd()
    {
        return $this->getData(self::KEY_END);
    }

    /**
     * {@inheritdoc}
     */
    public function setEnd($end)
    {
        return $this->setData(self::KEY_END, $end);
    }

    /**
     * {@inheritdoc}
     */
    public function isReturn()
    {
        return $this->getData(self::KEY_RETURN);
    }

    /**
     * {@inheritdoc}
     */
    public function setReturn($return)
    {
        return $this->setData(self::KEY_RETURN, $return);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateKey()
    {
        return $this->getData(self::KEY_DATE_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function setDateKey($dateKey)
    {
        return $this->setData(self::KEY_DATE_KEY, $dateKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateLabel()
    {
        return $this->getData(self::KEY_DATE_LABEL);
    }

    /**
     * {@inheritdoc}
     */
    public function setDateLabel($dateLabel)
    {
        return $this->setData(self::KEY_DATE_LABEL, $dateLabel);
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return $this->getData(self::KEY_LABEL);
    }

    /**
     * {@inheritdoc}
     */
    public function setLabel($timeslotLabel)
    {
        return $this->setData(self::KEY_LABEL, $timeslotLabel);
    }
}
