<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Shazzoo\ContentCatalogApi\Support\Resources\ResourceDefinition;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceRegistry;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceTransformer;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;
use Shazzoo\ContentStudioCore\Support\Plugins\PluginManager;

final class PluginCatalog
{
    public function __construct(
        private readonly PluginManager $plugins,
        private readonly BlockCatalog $blocks,
        private readonly ResourceRegistry $resources,
        private readonly ResourceTransformer $transformer,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $activeKeys = $this->plugins->activeKeys();
        $blocks = $this->blocks->toArray();

        return collect($this->plugins->all())
            ->filter(fn (array $plugin, string $key): bool => in_array($key, $activeKeys, true))
            ->map(fn (array $plugin, string $key): array => $this->plugin($key, $plugin, $blocks))
            ->sortBy('slug')
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return collect($this->all())->firstWhere('slug', $slug);
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function plugin(string $key, array $plugin, array $blocks): array
    {
        $slug = (string) ($plugin['slug'] ?? str($key)->after('/'));
        $prefixes = collect([$slug, str($key)->after('/')->toString()])
            ->map(fn (string $value): string => (str_ends_with($value, '-plugin') ? substr($value, 0, -7) : $value).'.')
            ->unique();

        return [
            'key' => $key,
            'slug' => $slug,
            'name' => $plugin['name'] ?? str($slug)->headline()->toString(),
            'description' => $plugin['description'] ?? null,
            'version' => $plugin['version'] ?? null,
            'source' => $plugin['source'] ?? null,
            'blocks' => collect($blocks)
                ->filter(fn (array $block): bool => $prefixes->contains(fn (string $prefix): bool => str_starts_with((string) ($block['type'] ?? ''), $prefix)))
                ->values()
                ->all(),
            'resources' => array_map(
                fn (ResourceDefinition $resource): array => $this->resource($slug, $resource),
                $this->resources->forPlugin($key, $plugin),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function resource(string $pluginSlug, ResourceDefinition $resource): array
    {
        $items = $resource->hasTable()
            ? $this->transformer->collection($resource, $resource->query()->get())
            : [];

        return [
            'key' => $resource->key,
            'label' => $resource->label,
            'endpoint' => "/api/content-catalog/plugins/{$pluginSlug}/resources/{$resource->key}",
            'fields' => $resource->publicFields(),
            'items_count' => count($items),
            'items' => $items,
        ];
    }
}
