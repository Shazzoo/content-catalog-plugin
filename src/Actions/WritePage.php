<?php

namespace Shazzoo\ContentCatalogApi\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Shazzoo\ContentCatalogApi\Support\BlockMapper;
use Shazzoo\ContentStudioCore\Models\Page;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WritePage
{
    public function __construct(private readonly BlockMapper $blocks) {}

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
            $attributes['content'] = $this->blocks->toContent($payload['blocks']);
        }

        // Linking to another language version shares its translation key;
        // unlinking (null) gives the page a group of its own.
        if (filled($payload['translation_of'] ?? null)) {
            $attributes['translation_key'] = Page::query()->findOrFail($payload['translation_of'])->translation_key;
        } elseif (array_key_exists('translation_key', $attributes) && blank($attributes['translation_key'])) {
            $attributes['translation_key'] = (string) Str::uuid();
        }

        return $attributes;
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
