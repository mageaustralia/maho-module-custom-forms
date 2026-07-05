<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form_Edit_Tab_Builder extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $form = new Maho\Data\Form();
        $fs = $form->addFieldset('builder', ['legend' => $this->__('Form Builder')]);
        // Hidden field the builder JS serialises into.
        $fs->addField('schema', 'hidden', ['name' => 'schema']);
        $fs->addField('builder_ui', 'note', [
            'text' => $this->getLayout()->createBlock('customforms/adminhtml_form_builder')->toHtml(),
        ]);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    public function getTabLabel(): string
    {
        return $this->__('Form Builder');
    }

    public function getTabTitle(): string
    {
        return $this->__('Form Builder');
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }
}
