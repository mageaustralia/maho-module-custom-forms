<?php

declare(strict_types=1);

use Maho\Config\Route;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Adminhtml_Customforms_SubmissionController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/customforms/submissions';

    #[\Override]
    public function preDispatch(): static
    {
        $this->_setForcedFormKeyActions(['delete']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('cms/customforms/submissions')
            ->_title($this->__('Custom Forms'))->_title($this->__('Submitted Data'));
        return $this;
    }

    #[Route('/admin/customforms_submission/index')]
    public function indexAction(): void
    {
        $this->_initAction()->renderLayout();
    }

    #[Route('/admin/customforms_submission/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false)->renderLayout();
    }

    #[Route('/admin/customforms_submission/view')]
    public function viewAction(): void
    {
        $id = (int) $this->getRequest()->getParam('submission_id');
        /** @var MageAustralia_CustomForms_Model_Submission $model */
        $model = Mage::getModel('customforms/submission')->load($id);
        if (!$model->getId()) {
            $this->_getSession()->addError($this->__('This submission no longer exists.'));
            $this->_redirect('*/*/');
            return;
        }
        Mage::register('customforms_submission', $model);
        $this->_initAction()->_title('#' . $model->getId())->renderLayout();
    }

    #[Route('/admin/customforms_submission/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('submission_id');
        if ($id) {
            try {
                Mage::getModel('customforms/submission')->setId($id)->delete();
                $this->_getSession()->addSuccess($this->__('The submission has been deleted.'));
            } catch (Throwable $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('Could not delete the submission.'));
            }
        }
        $this->_redirect('*/*/');
    }
}
