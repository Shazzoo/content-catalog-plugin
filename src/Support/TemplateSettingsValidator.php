<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates template keys and template settings against the active theme.
 */
final class TemplateSettingsValidator
{
    public function __construct(
        private readonly TemplateCatalog $templates,
        private readonly BlockValidator $fields,
    ) {}

    /**
     * Rules for a template key field. Without a theme there is nothing to check
     * against, so any key is accepted.
     *
     * @return array<int, mixed>
     */
    public function keyRules(): array
    {
        $keys = $this->templates->keys();

        return $keys === [] ? ['string'] : ['string', Rule::in($keys)];
    }

    /**
     * Checks settings against the settings fields of the template. Without a
     * template, or for a template without settings, any object is accepted.
     */
    public function validate(Validator $validator, string $attribute, ?string $templateKey, mixed $settings): void
    {
        if ($validator->errors()->has($attribute) || ! is_array($settings) || blank($templateKey)) {
            return;
        }

        $definitions = $this->templates->settings($templateKey);

        if ($definitions === []) {
            return;
        }

        $this->fields->validateFields($validator, $attribute, $settings, $definitions);
    }
}
