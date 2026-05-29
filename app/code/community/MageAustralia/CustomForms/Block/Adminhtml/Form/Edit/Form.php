<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Thin form container. The fieldsets live in the per-tab blocks
 * (Edit/Tab/*); the Tabs block has destElementId="edit_form" so its panels are
 * relocated into this form and submit together.
 */
class MageAustralia_CustomForms_Block_Adminhtml_Form_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $form = new Maho\Data\Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['form_id' => $this->getRequest()->getParam('form_id')]),
            'method' => 'post',
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
