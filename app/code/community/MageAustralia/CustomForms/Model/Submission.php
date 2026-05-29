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
 * A single form submission. `payload` (JSON) is keyed by field key.
 *
 * @method int getFormId()
 * @method $this setFormId(int $id)
 * @method int|null getStoreId()
 * @method $this setStoreId(?int $id)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(?int $id)
 * @method string getPayload()
 * @method $this setPayload(string $json)
 * @method string|null getFiles()
 * @method $this setFiles(?string $json)
 * @method string getStatus()
 * @method $this setStatus(string $status)
 * @method string|null getIp()
 * @method $this setIp(?string $ip)
 * @method string|null getCreatedAt()
 */
class MageAustralia_CustomForms_Model_Submission extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'customforms_submission';
    protected $_eventObject = 'submission';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customforms/submission');
    }

    /**
     * Decoded payload (field key => value).
     *
     * @return array<string, mixed>
     */
    public function getDecodedPayload(): array
    {
        $raw = (string) $this->getPayload();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
