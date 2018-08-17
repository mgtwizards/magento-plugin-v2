<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Controller\Adminhtml\Postcodes;

class Update extends \Magento\Backend\App\Action
{
    /**
     * @var \Porterbuddy\Porterbuddy\Model\Availability
     */
    protected $availability;

    /**
     * @param \Porterbuddy\Porterbuddy\Model\Availability $availability
     * @param \Magento\Backend\App\Action\Context $context
     */
    public function __construct(
        \Porterbuddy\Porterbuddy\Model\Availability $availability,
        \Magento\Backend\App\Action\Context $context
    ) {
        $this->availability = $availability;
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $this->availability->updatePostcodes();
            $this->messageManager->addSuccess(__('Postcodes have been updated.'));
        } catch (\Exception $e) {
            // already logged
            $this->messageManager->addErrorMessage(__('An error occurred - %1', $e->getMessage()));
        }

        return $resultRedirect->setRefererUrl();
    }
}
