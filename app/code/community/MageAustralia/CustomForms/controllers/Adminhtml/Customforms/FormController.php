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

/**
 * Admin CRUD for form definitions. The visual builder is progressive
 * enhancement over a hidden `schema` JSON field; this controller just
 * persists what the builder serialised plus the general/settings fields.
 *
 * Routing is via #[Maho\Config\Route] attributes (Maho 26). Run
 * `composer dump-autoload` after install so they compile into the matcher.
 */
class MageAustralia_CustomForms_Adminhtml_Customforms_FormController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/customforms/forms';

    #[\Override]
    public function preDispatch(): static
    {
        $this->_setForcedFormKeyActions(['save', 'delete']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('cms/customforms/forms')
            ->_title($this->__('Custom Forms'))->_title($this->__('Manage Forms'));
        return $this;
    }

    #[Route('/admin/customforms_form/index')]
    public function indexAction(): void
    {
        $this->_initAction()->renderLayout();
    }

    #[Route('/admin/customforms_form/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false)->renderLayout();
    }

    #[Route('/admin/customforms_form/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Route('/admin/customforms_form/edit')]
    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('form_id');
        /** @var MageAustralia_CustomForms_Model_Form $model */
        $model = Mage::getModel('customforms/form');
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->_getSession()->addError($this->__('This form no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }
        Mage::register('customforms_form', $model);

        $this->_initAction()
            ->_title($model->getId() ? $model->getName() : $this->__('New Form'))
            ->renderLayout();
    }

    #[Route('/admin/customforms_form/save', methods: ['POST'])]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $id = (int) $this->getRequest()->getParam('form_id');
        /** @var MageAustralia_CustomForms_Model_Form $model */
        $model = Mage::getModel('customforms/form');
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->_getSession()->addError($this->__('This form no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        try {
            $code = trim((string) ($data['code'] ?? ''));
            // The code field is readonly once saved, so it normally posts; but
            // fall back to the stored code defensively (some themes drop
            // readonly values, and code is immutable after creation anyway).
            if ($code === '' && $model->getId()) {
                $code = (string) $model->getCode();
            }
            if ($code === '' || !preg_match('~^[a-z0-9_]+$~', $code)) {
                throw new Mage_Core_Exception($this->__('Code must be lowercase letters, numbers and underscores.'));
            }

            // Validate the builder-serialised schema is well-formed JSON.
            $schemaJson = (string) ($data['schema'] ?? '');
            if ($schemaJson === '') {
                $schemaJson = '{"fields":[]}';
            }
            try {
                $decodedSchema = Mage::helper('core')->jsonDecode($schemaJson);
            } catch (\JsonException | Mage_Core_Exception) {
                $decodedSchema = null;
            }
            if (!is_array($decodedSchema)) {
                throw new Mage_Core_Exception($this->__('The form layout could not be saved (invalid schema).'));
            }

            $storeIds = $data['store_ids'] ?? [];
            $storeIds = is_array($storeIds) ? implode(',', array_map('intval', $storeIds)) : '';

            $notify = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($data['notify_emails'] ?? '')),
            ), static fn(string $s): bool => $s !== ''));

            $settings = Mage::helper('core')->jsonEncode([
                'successMessage' => trim((string) ($data['success_message'] ?? '')),
                'captcha'        => (int) ($data['captcha'] ?? 1) === 1,
                'notify'         => $notify,
            ]);

            $model->addData([
                'code'      => $code,
                'name'      => trim((string) ($data['name'] ?? $code)),
                'is_active' => (int) ($data['is_active'] ?? 0),
                'store_ids' => $storeIds,
                'schema'    => $schemaJson,
                'settings'  => $settings,
            ]);
            $model->save();

            $this->_getSession()->addSuccess($this->__('The form has been saved.'));
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['form_id' => $model->getId()]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Could not save the form.'));
        }
        $this->_getSession()->setFormData($data);
        $this->_redirect('*/*/edit', ['form_id' => $id]);
    }

    #[Route('/admin/customforms_form/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('form_id');
        if ($id) {
            try {
                Mage::getModel('customforms/form')->setId($id)->delete();
                $this->_getSession()->addSuccess($this->__('The form has been deleted.'));
            } catch (Throwable $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('Could not delete the form.'));
            }
        }
        $this->_redirect('*/*/');
    }
}
