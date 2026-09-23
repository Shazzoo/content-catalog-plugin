<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Shazzoo\ContentStudioCore\Models\Page;

final class PageTransformer
{
    public function __construct(private readonly BlockMapper $blocks) {}

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
            'blocks' => $this->blocks->toApi($page->content ?? []),
        ];
    }
}
