<?php

namespace Shazzoo\ContentCatalogApi\Support\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Shazzoo\ContentCatalogApi\Support\BlockMapper;

final class ResourceTransformer
{
    /**
     * Media library model. Optional: without it, media fields only return the id.
     */
    public const MEDIA_MODEL = 'FinnWiel\\ShazzooMedia\\Models\\ShazzooMedia';

    public function __construct(private readonly BlockMapper $blocks) {}

    /**
     * @param  iterable<Model>  $records
     * @return array<int, array<string, mixed>>
     */
    public function collection(ResourceDefinition $definition, iterable $records): array
    {
        $records = collect($records);
        $media = $this->media($definition, $records);

        return $records
            ->map(fn (Model $record): array => $this->record($definition, $record, $media))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(ResourceDefinition $definition, Model $record): array
    {
        return $this->record($definition, $record, $this->media($definition, collect([$record])));
    }

    /**
     * @param  Collection<int|string, Model>  $media
     * @return array<string, mixed>
     */
    private function record(ResourceDefinition $definition, Model $record, Collection $media): array
    {
        $payload = ['id' => $record->getKey()];

        foreach ($definition->fields as $field) {
            $name = $field['name'];
            $value = $record->getAttribute($name);

            $payload[$name] = match ($field['type']) {
                'blocks' => $this->blocks->toApi(is_array($value) ? $value : []),
                'tags', 'repeater' => $value ?? [],
                default => $value,
            };

            // image_id => image: {id, url, alt}, next to the id itself.
            if ($field['type'] === 'media' && class_exists(self::MEDIA_MODEL)) {
                $item = $value !== null ? $media->get($value) : null;

                $payload[Str::beforeLast($name, '_id')] = $item ? [
                    'id' => $item->getKey(),
                    'url' => $item->url,
                    'alt' => $item->alt,
                ] : null;
            }
        }

        if ($record->usesTimestamps()) {
            $payload['updated_at'] = $record->getAttribute($record->getUpdatedAtColumn())?->toISOString();
        }

        return $payload;
    }

    /**
     * Load every referenced media item in one query.
     *
     * @param  Collection<int, Model>  $records
     * @return Collection<int|string, Model>
     */
    private function media(ResourceDefinition $definition, Collection $records): Collection
    {
        $mediaFields = collect($definition->fields)->where('type', 'media')->pluck('name');

        if ($mediaFields->isEmpty() || ! class_exists(self::MEDIA_MODEL)) {
            return collect();
        }

        $ids = $records
            ->flatMap(fn (Model $record) => $mediaFields->map(fn (string $name) => $record->getAttribute($name)))
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : (self::MEDIA_MODEL)::query()->whereKey($ids)->get()->keyBy(fn (Model $item) => $item->getKey());
    }
}
