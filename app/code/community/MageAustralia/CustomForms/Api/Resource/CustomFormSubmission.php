<?php

declare(strict_types=1);

namespace MageAustralia\CustomForms\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use MageAustralia\CustomForms\Api\State\Processor\CustomFormSubmissionProcessor;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Headless form submission. Runs the same authoritative server pipeline as the
 * server-rendered controller (size caps, honeypot, render-token time-trap,
 * per-IP rate limit, captcha, schema validation). Served at
 * POST /api/rest/v2/custom-forms/{code}/submissions.
 *
 * Outcome is conveyed by `status` (ok|invalid|expired|too_fast|captcha) with a
 * field-keyed `errors` map for `invalid`. Hard rejections use real HTTP codes:
 * 404 (unknown/inactive form), 429 (rate limited), 413 (payload too large).
 */
#[ApiResource(
    shortName: 'CustomFormSubmission',
    description: 'Submit a custom form (full server-side anti-abuse pipeline)',
    processor: CustomFormSubmissionProcessor::class,
    operations: [
        new Post(
            uriTemplate: '/custom-forms/{code}/submissions',
            description: 'Submit a form; server re-validates and stores the submission',
            security: 'true',
        ),
    ],
)]
class CustomFormSubmission
{
    #[ApiProperty(identifier: true, writable: false)]
    public string $code = '';

    /** Field values keyed by field key. */
    public ?array $payload = null;

    /** Altcha captcha solution (when the form requires captcha). */
    public ?string $captchaToken = null;

    /** Render token issued by the GET endpoint. */
    public ?string $renderToken = null;

    /** Honeypot value: must be empty for a human. */
    public ?string $hp = null;

    /** Outcome: ok | invalid | expired | too_fast | captcha (output). */
    #[ApiProperty(writable: false)]
    public ?string $status = null;

    /** Human-readable message (output). */
    #[ApiProperty(writable: false)]
    public ?string $message = null;

    /** Field-keyed validation errors, present when status = invalid (output). */
    #[ApiProperty(writable: false)]
    public ?array $errors = null;
}
