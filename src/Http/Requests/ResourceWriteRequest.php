<?php

namespace Shazzoo\ContentCatalogApi\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Shazzoo\ContentCatalogApi\Support\BlockValidator;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceDefinition;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceRegistry;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceTransformer;
use Shazzoo\ContentCatalogApi\Support\TemplateSettingsValidator;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST, PUT and PATCH for a plugin resource. The rules come from the field
 * types in the resource declaration.
 */
final class ResourceWriteRequest extends FormRequest
{
    private ?ResourceDefinition $definition = null;

    public function authorize(): bool
    {
        return true;
    }

    public function definition(): ResourceDefinition
    {
        return $this->definition ??= app(ResourceRegistry::class)->find(
            (string) $this->route('plugin'),
            (string) $this->route('resource'),
        ) ?? throw new NotFoundHttpException;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post') && ! $this->definition()->creatable) {
            throw new MethodNotAllowedHttpException(['GET', 'PUT', 'PATCH'], 'This resource cannot be created through the API.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $definition = $this->definition();
        $fullPayload = $this->isMethod('post') || $this->isMethod('put');
        $rules = [
            'if_updated_at' => ['sometimes', 'nullable', 'date'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        foreach ($definition->fields as $field) {
            $name = $field['name'];
            $required = ($field['required'] ?? false) && ! array_key_exists('default', $field);
            $presence = $fullPayload && $required ? 'required' : 'sometimes';
            $nullable = $required || $field['type'] === 'toggle' ? [] : ['nullable'];

            if ($field['type'] === 'blocks') {
                $rules += app(BlockValidator::class)->rules($name, $presence);
                $rules[$name] = [...$rules[$name], ...$nullable];

                continue;
            }

            $rules[$name] = [$presence, ...$nullable, ...$this->typeRules($definition, $field)];
            $rules += $this->nestedRules($field);
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $definition = $this->definition();
            $allowed = [...$definition->fieldNames(), 'if_updated_at', 'purpose'];

            foreach (array_keys($this->all()) as $name) {
                if (! in_array($name, $allowed, true)) {
                    $validator->errors()->add((string) $name, 'This field is not defined for the resource.');
                }
            }

            foreach ($definition->fields as $field) {
                if ($field['type'] === 'blocks') {
                    app(BlockValidator::class)->validate($validator, $field['name'], $this->input($field['name']));
                }

                if ($field['type'] === 'template_settings') {
                    $keyField = $field['template_from'] ?? 'template_key';

                    app(TemplateSettingsValidator::class)->validate(
                        $validator,
                        $field['name'],
                        $this->input($keyField, $this->record()?->getAttribute($keyField)),
                        $this->input($field['name']),
                    );
                }
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function typeRules(ResourceDefinition $definition, array $field): array
    {
        $rules = match ($field['type']) {
            'text' => ['string', 'max:'.($field['max'] ?? 255)],
            'textarea' => ['string'],
            'number' => ['numeric'],
            'toggle' => ['boolean'],
            'select' => ($field['multiple'] ?? false) ? ['array'] : [Rule::in($this->optionKeys($field))],
            'tags' => ['array'],
            'repeater', 'json', 'template_settings' => ['array'],
            'template' => app(TemplateSettingsValidator::class)->keyRules(),
            'media' => class_exists(ResourceTransformer::MEDIA_MODEL)
                ? ['integer', Rule::exists((new (ResourceTransformer::MEDIA_MODEL))->getTable(), 'id')]
                : ['integer'],
            default => [],
        };

        if ($field['unique'] ?? false) {
            $model = $definition->newModel();
            $rules[] = Rule::unique($model->getTable(), $field['name'])->ignore($this->route('id'), $model->getKeyName());
        }

        return $rules;
    }

    /**
     * The record being replaced or updated; null when creating.
     */
    private function record(): ?Model
    {
        $id = $this->route('id');

        return $id === null ? null : $this->definition()->newModel()->newQuery()->find($id);
    }

    /**
     * Rules for the items of list fields.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, array<int, mixed>>
     */
    private function nestedRules(array $field): array
    {
        $name = $field['name'];

        return match (true) {
            $field['type'] === 'tags' => ["{$name}.*" => ['string', 'max:255']],
            $field['type'] === 'select' && ($field['multiple'] ?? false) => ["{$name}.*" => [Rule::in($this->optionKeys($field))]],
            $field['type'] === 'repeater' => ["{$name}.*" => ['array']],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private function optionKeys(array $field): array
    {
        return array_map('strval', array_keys((array) ($field['options'] ?? [])));
    }
}
