<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'form_id';
        $this->_blockGroup = 'customforms';
        $this->_controller = 'adminhtml_form';
        parent::__construct();

        $this->_addButton('save_and_continue', [
            'label'   => $this->__('Save and Continue Edit'),
            'class'   => 'save',
            'onclick' => 'customFormsSaveAndContinue()',
        ], 100);

        $this->_updateButton('save', 'label', $this->__('Save Form'));
    }

    #[\Override]
    protected function _prepareLayout()
    {
        // Match the core admin pattern: editForm.submit(url) with a back flag.
        $this->_formScripts[] = "
            function customFormsSaveAndContinue() {
                editForm.submit('" . $this->getSaveAndContinueUrl() . "');
            }
        ";
        return parent::_prepareLayout();
    }

    public function getSaveAndContinueUrl(): string
    {
        return $this->getUrl('*/*/save', [
            'form_id' => $this->getRequest()->getParam('form_id'),
            'back'    => 'edit',
        ]);
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $form = Mage::registry('customforms_form');
        if ($form && $form->getId()) {
            return $this->__("Edit Form '%s'", $this->escapeHtml($form->getName()));
        }
        return $this->__('New Form');
    }
}
