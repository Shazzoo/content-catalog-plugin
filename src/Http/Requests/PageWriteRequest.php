<?php

namespace Shazzoo\ContentCatalogApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Shazzoo\ContentStudioCore\Models\Page;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;

abstract class PageWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fullPayload = $this->isMethod('post') || $this->isMethod('put');
        $required = $fullPayload ? 'required' : 'sometimes';
        $pageId = $this->route('page');

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique((new Page)->getTable(), 'slug')
                ->where(fn ($query) => $query->where('locale', $this->input('locale', $this->routePage()?->locale ?? config('app.locale'))))
                ->ignore($pageId)],
            'translation_key' => ['sometimes', 'nullable', 'uuid'],
            'locale' => [$required, 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
            'template_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'template_settings' => ['sometimes', 'array'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string'],
            'seo' => ['sometimes', 'nullable', 'array'],
            'header' => ['sometimes', 'nullable', 'array'],
            'blocks' => [$required, 'array'],
            'blocks.*' => ['array:type,uuid,fields'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.uuid' => ['sometimes', 'nullable', 'uuid'],
            'blocks.*.fields' => ['required', 'array'],
            'if_updated_at' => ['sometimes', 'nullable', 'date'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $page = $this->routePage();
            $slug = $this->input('slug', $page?->slug);
            $locale = $this->input('locale', $page?->locale);

            if ($page !== null && is_string($slug) && is_string($locale)) {
                $duplicateExists = Page::query()
                    ->where('slug', $slug)
                    ->where('locale', $locale)
                    ->whereKeyNot($page->getKey())
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add('slug', 'The slug has already been taken for this locale.');
                }
            }

            if ($validator->errors()->has('blocks') || ! is_array($this->input('blocks'))) {
                return;
            }

            /** @var BlockCatalog $catalog */
            $catalog = app(BlockCatalog::class);
            $definitions = collect($catalog->toArray())->keyBy('type');

            foreach ($this->input('blocks', []) as $index => $block) {
                if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                    continue;
                }

                $definition = $definitions->get($block['type']);

                if (! is_array($definition)) {
                    $validator->errors()->add("blocks.{$index}.type", 'The selected block type is invalid.');

                    continue;
                }

                $this->validateFields(
                    $validator,
                    "blocks.{$index}.fields",
                    is_array($block['fields'] ?? null) ? $block['fields'] : [],
                    $definition['fields'] ?? [],
                );
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function validateFields(Validator $validator, string $path, array $values, array $definitions): void
    {
        $fields = collect($definitions)->keyBy('name');

        foreach (array_keys($values) as $name) {
            if (! is_string($name) || ! $fields->has($name)) {
                $validator->errors()->add("{$path}.{$name}", 'This field is not defined for the block.');
            }
        }

        foreach ($fields as $name => $field) {
            $hasUsableDefault = array_key_exists('default', $field) && $field['default'] !== null;
            $missingRequiredValue = ! array_key_exists($name, $values)
                || (($field['type'] ?? null) !== 'toggle' && blank($values[$name]));

            if (($field['required'] ?? false) && ! $hasUsableDefault && $missingRequiredValue) {
                $validator->errors()->add("{$path}.{$name}", 'This field is required.');
            }

            if (! array_key_exists($name, $values)) {
                continue;
            }

            $value = $values[$name];
            $type = $field['type'] ?? null;

            if ($type === 'toggle' && ! is_bool($value)) {
                $validator->errors()->add("{$path}.{$name}", 'This field must be true or false.');
            }

            if (in_array($type, ['select', 'radio'], true) && isset($field['options']) && ! array_key_exists((string) $value, $field['options'])) {
                $validator->errors()->add("{$path}.{$name}", 'The selected value is invalid.');
            }

            if ($type === 'repeater') {
                if (! is_array($value)) {
                    $validator->errors()->add("{$path}.{$name}", 'This field must be an array.');

                    continue;
                }

                foreach ($value as $itemIndex => $item) {
                    if (! is_array($item)) {
                        $validator->errors()->add("{$path}.{$name}.{$itemIndex}", 'This item must be an object.');

                        continue;
                    }

                    $this->validateFields($validator, "{$path}.{$name}.{$itemIndex}", $item, $field['schema'] ?? []);
                }
            }
        }
    }

    private function routePage(): ?Page
    {
        $page = $this->route('page');

        return $page instanceof Page ? $page : Page::query()->find($page);
    }
}
