<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Shazzoo\ContentStudioCore\Support\Blocks\Components\Component;
use Shazzoo\ContentStudioCore\Support\Fields\Definitions\Repeater;
use Shazzoo\ContentStudioCore\Support\Templates\TemplateSettingsDefinition;
use Shazzoo\ContentStudioCore\Support\Theming\TemplateDefinitionRegistry;
use Shazzoo\ContentStudioCore\Support\Theming\TemplateRegistry;

/**
 * Page templates of the active theme with the fields of their settings, in
 * the same shape as block fields.
 */
final class TemplateCatalog
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly TemplateDefinitionRegistry $definitions,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return collect($this->templates->all())
            ->map(fn (array $template, string $key): array => [
                'key' => $key,
                'label' => $template['label'] ?? $key,
                'description' => $template['description'] ?? null,
                'settings' => $this->settings($key),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->templates->all());
    }

    /**
     * Settings fields of a template; empty when it has none.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settings(string $key): array
    {
        $resolver = $this->definitions->get($key);
        $definition = $resolver ? $resolver() : null;

        if (! $definition instanceof TemplateSettingsDefinition) {
            return [];
        }

        return $this->normalizeFields($definition->schema);
    }

    /**
     * @param  array<int, Component>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFields(array $fields): array
    {
        return array_values(array_map(function (Component $field): array {
            $data = [
                'name' => $field->name,
                'type' => $field->type,
                'label' => $field->label,
                'required' => $field->required,
                'default' => $field->default,
            ];

            if (property_exists($field, 'options')) {
                $data['options'] = $field->options;
            }

            if ($field instanceof Repeater) {
                $data['schema'] = $this->normalizeFields($field->schema);
            }

            return array_filter($data, fn (mixed $value): bool => $value !== null);
        }, $fields));
    }
}
