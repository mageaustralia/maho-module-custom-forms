<?php

declare(strict_types=1);

use Maho\Config\Observer as MahoObserver;
use Maho\Event\Observer;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Subscribes to customform_submission_created and sends the per-form
 * notification email. Decoupled via the event so other consumers (e.g. a B2B
 * registration adapter) can subscribe independently.
 *
 * Run `composer dump-autoload` after install so the attribute compiles.
 */
class MageAustralia_CustomForms_Model_Observer
{
    #[MahoObserver(MageAustralia_CustomForms_Helper_Data::EVENT_SUBMISSION_CREATED)]
    public function sendNotification(Observer $observer): void
    {
        try {
            $form = $observer->getEvent()->getForm();
            $submission = $observer->getEvent()->getSubmission();
            if ($form instanceof MageAustralia_CustomForms_Model_Form
                && $submission instanceof MageAustralia_CustomForms_Model_Submission
            ) {
                Mage::helper('customforms')->sendSubmissionNotification($form, $submission);
            }
        } catch (Throwable $e) {
            // A notification failure must never break the submission.
            Mage::logException($e);
        }
    }
}
