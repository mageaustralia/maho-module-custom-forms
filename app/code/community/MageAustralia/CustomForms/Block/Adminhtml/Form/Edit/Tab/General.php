<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        /** @var MageAustralia_CustomForms_Model_Form $model */
        $model = Mage::registry('customforms_form');

        $form = new Maho\Data\Form();
        $fs = $form->addFieldset('general', ['legend' => $this->__('General')]);

        $fs->addField('name', 'text', [
            'name'     => 'name',
            'label'    => $this->__('Name'),
            'required' => true,
        ]);
        $fs->addField('code', 'text', [
            'name'     => 'code',
            'label'    => $this->__('Code'),
            'required' => true,
            'note'     => $this->__('Stable machine key: lowercase letters, numbers, underscores. Used in URLs and the API.'),
            'readonly' => (bool) $model->getId(),
        ]);
        $fs->addField('is_active', 'select', [
            'name'    => 'is_active',
            'label'   => $this->__('Active'),
            'options' => [0 => $this->__('No'), 1 => $this->__('Yes')],
        ]);
        $fs->addField('store_ids', 'multiselect', [
            'name'   => 'store_ids[]',
            'label'  => $this->__('Stores'),
            'values' => $this->_getStoreValues(),
            'note'   => $this->__('Leave empty for all stores.'),
        ]);

        $values = $model->getData();
        if ($model->getStoreIds()) {
            $values['store_ids'] = explode(',', (string) $model->getStoreIds());
        }
        $form->setValues($values);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('General');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->__('General');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    /**
     * @return list<array{label: string, value: array<array{label:string,value:int}>}>
     */
    private function _getStoreValues(): array
    {
        $out = [];
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = [];
                foreach ($group->getStores() as $store) {
                    $stores[] = ['label' => $store->getName(), 'value' => (int) $store->getId()];
                }
                if ($stores !== []) {
                    $out[] = ['label' => $website->getName() . ' / ' . $group->getName(), 'value' => $stores];
                }
            }
        }
        return $out;
    }
}
