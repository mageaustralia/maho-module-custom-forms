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
 * A form. The `schema` column (JSON) is the single source of truth for fields,
 * types, validation, layout/widths, conditional logic and steps. This model is
 * a thin wrapper; schema interpretation lives in the helper.
 *
 * @method string getCode()
 * @method $this setCode(string $code)
 * @method string getName()
 * @method $this setName(string $name)
 * @method int getIsActive()
 * @method $this setIsActive(int $flag)
 * @method string|null getStoreIds()
 * @method $this setStoreIds(?string $ids)
 * @method string getSchema()
 * @method $this setSchema(string $json)
 * @method string|null getSettings()
 * @method $this setSettings(?string $json)
 * @method string|null getCreatedAt()
 * @method string|null getUpdatedAt()
 */
class MageAustralia_CustomForms_Model_Form extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'customforms_form';
    protected $_eventObject = 'form';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customforms/form');
    }

    public function loadByCode(string $code): self
    {
        $this->load($code, 'code');
        return $this;
    }

    /**
     * Decoded schema as an associative array (empty array on malformed JSON).
     *
     * @return array<string, mixed>
     */
    public function getDecodedSchema(): array
    {
        $raw = (string) $this->getSchema();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Decoded settings (captcha / notify / success behaviour).
     *
     * @return array<string, mixed>
     */
    public function getDecodedSettings(): array
    {
        $raw = (string) $this->getSettings();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Active, and either store-agnostic or scoped to the given store.
     */
    public function isActiveForStore(?int $storeId = null): bool
    {
        if (!$this->getId() || (int) $this->getIsActive() !== 1) {
            return false;
        }
        $scope = trim((string) $this->getStoreIds());
        if ($scope === '') {
            return true;
        }
        $storeId ??= (int) Mage::app()->getStore()->getId();
        $allowed = array_map('intval', array_filter(array_map('trim', explode(',', $scope)), static fn(string $s): bool => $s !== ''));
        return $allowed === [] || in_array($storeId, $allowed, true);
    }
}
