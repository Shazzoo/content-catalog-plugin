<?php

namespace Shazzoo\ContentCatalogApi\Support\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * A plugin model that the API can list and write, described in the plugin's
 * plugin.json under "api_resources".
 */
final class ResourceDefinition
{
    /**
     * Field types the API understands.
     */
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'toggle', 'select', 'tags', 'media', 'repeater', 'blocks', 'json'];

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function __construct(
        public readonly string $plugin,
        public readonly string $key,
        public readonly string $label,
        public readonly string $model,
        public readonly array $fields,
        public readonly ?string $orderBy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $declaration
     */
    public static function fromArray(string $plugin, array $declaration): self
    {
        $key = $declaration['key'] ?? null;
        $model = $declaration['model'] ?? null;
        $fields = $declaration['fields'] ?? null;

        if (! is_string($key) || $key === '' || ! is_string($model) || ! is_array($fields)) {
            throw new InvalidArgumentException("Resource of plugin [{$plugin}] needs a key, a model and fields.");
        }

        foreach ($fields as $field) {
            if (! is_string($field['name'] ?? null) || ! in_array($field['type'] ?? null, self::FIELD_TYPES, true)) {
                throw new InvalidArgumentException("Resource [{$plugin}/{$key}] has a field without a name or with an unknown type.");
            }
        }

        return new self(
            plugin: $plugin,
            key: $key,
            label: is_string($declaration['label'] ?? null) ? $declaration['label'] : str($key)->headline()->toString(),
            model: $model,
            fields: array_values($fields),
            orderBy: is_string($declaration['order_by'] ?? null) ? $declaration['order_by'] : null,
        );
    }

    public function modelExists(): bool
    {
        return class_exists($this->model) && is_a($this->model, Model::class, true);
    }

    /**
     * False while the plugin's migrations have not run yet.
     */
    public function hasTable(): bool
    {
        return Schema::hasTable($this->newModel()->getTable());
    }

    public function newModel(): Model
    {
        return new $this->model;
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $model = $this->newModel();

        return $model->newQuery()->orderBy($this->orderBy ?? $model->getKeyName());
    }

    /**
     * @return array<int, string>
     */
    public function fieldNames(): array
    {
        return array_column($this->fields, 'name');
    }

    /**
     * The field definitions as the API shows them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function publicFields(): array
    {
        return array_map(fn (array $field): array => [
            'required' => false,
            ...$field,
        ], $this->fields);
    }
}
