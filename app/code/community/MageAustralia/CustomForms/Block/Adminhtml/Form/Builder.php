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
 * Renders the schema builder UI. Vanilla-ES enhancement over the hidden
 * `schema` field: builder.js reads the current schema, renders editable field
 * cards, and serialises back into #schema on change/submit.
 */
class MageAustralia_CustomForms_Block_Adminhtml_Form_Builder extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('customforms/builder.phtml');
    }

    /**
     * Current form schema as a JSON string (defaults to an empty field list).
     */
    public function getSchemaJson(): string
    {
        /** @var MageAustralia_CustomForms_Model_Form $model */
        $model = Mage::registry('customforms_form');
        $raw = $model ? (string) $model->getSchema() : '';
        return $raw !== '' ? $raw : '{"version":1,"fields":[]}';
    }

    /**
     * Field-type catalogue offered by the palette.
     *
     * @return list<array{type:string,label:string,choice:bool}>
     */
    public function getFieldTypes(): array
    {
        return [
            ['type' => 'text',        'label' => $this->__('Text'),         'choice' => false],
            ['type' => 'textarea',    'label' => $this->__('Textarea'),     'choice' => false],
            ['type' => 'email',       'label' => $this->__('Email'),        'choice' => false],
            ['type' => 'phone',       'label' => $this->__('Phone'),        'choice' => false],
            ['type' => 'number',      'label' => $this->__('Number'),       'choice' => false],
            ['type' => 'select',      'label' => $this->__('Dropdown'),     'choice' => true],
            ['type' => 'radio',       'label' => $this->__('Radio'),        'choice' => true],
            ['type' => 'checkbox',    'label' => $this->__('Checkboxes'),   'choice' => true],
            ['type' => 'multiselect', 'label' => $this->__('Multi-select'), 'choice' => true],
            ['type' => 'file',        'label' => $this->__('File upload'),  'choice' => false],
            ['type' => 'heading',     'label' => $this->__('Heading'),      'choice' => false],
        ];
    }

    public function getFieldTypesJson(): string
    {
        return Mage::helper('core')->jsonEncode($this->getFieldTypes());
    }
}
