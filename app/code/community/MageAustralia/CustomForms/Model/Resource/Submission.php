<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Model_Resource_Submission extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customforms/submission', 'submission_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        if (!$object->getId()) {
            $object->setData('created_at', Mage_Core_Model_Locale::nowUtc());
        }
        return parent::_beforeSave($object);
    }

    /**
     * Count submissions from an IP within the last N seconds (rate limiting).
     */
    public function countRecentByIp(string $ip, int $sinceSeconds): int
    {
        if ($ip === '') {
            return 0;
        }
        $adapter = $this->_getReadAdapter();
        // UTC threshold N seconds ago. gmdate() matches the 'Y-m-d H:i:s' UTC
        // format used for created_at and is portable across MySQL/PG/SQLite.
        $threshold = gmdate('Y-m-d H:i:s', time() - $sinceSeconds);
        $select = $adapter->select()
            ->from($this->getMainTable(), ['cnt' => new Maho\Db\Expr('COUNT(*)')])
            ->where('ip = ?', $ip)
            ->where('created_at >= ?', $threshold);
        return (int) $adapter->fetchOne($select);
    }
}
