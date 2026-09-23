<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Shazzoo\ContentStudioCore\Models\Page;

final class PageTransformer
{
    /** @return array<string, mixed> */
    public function transform(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'translation_key' => $page->translation_key,
            'locale' => $page->locale,
            'is_active' => $page->is_active,
            'template_key' => $page->template_key,
            'template_settings' => $page->template_settings ?? [],
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'seo' => $page->seo,
            'header' => $page->header,
            'updated_at' => $page->updated_at?->toISOString(),
            'blocks' => array_map(
                fn (array $block, int $position): array => $this->block($block, $position),
                array_values($page->content ?? []),
                array_keys(array_values($page->content ?? [])),
            ),
        ];
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
}
