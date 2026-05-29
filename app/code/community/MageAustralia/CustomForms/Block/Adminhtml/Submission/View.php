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
 * Read-only submission detail: meta + the field/value table.
 */
class MageAustralia_CustomForms_Block_Adminhtml_Submission_View extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('customforms/submission/view.phtml');
    }

    public function getSubmission(): ?MageAustralia_CustomForms_Model_Submission
    {
        $s = Mage::registry('customforms_submission');
        return $s instanceof MageAustralia_CustomForms_Model_Submission ? $s : null;
    }

    /**
     * Field key => submitted value (scalar-coerced for display).
     *
     * @return array<string, string>
     */
    public function getValues(): array
    {
        $submission = $this->getSubmission();
        if (!$submission) {
            return [];
        }
        $out = [];
        foreach ($submission->getDecodedPayload() as $key => $value) {
            $out[(string) $key] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        }
        return $out;
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function getDeleteUrl(): string
    {
        $submission = $this->getSubmission();
        return $this->getUrl('*/*/delete', ['submission_id' => $submission ? $submission->getId() : 0]);
    }
}
