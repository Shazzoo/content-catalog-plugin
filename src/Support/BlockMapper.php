<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Support\Str;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;

/**
 * Converts between API blocks ({type, uuid, fields}) and stored content
 * ({uuid, type, data}).
 */
final class BlockMapper
{
    public function __construct(private readonly BlockCatalog $catalog) {}

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array{uuid: string, type: string, data: array<string, mixed>}>
     */
    public function toContent(array $blocks): array
    {
        $definitions = collect($this->catalog->toArray())->keyBy('type');

        return array_map(fn (array $block): array => [
            'uuid' => filled($block['uuid'] ?? null) ? $block['uuid'] : (string) Str::uuid(),
            'type' => $block['type'],
            'data' => $this->withDefaults($block['fields'], $definitions->get($block['type'])['fields'] ?? []),
        ], array_values($blocks));
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     * @return array<int, array<string, mixed>>
     */
    public function toApi(array $content): array
    {
        return array_map(
            fn (array $block, int $position): array => $this->block($block, $position),
            array_values($content),
            array_keys(array_values($content)),
        );
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function block(array $block, int $position): array
    {
        $payload = [
            'position' => $position,
            'type' => $block['type'] ?? null,
            'fields' => $this->filledValues($block['data'] ?? []),
        ];

        if (filled($block['uuid'] ?? null)) {
            $payload['uuid'] = $block['uuid'];
        }

        return $payload;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function filledValues(array $values): array
    {
        $filledValues = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = $this->filledValues($value);
            }

            if ($value === null || $value === [] || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $filledValues[$key] = $value;
        }

        return array_is_list($values) ? array_values($filledValues) : $filledValues;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, mixed>
     */
    private function withDefaults(array $values, array $definitions): array
    {
        foreach ($definitions as $field) {
            $name = $field['name'];

            if (! array_key_exists($name, $values) && array_key_exists('default', $field)) {
                $values[$name] = $field['default'];
            }

            if (($field['type'] ?? null) !== 'repeater' || ! is_array($values[$name] ?? null)) {
                continue;
            }

            $values[$name] = array_map(
                fn (array $item): array => $this->withDefaults($item, $field['schema'] ?? []),
                $values[$name],
            );
        }

        return $values;
    }
}
