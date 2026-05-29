<?php

declare(strict_types=1);

use Maho\Config\Route;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Frontend submission endpoint. Runs the shared helper pipeline (anti-spam +
 * validation + persist + event), stashes the result in session, and redirects
 * back to the form (post-redirect-get) so the success message or field errors
 * render inline.
 */
class MageAustralia_CustomForms_FormController extends Mage_Core_Controller_Front_Action
{
    #[Route('/customforms/form/submit', methods: ['POST'])]
    public function submitAction(): void
    {
        $request = $this->getRequest();
        $helper = Mage::helper('customforms');
        $code = trim((string) $request->getPost('form_code', ''));

        // No session form key check: this form is built for embedding on
        // cacheable CMS pages, where a per-session form key cannot persist
        // (it regenerates every render). The stateless render_token, verified
        // inside processSubmission, is our CSRF / anti-replay anchor instead.
        $form = $helper->getForm($code);
        if (!$form) {
            $this->_redirectReferer();
            return;
        }

        $payload = $request->getPost('field', []);
        $payload = is_array($payload) ? $payload : [];

        $customerSession = Mage::getSingleton('customer/session');
        $result = $helper->processSubmission($form, $payload, [
            'ip'            => $helper->resolveClientIp($request),
            'store_id'      => (int) Mage::app()->getStore()->getId(),
            'customer_id'   => $customerSession->isLoggedIn() ? (int) $customerSession->getCustomerId() : null,
            'honeypot'      => (string) $request->getPost(MageAustralia_CustomForms_Helper_Data::HONEYPOT_FIELD, ''),
            'render_token'  => (string) $request->getPost('render_token', ''),
            'captcha_token' => (string) $request->getPost('altcha', ''),
        ]);

        $success = (bool) $result->getData('success');
        // One-time nonce: the result is displayed only when the redirect URL
        // carries the matching ?cfr= token. The form lives on a cacheable CMS
        // page where a GET cannot reliably clear a session flag, so gating on a
        // URL nonce (instead of "read once then unset") is what stops the
        // message sticking on every revisit. The block also strips the param
        // from the URL via JS so a refresh won't re-show it.
        $nonce = Mage::helper('core')->getRandomString(16);
        $this->_stashResult(
            $code,
            $nonce,
            $success,
            (string) $result->getData('message'),
            (array) $result->getData('errors'),
            $success ? [] : $payload,
        );
        $this->_redirectWithResult($nonce);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $values
     */
    protected function _stashResult(string $code, string $nonce, bool $success, string $message, array $errors, array $values): void
    {
        Mage::getSingleton('core/session')->setData('customforms_result', [
            'code'    => $code,
            'nonce'   => $nonce,
            'success' => $success,
            'message' => $message,
            'errors'  => $errors,
            'values'  => $values,
        ]);
    }

    /**
     * Redirect back to the submitting page (falling back to home), carrying the
     * result nonce as ?cfr= so the form block displays the result exactly once.
     * Named distinctly from the parent _redirectReferer() to avoid overriding it
     * with an incompatible signature.
     */
    protected function _redirectWithResult(string $nonce): void
    {
        $ref = (string) $this->getRequest()->getServer('HTTP_REFERER', '');
        $url = $ref !== '' ? $ref : Mage::getBaseUrl();

        // Strip any stale cfr param, preserve a trailing #anchor, append nonce.
        $url = (string) preg_replace('~([?&])cfr=[^&#]*(&|$)~', '$1', $url);
        $url = (string) preg_replace('~[?&]+($)~', '', $url);
        $hash = '';
        if (($h = strpos($url, '#')) !== false) {
            $hash = substr($url, $h);
            $url = substr($url, 0, $h);
        }
        $url .= (str_contains($url, '?') ? '&' : '?') . 'cfr=' . rawurlencode($nonce) . $hash;

        $this->getResponse()->setRedirect($url);
    }
}
