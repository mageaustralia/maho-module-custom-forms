<?php

declare(strict_types=1);

namespace MageAustralia\CustomForms\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use MageAustralia\CustomForms\Api\Resource\CustomFormSubmission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Headless submission processor. The server stays authoritative: it re-runs the
 * full processSubmission() anti-abuse pipeline (size caps, honeypot, render
 * token + time-trap, per-IP rate limit on the real client IP, captcha, schema
 * validation) regardless of any client-side checks. Inputs are never echoed
 * back in the response.
 */
class CustomFormSubmissionProcessor implements ProcessorInterface
{
    /**
     * @param CustomFormSubmission $data
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomFormSubmission
    {
        // $data->code is typed non-nullable so it can't be the ?? source; only
        // uriVariables['code'] can be missing (POST without a code in the URL).
        $code = (string) ($uriVariables['code'] ?? $data->code);

        /** @var \MageAustralia_CustomForms_Helper_Data $helper */
        $helper = \Mage::helper('customforms');

        $storeId = $helper->resolveStorefrontStoreId();
        if (!$helper->isApiEnabled($storeId)) {
            throw new NotFoundHttpException('Form not found');
        }
        $form = $helper->getForm($code, $storeId);
        if (!$form) {
            throw new NotFoundHttpException('Form not found');
        }

        $payload = is_array($data->payload) ? $data->payload : [];
        $request = \Mage::app()->getRequest();

        $result = $helper->processSubmission($form, $payload, [
            'ip'            => $helper->resolveClientIp($request),
            'store_id'      => $storeId,
            'customer_id'   => null,
            'honeypot'      => (string) ($data->hp ?? ''),
            'render_token'  => (string) ($data->renderToken ?? ''),
            'captcha_token' => (string) ($data->captchaToken ?? ''),
        ]);

        // Never reflect the submitted inputs back to the caller.
        $data->payload = null;
        $data->renderToken = null;
        $data->captchaToken = null;
        $data->hp = null;

        $resultCode = (string) $result->getData('code');
        $message = (string) $result->getData('message');

        // Hard rejections get real HTTP status codes so clients / edge proxies
        // can back off and so abuse is visible in access logs.
        if ($resultCode === 'rate_limited') {
            throw new TooManyRequestsHttpException(null, $message !== '' ? $message : 'Too many submissions.');
        }
        if ($resultCode === 'too_large') {
            // 413 Payload Too Large (PayloadTooLargeHttpException isn't in this
            // Symfony version, so use the generic HttpException with the code).
            throw new HttpException(413, $message !== '' ? $message : 'Submission too large.');
        }

        $data->code = (string) $form->getCode();
        $data->status = $result->getData('success') ? 'ok' : ($resultCode !== '' ? $resultCode : 'invalid');
        $data->message = $message;
        $errors = (array) $result->getData('errors');
        $data->errors = $errors !== [] ? $errors : null;

        return $data;
    }
}
