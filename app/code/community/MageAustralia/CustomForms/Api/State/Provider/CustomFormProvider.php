<?php

declare(strict_types=1);

namespace MageAustralia\CustomForms\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use MageAustralia\CustomForms\Api\Resource\CustomForm;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Provides a public form definition for headless rendering. Only active forms
 * enabled for the current store are exposed (getForm() enforces both) - any
 * other code returns 404, so the endpoint can't be used to enumerate disabled
 * or other-store forms.
 */
class CustomFormProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomForm
    {
        $code = (string) ($uriVariables['code'] ?? '');

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

        $resource = new CustomForm();
        $resource->code = (string) $form->getCode();
        $resource->name = (string) $form->getName();
        $resource->schema = $form->getDecodedSchema();
        // Stateless token: the storefront returns it on POST. No session needed.
        $resource->renderToken = $helper->signRenderToken((string) $form->getCode());

        $required = $helper->formRequiresCaptcha($form, $storeId);
        $captcha = ['required' => $required];
        if ($required) {
            try {
                $captcha['challengeUrl'] = (string) \Mage::helper('captcha')->getChallengeUrl();
            } catch (\Throwable $e) {
                // Captcha helper unavailable: omit URL, leave required=true.
            }
        }
        $resource->captcha = $captcha;

        return $resource;
    }
}
