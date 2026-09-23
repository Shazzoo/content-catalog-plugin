<?php

namespace Shazzoo\ContentCatalogApi\Support\Resources;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Shazzoo\ContentStudioCore\Support\Plugins\PluginManager;

/**
 * Finds the resources of active plugins: the "api_resources" section of each
 * plugin's plugin.json, or a built-in declaration for older plugins.
 */
final class ResourceRegistry
{
    public function __construct(private readonly PluginManager $plugins) {}

    /**
     * @param  array<string, mixed>  $plugin  Plugin metadata from the plugin manager.
     * @return array<int, ResourceDefinition>
     */
    public function forPlugin(string $pluginKey, array $plugin): array
    {
        $declarations = is_array($plugin['api_resources'] ?? null)
            ? $plugin['api_resources']
            : BuiltInResources::for($pluginKey);

        $definitions = [];

        foreach ($declarations as $declaration) {
            try {
                $definition = ResourceDefinition::fromArray($pluginKey, (array) $declaration);
            } catch (InvalidArgumentException $exception) {
                Log::warning('[Content Catalog API] '.$exception->getMessage());

                continue;
            }

            if ($definition->modelExists()) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Resolve a resource by plugin slug and resource key, among active plugins.
     * A resource whose table does not exist yet cannot be read or written.
     */
    public function find(string $pluginSlug, string $resourceKey): ?ResourceDefinition
    {
        $activeKeys = $this->plugins->activeKeys();

        foreach ($this->plugins->all() as $pluginKey => $plugin) {
            if (! in_array($pluginKey, $activeKeys, true) || ($plugin['slug'] ?? null) !== $pluginSlug) {
                continue;
            }

            foreach ($this->forPlugin($pluginKey, $plugin) as $definition) {
                if ($definition->key === $resourceKey) {
                    return $definition->hasTable() ? $definition : null;
                }
            }
        }

        return null;
    }
}
