<?php

namespace Porterbuddy\Porterbuddy\Model\Config\Source;

use Exception;
use Porterbuddy\Porterbuddy\Model\InventoryApi\StockRepositoryInstance as StockRepositoryInterface;

class InventoryStock implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * @var StockRepositoryInterface
     */
    private $stockRepository;

    /**
     * InventorySources constructor.
     * @param StockRepositoryInterface $sourceRepository
     */
    public function __construct(
        StockRepositoryInterface $stockRepository
    ){
        $this->stockRepository = $stockRepository->get();
    }

    public function toOptionArray($isMultiselect = false)
    {
        $options = [];

        if ($this->stockRepository) {
            $stocks = $this->stockRepository->getList()->getItems();
            foreach ($stocks as $stock) {
                $options[] = [
                    'value' => $stock->getStockId(),
                    'label' => $stock->getName()
                ];
            }

            if (!$isMultiselect) {
                array_unshift($options, [
                    'value' => '',
                    'label' => __('--Please Select--')
                ]);
            }
        }

        return $options;
    }
}
