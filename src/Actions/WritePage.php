<?php

namespace Shazzoo\ContentCatalogApi\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Shazzoo\ContentStudioCore\Models\Page;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WritePage
{
    public function __construct(private readonly BlockCatalog $blocks) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload): Page
    {
        return DB::transaction(function () use ($payload): Page {
            $actorId = $this->actorId();
            $page = new Page($this->attributes($payload));
            $page->created_by = $actorId;
            $page->updated_by = $actorId;
            $page->save();

            return $page->refresh();
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(int $pageId, array $payload): Page
    {
        return DB::transaction(function () use ($pageId, $payload): Page {
            $page = Page::query()->lockForUpdate()->findOrFail($pageId);

            if (filled($payload['if_updated_at'] ?? null) && ! $page->updated_at?->equalTo($payload['if_updated_at'])) {
                throw new ConflictHttpException('The page has changed since it was fetched. Fetch it again before updating.');
            }

            $page->fill($this->attributes($payload));
            $page->updated_by = $this->actorId();
            $page->save();

            return $page->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributes(array $payload): array
    {
        $attributes = Arr::only($payload, [
            'title', 'slug', 'translation_key', 'locale', 'is_active', 'template_key',
            'template_settings', 'seo_title', 'seo_description', 'seo', 'header',
        ]);

        if (array_key_exists('blocks', $payload)) {
            $definitions = collect($this->blocks->toArray())->keyBy('type');

            $attributes['content'] = array_map(fn (array $block): array => [
                'uuid' => filled($block['uuid'] ?? null) ? $block['uuid'] : (string) Str::uuid(),
                'type' => $block['type'],
                'data' => $this->withDefaults($block['fields'], $definitions->get($block['type'])['fields'] ?? []),
            ], $payload['blocks']);
        }

        return $attributes;
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

    private function actorId(): int
    {
        $modelClass = config('auth.providers.users.model');

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new UnprocessableEntityHttpException('No valid API author model is configured.');
        }

        /** @var Model $model */
        $model = new $modelClass;
        $query = $modelClass::query();

        if (Schema::hasColumn($model->getTable(), 'is_admin')) {
            $adminId = (clone $query)->where('is_admin', true)->value($model->getKeyName());

            if ($adminId !== null) {
                return (int) $adminId;
            }
        }

        $actorId = $query->orderBy($model->getKeyName())->value($model->getKeyName());

        if ($actorId === null) {
            throw new UnprocessableEntityHttpException('Create an administrator before creating pages through the API.');
        }

        return (int) $actorId;
    }
}
