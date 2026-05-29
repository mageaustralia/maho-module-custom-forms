<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form_Edit_Tab_Settings
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        /** @var MageAustralia_CustomForms_Model_Form $model */
        $model = Mage::registry('customforms_form');
        $settings = $model->getDecodedSettings();

        $form = new Maho\Data\Form();
        $fs = $form->addFieldset('settings', ['legend' => $this->__('Settings')]);
        $fs->addField('success_message', 'text', [
            'name'  => 'success_message',
            'label' => $this->__('Success message'),
        ]);
        $fs->addField('captcha', 'select', [
            'name'    => 'captcha',
            'label'   => $this->__('Captcha'),
            'options' => [1 => $this->__('Yes'), 0 => $this->__('No')],
            'note'    => $this->__('Require altcha captcha (when globally enabled).'),
        ]);
        $fs->addField('notify_emails', 'text', [
            'name'  => 'notify_emails',
            'label' => $this->__('Notify emails'),
            'note'  => $this->__('Comma-separated addresses to email on each submission. Leave blank for none.'),
        ]);

        $form->setValues([
            'success_message' => (string) ($settings['successMessage'] ?? ''),
            'captcha'         => ($settings['captcha'] ?? true) === false ? 0 : 1,
            'notify_emails'   => implode(', ', (array) ($settings['notify'] ?? [])),
        ]);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    public function getTabLabel(): string
    {
        return $this->__('Settings');
    }

    public function getTabTitle(): string
    {
        return $this->__('Settings');
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
