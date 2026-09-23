<?php

namespace Shazzoo\ContentCatalogApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shazzoo\ContentCatalogApi\Actions\WritePage;
use Shazzoo\ContentCatalogApi\Http\Requests\StorePageRequest;
use Shazzoo\ContentCatalogApi\Http\Requests\UpdatePageRequest;
use Shazzoo\ContentCatalogApi\Support\ContentCatalogApiGuard;
use Shazzoo\ContentCatalogApi\Support\ContentCatalogRequestLogger;
use Shazzoo\ContentCatalogApi\Support\PageTransformer;
use Shazzoo\ContentCatalogApi\Support\PluginCatalog;
use Shazzoo\ContentStudioCore\Models\Page;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;

final class ContentCatalogController
{
    public function __construct(
        private readonly ContentCatalogApiGuard $guard,
        private readonly ContentCatalogRequestLogger $logger,
        private readonly BlockCatalog $blocks,
        private readonly PluginCatalog $plugins,
        private readonly PageTransformer $pages,
        private readonly WritePage $writer,
    ) {}

    public function handle(?string $locale, ?string $slug): JsonResponse
    {
        $path = trim((string) $slug, '/');
        abort_unless(str_starts_with($path, 'api/content-catalog'), 404);

        /** @var Request $request */
        $request = request();
        $this->guard->authorize($request);

        return match (true) {
            $path === 'api/content-catalog' => $this->index($request),
            $path === 'api/content-catalog/blocks' => $this->blocks($request),
            $path === 'api/content-catalog/pages' => $this->pages($request),
            preg_match('#^api/content-catalog/pages/(\d+)$#', $path, $matches) === 1 => $this->showPage($request, (int) $matches[1]),
            $path === 'api/content-catalog/plugins' => $this->plugins($request),
            preg_match('#^api/content-catalog/plugins/([^/]+)$#', $path, $matches) === 1 => $this->showPlugin($request, $matches[1]),
            default => abort(404),
        };
    }

    public function index(Request $request): JsonResponse
    {
        return $this->readResponse($request, [
            'blocks' => $this->blocks->toArray(),
            'pages' => $this->allPages(),
            'plugins' => $this->plugins->all(),
        ]);
    }

    public function blocks(Request $request): JsonResponse
    {
        return $this->readResponse($request, $this->blocks->toArray());
    }

    public function pages(Request $request): JsonResponse
    {
        return $this->readResponse($request, $this->allPages());
    }

    public function showPage(Request $request, int $page): JsonResponse
    {
        return $this->readResponse(
            $request,
            $this->pages->transform(Page::query()->findOrFail($page)),
        );
    }

    public function plugins(Request $request): JsonResponse
    {
        return $this->readResponse($request, $this->plugins->all(), 'content-catalog.plugins.read');
    }

    public function showPlugin(Request $request, string $plugin): JsonResponse
    {
        $payload = $this->plugins->find($plugin);
        abort_if($payload === null, 404);

        return $this->readResponse($request, $payload, 'content-catalog.plugins.read');
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $page = $this->writer->create($request->validated());
        $this->logger->log($request, 'content-catalog.page.create');

        return response()->json(['data' => $this->pages->transform($page)], 201);
    }

    public function replace(UpdatePageRequest $request, int $page): JsonResponse
    {
        return $this->write($request, $page, 'content-catalog.page.replace');
    }

    public function update(UpdatePageRequest $request, int $page): JsonResponse
    {
        return $this->write($request, $page, 'content-catalog.page.update');
    }

    /** @return array<int, array<string, mixed>> */
    private function allPages(): array
    {
        return Page::query()
            ->select([
                'id', 'title', 'slug', 'translation_key', 'locale', 'is_active',
                'template_key', 'template_settings', 'seo_title', 'seo_description',
                'seo', 'header', 'updated_at', 'content',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): array => $this->pages->transform($page))
            ->all();
    }

    private function write(UpdatePageRequest $request, int $pageId, string $operation): JsonResponse
    {
        $page = $this->writer->update($pageId, $request->validated());
        $this->logger->log($request, $operation);

        return response()->json(['data' => $this->pages->transform($page)]);
    }

    private function readResponse(Request $request, array $data, string $operation = 'content-catalog.read'): JsonResponse
    {
        $this->logger->log($request, $operation);

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'no-store, private');
    }
}
