<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'customforms';
        $this->_controller = 'adminhtml_form';
        $this->_headerText = $this->__('Custom Forms');
        $this->_addButtonLabel = $this->__('Add New Form');
        parent::__construct();
    }
}
