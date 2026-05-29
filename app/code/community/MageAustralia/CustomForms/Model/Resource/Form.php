<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Model_Resource_Form extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customforms/form', 'form_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        $now = Mage_Core_Model_Locale::nowUtc();
        if (!$object->getId()) {
            $object->setData('created_at', $now);
        }
        $object->setData('updated_at', $now);
        return parent::_beforeSave($object);
    }
}
