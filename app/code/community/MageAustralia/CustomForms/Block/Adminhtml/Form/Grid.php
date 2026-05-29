<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customforms_form_grid');
        $this->setDefaultSort('form_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $this->setCollection(Mage::getResourceModel('customforms/form_collection'));
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('form_id', [
            'header' => $this->__('ID'),
            'index'  => 'form_id',
            'type'   => 'number',
            'width'  => '60px',
        ]);
        $this->addColumn('name', [
            'header' => $this->__('Name'),
            'index'  => 'name',
        ]);
        $this->addColumn('code', [
            'header' => $this->__('Code'),
            'index'  => 'code',
            'width'  => '220px',
        ]);
        $this->addColumn('is_active', [
            'header'  => $this->__('Active'),
            'index'   => 'is_active',
            'type'    => 'options',
            'width'   => '90px',
            'options' => [0 => $this->__('No'), 1 => $this->__('Yes')],
        ]);
        $this->addColumn('updated_at', [
            'header' => $this->__('Updated'),
            'index'  => 'updated_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ]);
        $this->addColumn('action', [
            'header'   => $this->__('Action'),
            'type'     => 'action',
            'getter'   => 'getId',
            'filter'   => false,
            'sortable' => false,
            'width'    => '80px',
            'actions'  => [[
                'caption' => $this->__('Edit'),
                'url'     => ['base' => '*/*/edit'],
                'field'   => 'form_id',
            ]],
        ]);
        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['form_id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
