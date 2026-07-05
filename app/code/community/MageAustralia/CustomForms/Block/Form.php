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
 * Frontend renderer for a custom form. Used both via the CMS directive
 * {{block type="customforms/form" form_code="..."}} and via layout XML.
 * Renders the form from its JSON schema; submission posts to the frontend
 * submit controller which runs the shared helper pipeline.
 *
 * @method string|null getFormCode()
 * @method $this setFormCode(string $code)
 */
class MageAustralia_CustomForms_Block_Form extends Mage_Core_Block_Template
{
    private ?MageAustralia_CustomForms_Model_Form $_form = null;
    private bool $_formResolved = false;

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        if (!$this->getTemplate()) {
            $this->setTemplate('customforms/form.phtml');
        }
    }

    public function customformsHelper(): MageAustralia_CustomForms_Helper_Data
    {
        /** @var MageAustralia_CustomForms_Helper_Data $h */
        $h = Mage::helper('customforms');
        return $h;
    }

    public function getForm(): ?MageAustralia_CustomForms_Model_Form
    {
        if (!$this->_formResolved) {
            $this->_formResolved = true;
            $code = trim((string) $this->getFormCode());
            if ($code !== '' && $this->customformsHelper()->isEnabled()) {
                $this->_form = $this->customformsHelper()->getForm($code);
            }
        }
        return $this->_form;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFields(): array
    {
        $form = $this->getForm();
        return $form ? $this->customformsHelper()->getSchemaFields($form) : [];
    }

    public function getFormTitle(): string
    {
        $form = $this->getForm();
        if (!$form) {
            return '';
        }
        $schema = $form->getDecodedSchema();
        return (string) ($schema['title'] ?? $form->getName());
    }

    public function getActionUrl(): string
    {
        return $this->getUrl('customforms/form/submit', [
            '_secure' => Mage::getStoreConfigFlag('web/secure/use_in_frontend'),
        ]);
    }

    public function getRenderToken(): string
    {
        $form = $this->getForm();
        return $form ? $this->customformsHelper()->signRenderToken((string) $form->getCode()) : '';
    }

    public function isHoneypotEnabled(): bool
    {
        return $this->customformsHelper()->isHoneypotEnabled();
    }

    /** Honeypot field name (kept stable + innocuous-looking). */
    public function getHoneypotName(): string
    {
        return MageAustralia_CustomForms_Helper_Data::HONEYPOT_FIELD;
    }

    public function getSubmitLabel(): string
    {
        $form = $this->getForm();
        $settings = $form ? $form->getDecodedSettings() : [];
        $label = trim((string) ($settings['submitLabel'] ?? ''));
        return $label !== '' ? $label : (string) $this->__('Submit');
    }

    /**
     * Pull (and clear) the last submit result for this form from session, so
     * a post-redirect-get flow can show the success message or field errors +
     * repopulate values.
     *
     * @return array{success?:bool,message?:string,errors?:array<string,string>,values?:array<string,mixed>}
     */
    public function getResult(): array
    {
        $form = $this->getForm();
        if (!$form) {
            return [];
        }
        // Display is gated on the one-time ?cfr= nonce from the redirect, NOT
        // on "read once then unset": this form lives on cacheable CMS pages
        // where a GET cannot reliably persist a session unset, so clearing the
        // flag would never stick and the message would show on every revisit.
        // A plain revisit has no cfr param -> nothing shown. The template
        // strips the param from the URL after display so a refresh is clean.
        $nonce = (string) Mage::app()->getRequest()->getParam('cfr', '');
        if ($nonce === '') {
            return [];
        }
        $session = Mage::getSingleton('core/session');
        $all = $session->getData('customforms_result');
        if (!is_array($all)
            || ($all['code'] ?? null) !== $form->getCode()
            || !hash_equals((string) ($all['nonce'] ?? ''), $nonce)
        ) {
            return [];
        }
        // Return only the declared shape - $all also carries `code` + `nonce`
        // (validation metadata) which callers don't need.
        return array_intersect_key($all, array_flip(['success', 'message', 'errors', 'values']));
    }

    /** Convenience for the template (resolved once). */
    public function getResultData(): Maho\DataObject
    {
        return new Maho\DataObject($this->getResult());
    }

    /**
     * Whether this form requires the altcha captcha (global config on + the
     * form has not opted out). Off by default in v1; honeypot + time-trap +
     * rate-limit are the always-on defenses.
     */
    public function isCaptchaRequired(): bool
    {
        $form = $this->getForm();
        if (!$form || !$this->customformsHelper()->isCaptchaEnabled()) {
            return false;
        }
        $settings = $form->getDecodedSettings();
        return ($settings['captcha'] ?? true) !== false;
    }

    /**
     * Best-effort altcha widget markup via Maho_Captcha. Returns empty string
     * if the captcha helper is unavailable.
     */
    public function getCaptchaHtml(): string
    {
        try {
            /** @var Maho_Captcha_Helper_Data $captcha */
            $captcha = Mage::helper('captcha');
            if (!$captcha->isEnabled()) {
                return '';
            }
            return '<altcha-widget challengeurl="' . $this->escapeHtml($captcha->getChallengeUrl()) . '"></altcha-widget>';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
