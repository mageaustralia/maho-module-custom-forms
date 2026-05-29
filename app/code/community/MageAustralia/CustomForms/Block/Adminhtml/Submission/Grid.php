<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Submission_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customforms_submission_grid');
        $this->setDefaultSort('submission_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        /** @var MageAustralia_CustomForms_Model_Resource_Submission_Collection $collection */
        $collection = Mage::getResourceModel('customforms/submission_collection');
        $formTable = Mage::getSingleton('core/resource')->getTableName('customforms/form');
        $collection->getSelect()->joinLeft(
            ['f' => $formTable],
            'main_table.form_id = f.form_id',
            ['form_code' => 'f.code', 'form_name' => 'f.name'],
        );
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('submission_id', [
            'header' => $this->__('ID'),
            'index'  => 'submission_id',
            'type'   => 'number',
            'width'  => '60px',
        ]);
        $this->addColumn('form_name', [
            'header' => $this->__('Form'),
            'index'  => 'form_name',
        ]);
        $this->addColumn('customer_id', [
            'header' => $this->__('Customer Id'),
            'index'  => 'customer_id',
            'width'  => '100px',
        ]);
        $this->addColumn('ip', [
            'header' => $this->__('IP'),
            'index'  => 'ip',
            'width'  => '130px',
        ]);
        $this->addColumn('status', [
            'header' => $this->__('Status'),
            'index'  => 'status',
            'width'  => '90px',
        ]);
        $this->addColumn('created_at', [
            'header' => $this->__('Submitted'),
            'index'  => 'created_at',
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
                'caption' => $this->__('View'),
                'url'     => ['base' => '*/*/view'],
                'field'   => 'submission_id',
            ]],
        ]);
        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/view', ['submission_id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
