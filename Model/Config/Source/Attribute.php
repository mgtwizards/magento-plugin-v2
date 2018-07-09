<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Config\Source;

class Attribute implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var \Magento\Eav\Api\Data\AttributeInterface[]
     */
    protected $attributes;

    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Eav\Api\AttributeRepositoryInterface $attributeRepository
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getAttributes();
    }

    /**
     * @return \Magento\Eav\Api\Data\AttributeInterface[]
     */
    protected function getAttributes()
    {
        if (null === $this->attributes) {
            $this->attributes = [];
            $this->attributes[] = [
                'value' => '',
                'label' => __('-- Please Select --'),
            ];

            $this->searchCriteriaBuilder->addFilter('frontend_input', 'hidden', 'neq');
            $searchCriteria = $this->searchCriteriaBuilder->create();

            $attributeRepository = $this->attributeRepository->getList(
                \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
                $searchCriteria
            );

            foreach ($attributeRepository->getItems() as $attribute) {
                $this->attributes[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $attribute->getDefaultFrontendLabel() ?: $attribute->getAttributeCode(),
                ];
            }
        }

        return $this->attributes;
    }
}
