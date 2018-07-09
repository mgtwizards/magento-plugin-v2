<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Plugin\Sales\Block\Adminhtml;

use Magento\Sales\Block\Adminhtml\Order\AbstractOrder as AbstractOrderBlock;
use Porterbuddy\Porterbuddy\Block\Adminhtml\Info;

class AbstractOrder
{
    /**
     * @var \Magento\Framework\View\LayoutInterface
     */
    protected $layout;

    /**
     * @param \Magento\Framework\View\LayoutInterface $layout
     */
    public function __construct(\Magento\Framework\View\LayoutInterface $layout)
    {
        $this->layout = $layout;
    }

    public function afterToHtml(
        AbstractOrderBlock $block,
        $html
    ) {
        $markers = $this->getMarkers($block);

        if ($markers) {
            $pos = 0;
            foreach ($markers as $marker) {
                $pos = strpos($html, $marker, $pos+1);
                if (false === $pos) {
                    break;
                }
            }

            if ($pos) {
                /** @var Info $info */
                $info = $this->layout->createBlock(Info::class);
                $info->setOrder($block->getOrder());
                $infoHtml = $info->toHtml();

                $before = substr($html, 0, $pos);
                $after = substr($html, $pos);
                $html = $before . $infoHtml . $after;
            }
        }

        return $html;
    }

    /**
     * @param AbstractOrderBlock $block
     * @return array|bool
     */
    public function getMarkers(AbstractOrderBlock $block)
    {
        if ('order_shipping_view' === $block->getNameInLayout()) {
            return ['admin__page-section-item order-shipping-method', 'page-section-item-content', '</div>'];
        } elseif ('form' === $block->getNameInLayout()) {
            return ['shipping-description-wrapper', '</div>', '</div>'];
        } else {
            return false;
        }
    }
}
