<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Validation\Validator;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;

/**
 * Validates a list of blocks against the block catalog. Used for page content
 * and for resource fields of the "blocks" type. The field check is also used
 * for template settings.
 */
final class BlockValidator
{
    public function __construct(private readonly BlockCatalog $catalog) {}

    /**
     * Structural rules for a blocks list at the given attribute.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(string $attribute, string $presence): array
    {
        return [
            $attribute => [$presence, 'array'],
            "{$attribute}.*" => ['array:type,uuid,fields'],
            "{$attribute}.*.type" => ['required', 'string'],
            "{$attribute}.*.uuid" => ['sometimes', 'nullable', 'uuid'],
            "{$attribute}.*.fields" => ['required', 'array'],
        ];
    }

    /**
     * Checks each block's type and fields against its definition.
     */
    public function validate(Validator $validator, string $attribute, mixed $blocks): void
    {
        if ($validator->errors()->has($attribute) || ! is_array($blocks)) {
            return;
        }

        $definitions = collect($this->catalog->toArray())->keyBy('type');

        foreach ($blocks as $index => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $definition = $definitions->get($block['type']);

            if (! is_array($definition)) {
                $validator->errors()->add("{$attribute}.{$index}.type", 'The selected block type is invalid.');

                continue;
            }

            $this->validateFields(
                $validator,
                "{$attribute}.{$index}.fields",
                is_array($block['fields'] ?? null) ? $block['fields'] : [],
                $definition['fields'] ?? [],
            );
        }
    }

    /**
     * Checks values against field definitions from the block or template
     * catalog: unknown fields, required fields, toggles, options, repeaters.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, array<string, mixed>>  $definitions
     */
    public function validateFields(Validator $validator, string $path, array $values, array $definitions): void
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

            if (in_array($type, ['select', 'radio'], true) && isset($field['options']) && ! $this->isValidOption($value, $field['options'])) {
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

    /**
     * The block catalog does not say whether a select allows several values,
     * so a list is accepted when every value in it is a valid option.
     *
     * @param  array<array-key, mixed>  $options
     */
    private function isValidOption(mixed $value, array $options): bool
    {
        if (is_array($value)) {
            return array_is_list($value)
                && collect($value)->every(fn (mixed $item): bool => is_scalar($item) && array_key_exists((string) $item, $options));
        }

        return is_scalar($value) && array_key_exists((string) $value, $options);
    }
}
