<?php

namespace Shazzoo\ContentCatalogApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiSettings;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiRequestLog;
use Shazzoo\ContentStudioCore\Models\Page;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;

final class ContentCatalogController
{
    public function handle(?string $locale, ?string $slug): JsonResponse
    {
        abort_unless(trim((string) $slug, '/') === 'api/content-catalog', 404);

        /** @var Request $request */
        $request = request();

        $this->throttle($request);

        $settings = ContentCatalogApiSettings::current();

        abort_unless($settings->isAvailable(), 404);
        abort_unless($settings->hasValidKey($request->bearerToken()), 401);

        ContentCatalogApiRequestLog::query()->create([
            'operation' => 'content-catalog.read',
            'purpose' => filled($request->query('purpose')) ? str($request->query('purpose'))->limit(255)->toString() : null,
            'api_key_last_four' => $settings->api_key_last_four,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_path' => $request->path(),
            'requested_at' => now(),
        ]);

        /** @var BlockCatalog $blockCatalog */
        $blockCatalog = app(BlockCatalog::class);

        return response()->json([
            'data' => [
                'blocks' => $blockCatalog->toArray(),
                'pages' => Page::query()
                    ->select([
                        'id',
                        'title',
                        'slug',
                        'locale',
                        'is_active',
                        'template_key',
                        'updated_at',
                        'content',
                    ])
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Page $page): array => $this->page($page))
                    ->all(),
            ],
        ]);
    }

    private function throttle(Request $request): void
    {
        $key = 'content-catalog-api:'.$request->ip();

        abort_if(RateLimiter::tooManyAttempts($key, 60), 429);

        RateLimiter::hit($key, 60);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'locale' => $page->locale,
            'is_active' => $page->is_active,
            'template_key' => $page->template_key,
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

            if (! $this->isFilled($value)) {
                continue;
            }

            $filledValues[$key] = $value;
        }

        return array_is_list($values) ? array_values($filledValues) : $filledValues;
    }

    private function isFilled(mixed $value): bool
    {
        return $value !== null
            && $value !== []
            && (! is_string($value) || trim($value) !== '');
    }
}
