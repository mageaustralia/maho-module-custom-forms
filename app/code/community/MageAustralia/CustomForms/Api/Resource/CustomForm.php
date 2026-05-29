<?php

declare(strict_types=1);

namespace MageAustralia\CustomForms\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use MageAustralia\CustomForms\Api\State\Provider\CustomFormProvider;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Headless form definition: the public schema a storefront renders client-side,
 * plus the captcha config and a fresh stateless render token. No PII; safe to
 * edge-cache. Served at GET /api/rest/v2/custom-forms/{code}.
 */
#[ApiResource(
    shortName: 'CustomForm',
    description: 'Public custom-form definition (schema + captcha + render token) for headless rendering',
    provider: CustomFormProvider::class,
    operations: [
        new Get(
            uriTemplate: '/custom-forms/{code}',
            description: 'Get a form schema, captcha config and a fresh render token',
            security: "true",
        ),
    ],
)]
class CustomForm
{
    #[ApiProperty(identifier: true, writable: false)]
    public string $code = '';

    /** Display name / title. */
    #[ApiProperty(writable: false)]
    public ?string $name = null;

    /** The form schema JSON (fields, widths, validation, steps, showIf). */
    #[ApiProperty(writable: false)]
    public ?array $schema = null;

    /** Captcha config: { required: bool, challengeUrl?: string }. */
    #[ApiProperty(writable: false)]
    public ?array $captcha = null;

    /** Stateless, HMAC-signed render token (carries issue time; valid 24h). */
    #[ApiProperty(writable: false)]
    public ?string $renderToken = null;
}
