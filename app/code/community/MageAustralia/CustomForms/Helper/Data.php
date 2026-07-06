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
 * Schema-first form builder: config accessors, schema-driven validation, and
 * the single submission pipeline shared by the server-rendered controller and
 * the headless API processor.
 *
 * The form `schema` JSON shape is documented in docs/SCHEMA.md - that contract
 * is what the storefront renderer consumes.
 */
class MageAustralia_CustomForms_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'MageAustralia_CustomForms';

    public const XML_PATH_ENABLED     = 'customforms/general/enabled';
    public const XML_PATH_CAPTCHA     = 'customforms/antispam/captcha';
    public const XML_PATH_HONEYPOT    = 'customforms/antispam/honeypot';
    public const XML_PATH_MIN_SECONDS = 'customforms/antispam/min_seconds';
    public const XML_PATH_RATE_LIMIT  = 'customforms/antispam/rate_limit_per_hour';
    public const XML_PATH_API_ENABLED = 'customforms/api/enabled';

    /** Dispatched after a submission is persisted. Downstream flows subscribe. */
    public const EVENT_SUBMISSION_CREATED = 'customform_submission_created';

    /** Honeypot field name (innocuous-looking; bots fill it, humans never see it). */
    public const HONEYPOT_FIELD = 'contact_time';

    /** Registered transactional template for submission notifications. */
    public const NOTIFY_EMAIL_TEMPLATE = 'customforms_submission_notify';

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * Whether the headless API surface (schema GET + submissions POST) is on.
     * Opt-in: off by default so installs that only use the server-rendered form
     * expose no extra endpoint. Requires the module master switch too.
     */
    public function isApiEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && Mage::getStoreConfigFlag(self::XML_PATH_API_ENABLED, $storeId);
    }

    /**
     * The CMS embed snippet for a form. Paste into any CMS page/block/static
     * block to render the form inline.
     */
    /**
     * Portable CMS embed that renders in BOTH renderers from one paste:
     *  - Server theme: the {{block}} directive renders the form; the neutral
     *    placeholder div sits empty and invisible.
     *  - Headless storefront: it strips the {{block}} directive (and any
     *    form-shaped HTML) during CMS sanitisation, but the `data-maho-form`
     *    div survives and the custom-forms storefront plugin hydrates it.
     */
    public function getCmsSnippet(string $code): string
    {
        return '{{block type="customforms/form" form_code="' . $code . '"}}' . "\n"
            . '<div data-maho-form="' . $code . '"></div>';
    }

    /**
     * Layout XML snippet (for theme layout updates / local.xml).
     */
    public function getLayoutSnippet(string $code): string
    {
        return '<block type="customforms/form" name="customforms.' . $code
            . '"><action method="setFormCode"><code>' . $code . '</code></action></block>';
    }

    public function isCaptchaEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CAPTCHA, $storeId);
    }

    public function isHoneypotEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_HONEYPOT, $storeId);
    }

    public function getMinSeconds(?int $storeId = null): int
    {
        return max(0, (int) Mage::getStoreConfig(self::XML_PATH_MIN_SECONDS, $storeId));
    }

    public function getRateLimitPerHour(?int $storeId = null): int
    {
        return max(0, (int) Mage::getStoreConfig(self::XML_PATH_RATE_LIMIT, $storeId));
    }

    /**
     * Load an active-for-store form by code, or null.
     */
    public function getForm(string $code, ?int $storeId = null): ?MageAustralia_CustomForms_Model_Form
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        /** @var MageAustralia_CustomForms_Model_Form $form */
        $form = Mage::getModel('customforms/form')->loadByCode($code);
        return $form->isActiveForStore($storeId) ? $form : null;
    }

    /**
     * Flatten a form's fields whether the schema is stepped (`steps[].fields[]`)
     * or flat (`fields[]`).
     *
     * @return list<array<string, mixed>>
     */
    public function getSchemaFields(MageAustralia_CustomForms_Model_Form $form): array
    {
        $schema = $form->getDecodedSchema();
        $fields = [];
        if (!empty($schema['steps']) && is_array($schema['steps'])) {
            foreach ($schema['steps'] as $step) {
                foreach ($step['fields'] ?? [] as $field) {
                    if (is_array($field)) {
                        $fields[] = $field;
                    }
                }
            }
        } elseif (!empty($schema['fields']) && is_array($schema['fields'])) {
            foreach ($schema['fields'] as $field) {
                if (is_array($field)) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    /**
     * Evaluate a field's `showIf` against the payload. Hidden fields are not
     * validated. MVP supports a single `{field, eq}` equality condition.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $payload
     */
    public function isFieldVisible(array $field, array $payload): bool
    {
        $cond = $field['showIf'] ?? null;
        if (!is_array($cond) || empty($cond['field'])) {
            return true;
        }
        $target = (string) ($payload[$cond['field']] ?? '');
        return $target === (string) ($cond['eq'] ?? '');
    }

    /**
     * Validate a payload against the form schema. Returns a map of
     * fieldKey => error message; empty array means valid.
     *
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function validatePayload(MageAustralia_CustomForms_Model_Form $form, array $payload): array
    {
        $errors = [];
        foreach ($this->getSchemaFields($form) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || in_array(($field['type'] ?? ''), ['heading', 'html', 'step'], true)) {
                continue;
            }
            if (!$this->isFieldVisible($field, $payload)) {
                continue;
            }

            $value = $payload[$key] ?? null;
            $isArray = is_array($value);
            $isEmpty = $value === null || $value === '' || ($isArray && $value === []);

            if (!empty($field['required']) && $isEmpty) {
                $errors[$key] = $this->__('This field is required.');
                continue;
            }
            if ($isEmpty) {
                continue;
            }

            $error = $this->_validateFieldValue($field, $value);
            if ($error !== null) {
                $errors[$key] = $error;
            }
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function _validateFieldValue(array $field, mixed $value): ?string
    {
        $type     = (string) ($field['type'] ?? 'text');
        $rules    = is_array($field['validate'] ?? null) ? $field['validate'] : [];
        $str      = is_array($value) ? '' : (string) $value;

        switch ($type) {
            case 'email':
                if (!Mage::helper('core')->isValidEmail($str)) {
                    return $this->__('Please enter a valid email address.');
                }
                break;
            case 'number':
                if (!is_numeric($str)) {
                    return $this->__('Please enter a number.');
                }
                break;
            case 'select':
            case 'radio':
            case 'checkbox':
            case 'multiselect':
                $allowed = [];
                foreach (($field['options'] ?? []) as $opt) {
                    if (isset($opt['value'])) {
                        $allowed[] = (string) $opt['value'];
                    }
                }
                $given = is_array($value) ? array_map('strval', $value) : [$str];
                foreach ($given as $g) {
                    if ($allowed !== [] && !in_array($g, $allowed, true)) {
                        return $this->__('Please choose a valid option.');
                    }
                }
                break;
        }

        if (isset($rules['minLength']) && mb_strlen($str) < (int) $rules['minLength']) {
            return $this->__('Please enter at least %s characters.', (int) $rules['minLength']);
        }
        if (isset($rules['maxLength']) && mb_strlen($str) > (int) $rules['maxLength']) {
            return $this->__('Please enter no more than %s characters.', (int) $rules['maxLength']);
        }
        if (!empty($rules['pattern'])) {
            // Pattern is admin-authored (trusted). Delimit safely.
            $pattern = '~' . str_replace('~', '\~', (string) $rules['pattern']) . '~';
            if (@preg_match($pattern, $str) !== 1) {
                return $this->__('Please enter a valid value.');
            }
        }
        return null;
    }

    /**
     * The single submission pipeline. Runs anti-spam + validation, persists the
     * submission, and dispatches EVENT_SUBMISSION_CREATED. Shared by the
     * server-rendered controller and the headless API processor.
     *
     * @param array<string, mixed> $payload  field key => value
     * @param array<string, mixed> $context  ip, store_id, customer_id,
     *                                        captcha_token, honeypot, render_token
     * @return Maho\DataObject  {success:bool, errors:array, message:string, submission:?Model}
     */
    public function processSubmission(
        MageAustralia_CustomForms_Model_Form $form,
        array $payload,
        array $context = [],
    ): Maho\DataObject {
        // `code` is a machine-readable outcome (ok|too_large|expired|too_fast|
        // rate_limited|captcha|invalid) so API callers can map to HTTP status.
        $result = new Maho\DataObject(['success' => false, 'code' => 'invalid', 'errors' => [], 'message' => '']);
        $storeId = isset($context['store_id']) ? (int) $context['store_id'] : null;

        // 0. Hard input caps (abuse / DoS guard) before any work. Unknown fields
        //    are dropped later, but cap the raw payload first so an oversized
        //    body can't exhaust memory or bloat storage.
        $oversize = $this->_payloadExceedsLimits($payload);
        if ($oversize !== null) {
            return $result->setData('code', 'too_large')->setData('message', $oversize);
        }

        // 1. Honeypot: a hidden field bots fill but humans never see.
        if ($this->isHoneypotEnabled($storeId) && trim((string) ($context['honeypot'] ?? '')) !== '') {
            // Pretend success to a bot; persist nothing.
            return $result->setData('success', true)->setData('code', 'ok')->setData('message', $this->__('Thank you.'));
        }

        // 2. Render token: our stateless CSRF / anti-replay anchor. Unlike a
        //    session form key it survives full-page caching (HMAC over form
        //    code + issue time, valid 24h), so it is the correct check for a
        //    form embedded on cacheable CMS pages. Always required.
        $elapsed = $this->verifyRenderToken((string) $form->getCode(), (string) ($context['render_token'] ?? ''));
        if ($elapsed === null) {
            return $result->setData('code', 'expired')->setData('message', $this->__('This form has expired. Please reload the page and try again.'));
        }
        // 2b. Time-trap: reject suspiciously fast (bot) submissions.
        $minSeconds = $this->getMinSeconds($storeId);
        if ($minSeconds > 0 && $elapsed < $minSeconds) {
            return $result->setData('code', 'too_fast')->setData('message', $this->__('Your submission was too fast. Please try again.'));
        }

        // 3. Rate limit per IP.
        $ip = (string) ($context['ip'] ?? '');
        $rate = $this->getRateLimitPerHour($storeId);
        if ($rate > 0 && $ip !== '') {
            /** @var MageAustralia_CustomForms_Model_Resource_Submission $res */
            $res = Mage::getResourceModel('customforms/submission');
            if ($res->countRecentByIp($ip, 3600) >= $rate) {
                return $result->setData('code', 'rate_limited')->setData('message', $this->__('Too many submissions. Please try again later.'));
            }
        }

        // 4. Captcha (altcha via Maho_Captcha) - when enabled by config + form.
        if ($this->_captchaRequired($form, $storeId)) {
            $token = (string) ($context['captcha_token'] ?? '');
            if ($token === '' || !Mage::helper('captcha')->verify($token)) {
                return $result->setData('code', 'captcha')->setData('message', $this->__('Captcha verification failed.'));
            }
        }

        // 5. Schema validation (authoritative).
        $errors = $this->validatePayload($form, $payload);
        if ($errors !== []) {
            return $result->setData('code', 'invalid')
                ->setData('errors', $errors)
                ->setData('message', $this->__('Please correct the highlighted fields.'));
        }

        // 6. Persist + dispatch.
        $clean = $this->_filterPayloadToSchema($form, $payload);
        /** @var MageAustralia_CustomForms_Model_Submission $submission */
        $submission = Mage::getModel('customforms/submission');
        $submission->setFormId((int) $form->getId())
            ->setStoreId($storeId)
            ->setCustomerId(isset($context['customer_id']) ? (int) $context['customer_id'] : null)
            ->setPayload(Mage::helper('core')->jsonEncode($clean))
            ->setStatus('new')
            ->setIp($ip !== '' ? $ip : null);
        $submission->save();

        Mage::dispatchEvent(self::EVENT_SUBMISSION_CREATED, [
            'submission' => $submission,
            'form'       => $form,
        ]);

        $settings = $form->getDecodedSettings();
        $message = (string) ($settings['successMessage'] ?? '');
        return $result->setData('success', true)
            ->setData('code', 'ok')
            ->setData('submission', $submission)
            ->setData('message', $message !== '' ? $message : $this->__('Thank you. Your submission has been received.'));
    }

    /** Raw-payload abuse caps, enforced before any processing. */
    public const int MAX_PAYLOAD_FIELDS = 60;
    public const int MAX_VALUE_LENGTH   = 20000;
    public const int MAX_ARRAY_ITEMS    = 100;

    /**
     * Reject oversized / malformed raw payloads up front (DoS / abuse guard).
     * Returns a human message when over a limit, or null when within limits.
     *
     * @param array<string, mixed> $payload
     */
    private function _payloadExceedsLimits(array $payload): ?string
    {
        if (count($payload) > self::MAX_PAYLOAD_FIELDS) {
            return $this->__('Too many fields submitted.');
        }
        foreach ($payload as $value) {
            if (is_array($value)) {
                if (count($value) > self::MAX_ARRAY_ITEMS) {
                    return $this->__('Too many values submitted for one field.');
                }
                foreach ($value as $v) {
                    if (!is_scalar($v) && $v !== null) {
                        return $this->__('Invalid value submitted.');
                    }
                    if (is_string($v) && strlen($v) > self::MAX_VALUE_LENGTH) {
                        return $this->__('A submitted value is too long.');
                    }
                }
            } elseif (is_string($value)) {
                if (strlen($value) > self::MAX_VALUE_LENGTH) {
                    return $this->__('A submitted value is too long.');
                }
            } elseif (!is_scalar($value) && $value !== null) {
                return $this->__('Invalid value submitted.');
            }
        }
        return null;
    }

    /** Public wrapper so the headless API can mirror the captcha gate. */
    public function formRequiresCaptcha(MageAustralia_CustomForms_Model_Form $form, ?int $storeId = null): bool
    {
        return $this->_captchaRequired($form, $storeId);
    }

    /**
     * Resolve the store a headless API request should act in. X-Store-Code (sent
     * by the storefront proxy) sets the current store; when absent the API
     * kernel sits in the admin store (id 0), where no frontend form is active.
     * Fall back to the default store view so public form endpoints still resolve
     * (and so the embed widget works without an explicit store header).
     */
    public function resolveStorefrontStoreId(): int
    {
        $store = Mage::app()->getStore();
        if ((int) $store->getId() !== 0) {
            return (int) $store->getId();
        }
        $default = Mage::app()->getDefaultStoreView();
        return $default ? (int) $default->getId() : (int) $store->getId();
    }

    /**
     * Keep only values for keys the schema actually declares (drops honeypot,
     * tokens, and any injected extras).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function _filterPayloadToSchema(MageAustralia_CustomForms_Model_Form $form, array $payload): array
    {
        $clean = [];
        foreach ($this->getSchemaFields($form) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '' && array_key_exists($key, $payload)) {
                $clean[$key] = $payload[$key];
            }
        }
        return $clean;
    }

    private function _captchaRequired(MageAustralia_CustomForms_Model_Form $form, ?int $storeId): bool
    {
        if (!$this->isCaptchaEnabled($storeId)) {
            return false;
        }
        $settings = $form->getDecodedSettings();
        // Default on when globally enabled; a form may opt out with captcha:false.
        return ($settings['captcha'] ?? true) !== false;
    }

    /**
     * Issue a signed render token carrying the issue time. Verified on submit
     * for the time-trap. Stateless (HMAC over form code + timestamp).
     */
    public function signRenderToken(string $formCode): string
    {
        $ts = time();
        $sig = substr(hash_hmac('sha256', $formCode . ':' . $ts, $this->_getSigningSecret()), 0, 24);
        return $ts . '.' . $sig;
    }

    /**
     * Verify a render token. Returns elapsed seconds since issue, or null when
     * malformed / forged / older than 24h.
     */
    public function verifyRenderToken(string $formCode, string $token): ?int
    {
        if (!str_contains($token, '.')) {
            return null;
        }
        [$tsRaw, $sig] = explode('.', $token, 2);
        if (!ctype_digit($tsRaw)) {
            return null;
        }
        $ts = (int) $tsRaw;
        $expected = substr(hash_hmac('sha256', $formCode . ':' . $ts, $this->_getSigningSecret()), 0, 24);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $elapsed = time() - $ts;
        if ($elapsed < 0 || $elapsed > 86400) {
            return null;
        }
        return $elapsed;
    }

    protected function _getSigningSecret(): string
    {
        // Crypt key is a global config node, not store config.
        return (string) Mage::getConfig()->getNode('global/crypt/key');
    }

    /**
     * Cloudflare published edge ranges (https://www.cloudflare.com/ips/,
     * checked 2026-05). Stable; updated rarely.
     *
     * @var list<string>
     */
    private const array CLOUDFLARE_CIDRS = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * Real client IP, accounting for Cloudflare. Behind CF, REMOTE_ADDR is
     * always a CF edge IP, which would make per-IP rate limiting effectively
     * global and the stored IP useless. Only when the request genuinely arrives
     * from a Cloudflare edge do we trust the CF-Connecting-IP header (validating
     * the edge IP prevents header spoofing from a direct-to-origin request).
     *
     * This is the app-level complement to the proper, global fix: nginx
     * ngx_http_realip_module with `set_real_ip_from <CF ranges>` +
     * `real_ip_header CF-Connecting-IP`, which makes REMOTE_ADDR correct for
     * ALL of Maho. With that in place REMOTE_ADDR is no longer a CF range, so
     * this method simply returns it unchanged - the two compose safely.
     */
    public function resolveClientIp(Mage_Core_Controller_Request_Http $request): string
    {
        $remote = (string) $request->getServer('REMOTE_ADDR', '');
        $cf     = (string) $request->getServer('HTTP_CF_CONNECTING_IP', '');
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) !== false && $this->_isCloudflareIp($remote)) {
            return $cf;
        }
        return $remote !== '' ? $remote : (string) $request->getClientIp();
    }

    private function _isCloudflareIp(string $ip): bool
    {
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        foreach (self::CLOUDFLARE_CIDRS as $cidr) {
            if ($this->_ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /** CIDR membership test, IPv4 + IPv6 (via inet_pton binary compare). */
    private function _ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bitsRaw] = array_pad(explode('/', $cidr, 2), 2, '0');
        $bits = (int) $bitsRaw;
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // invalid, or v4-vs-v6 family mismatch
        }
        $whole = intdiv($bits, 8);
        $rem   = $bits % 8;
        if ($whole > 0 && strncmp($ipBin, $subnetBin, $whole) !== 0) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        return ($ipBin[$whole] & $mask) === ($subnetBin[$whole] & $mask);
    }

    /**
     * Recipient emails configured on a form (settings.notify), validated.
     *
     * @return list<string>
     */
    public function getNotifyRecipients(MageAustralia_CustomForms_Model_Form $form): array
    {
        $notify = $form->getDecodedSettings()['notify'] ?? [];
        if (!is_array($notify)) {
            return [];
        }
        $out = [];
        foreach ($notify as $email) {
            $email = trim((string) $email);
            if ($email !== '' && Mage::helper('core')->isValidEmail($email)) {
                $out[] = $email;
            }
        }
        return $out;
    }

    /**
     * Email the form's notify recipients with a submission. No-op when no
     * recipients or the template is missing. Called by the
     * customform_submission_created observer.
     */
    public function sendSubmissionNotification(
        MageAustralia_CustomForms_Model_Form $form,
        MageAustralia_CustomForms_Model_Submission $submission,
    ): void {
        $recipients = $this->getNotifyRecipients($form);
        if ($recipients === []) {
            return;
        }

        $storeId = (int) ($submission->getStoreId() ?: Mage::app()->getStore()->getId());

        // Pre-render the field/value rows: the email filter has no {{foreach}},
        // so structured data must arrive as a single pre-built HTML var.
        $rows = '';
        $labels = $this->_fieldLabelMap($form);
        foreach ($submission->getDecodedPayload() as $key => $value) {
            $display = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            $rows .= '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #e5e5e5;background:#fafafa;"><strong>'
                . htmlspecialchars((string) ($labels[$key] ?? $key), ENT_QUOTES) . '</strong></td>'
                . '<td style="padding:6px 10px;border:1px solid #e5e5e5;">'
                . nl2br(htmlspecialchars($display, ENT_QUOTES)) . '</td></tr>';
        }

        /** @var Mage_Core_Model_Email_Template $mail */
        $mail = Mage::getModel('core/email_template');
        $mail->setDesignConfig(['area' => 'frontend', 'store' => $storeId]);
        $mail->loadDefault(self::NOTIFY_EMAIL_TEMPLATE);
        if (!$mail->getTemplateText()) {
            return;
        }
        $mail->setSenderName((string) Mage::getStoreConfig('trans_email/ident_general/name', $storeId));
        $mail->setSenderEmail((string) Mage::getStoreConfig('trans_email/ident_general/email', $storeId));

        $vars = [
            'form_name'     => (string) $form->getName(),
            'form_code'     => (string) $form->getCode(),
            'submission_id' => (int) $submission->getId(),
            'submitted_at'  => (string) $submission->getCreatedAt(),
            'ip'            => (string) $submission->getIp(),
            'items_html'    => $rows,
        ];

        foreach ($recipients as $email) {
            $mail->send($email, $email, $vars);
        }
    }

    /**
     * field key => label, for friendlier notification rows.
     *
     * @return array<string, string>
     */
    private function _fieldLabelMap(MageAustralia_CustomForms_Model_Form $form): array
    {
        $map = [];
        foreach ($this->getSchemaFields($form) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $map[$key] = (string) ($field['label'] ?? $key);
            }
        }
        return $map;
    }
}
