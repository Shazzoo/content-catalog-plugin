<?php

namespace Shazzoo\ContentCatalogApi\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Shazzoo\ContentCatalogApi\Support\BlockMapper;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceDefinition;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class WriteResource
{
    public function __construct(private readonly BlockMapper $blocks) {}

    /** @param array<string, mixed> $payload */
    public function create(ResourceDefinition $definition, array $payload): Model
    {
        return DB::transaction(function () use ($definition, $payload): Model {
            $record = $definition->newModel();
            $record->forceFill($this->attributes($definition, $payload, withDefaults: true));
            $record->save();

            return $record->refresh();
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(ResourceDefinition $definition, int|string $id, array $payload): Model
    {
        return DB::transaction(function () use ($definition, $id, $payload): Model {
            $record = $definition->newModel()->newQuery()->lockForUpdate()->findOrFail($id);

            if (filled($payload['if_updated_at'] ?? null) && $record->usesTimestamps()
                && ! $record->getAttribute($record->getUpdatedAtColumn())?->equalTo($payload['if_updated_at'])) {
                throw new ConflictHttpException('The record has changed since it was fetched. Fetch it again before updating.');
            }

            $attributes = $this->attributes($definition, $payload, withDefaults: false);

            // Merge fields keep the keys the request does not send.
            foreach ($definition->fields as $field) {
                if (($field['merge'] ?? false) && is_array($attributes[$field['name']] ?? null)) {
                    $attributes[$field['name']] = array_replace(
                        (array) ($record->getAttribute($field['name']) ?? []),
                        $attributes[$field['name']],
                    );
                }
            }

            $record->forceFill($attributes);
            $record->save();

            return $record->refresh();
        });
    }

    /**
     * Only declared fields are written, so forceFill cannot reach other columns.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributes(ResourceDefinition $definition, array $payload, bool $withDefaults): array
    {
        $attributes = Arr::only($payload, $definition->fieldNames());

        foreach ($definition->fields as $field) {
            $name = $field['name'];

            if ($withDefaults && ! array_key_exists($name, $attributes) && array_key_exists('default', $field)) {
                $attributes[$name] = $field['default'];
            }

            if ($field['type'] === 'blocks' && is_array($attributes[$name] ?? null)) {
                $attributes[$name] = $this->blocks->toContent($attributes[$name]);
            }
        }

        return $attributes;
    }
}
