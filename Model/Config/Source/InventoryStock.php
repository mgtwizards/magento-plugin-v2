<?php

namespace Porterbuddy\Porterbuddy\Model\Config\Source;

use Exception;
use Magento\InventoryApi\Api\StockRepositoryInterface;

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
        $this->stockRepository = $stockRepository;
    }

    public function toOptionArray($isMultiselect = false)
    {
        $options = [];

        try{
            $stocks = $this->stockRepository->getList()->getItems();
            foreach($stocks as $stock){
                $options[] = [
                    'value' => $stock->getStockId(),
                    'label' => $stock->getName()
                ];
            }

            if (!$isMultiselect) {
                array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
            }


        }catch(Exception $e){
            //probably not configured, we just skip it and have no options.
        }

        return $options;
    }
}
