<?php

namespace Shazzoo\ContentCatalogApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shazzoo\ContentCatalogApi\Actions\WritePage;
use Shazzoo\ContentCatalogApi\Actions\WriteResource;
use Shazzoo\ContentCatalogApi\Http\Requests\ResourceWriteRequest;
use Shazzoo\ContentCatalogApi\Http\Requests\StorePageRequest;
use Shazzoo\ContentCatalogApi\Http\Requests\UpdatePageRequest;
use Shazzoo\ContentCatalogApi\Support\ContentCatalogApiGuard;
use Shazzoo\ContentCatalogApi\Support\ContentCatalogRequestLogger;
use Shazzoo\ContentCatalogApi\Support\PageTransformer;
use Shazzoo\ContentCatalogApi\Support\PluginCatalog;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceDefinition;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceRegistry;
use Shazzoo\ContentCatalogApi\Support\Resources\ResourceTransformer;
use Shazzoo\ContentCatalogApi\Support\TemplateCatalog;
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
        private readonly ResourceRegistry $resources,
        private readonly ResourceTransformer $records,
        private readonly WriteResource $resourceWriter,
        private readonly TemplateCatalog $templates,
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
            $path === 'api/content-catalog/templates' => $this->templates($request),
            preg_match('#^api/content-catalog/templates/([^/]+)$#', $path, $matches) === 1 => $this->showTemplate($request, $matches[1]),
            $path === 'api/content-catalog/plugins' => $this->plugins($request),
            preg_match('#^api/content-catalog/plugins/([^/]+)$#', $path, $matches) === 1 => $this->showPlugin($request, $matches[1]),
            preg_match('#^api/content-catalog/plugins/([^/]+)/resources/([^/]+)$#', $path, $matches) === 1 => $this->resourceIndex($request, $matches[1], $matches[2]),
            preg_match('#^api/content-catalog/plugins/([^/]+)/resources/([^/]+)/([^/]+)$#', $path, $matches) === 1 => $this->resourceShow($request, $matches[1], $matches[2], $matches[3]),
            default => abort(404),
        };
    }

    public function index(Request $request): JsonResponse
    {
        return $this->readResponse($request, [
            'blocks' => $this->blocks->toArray(),
            'pages' => $this->allPages(),
            'plugins' => $this->plugins->all(),
            'templates' => $this->templates->all(),
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

    public function templates(Request $request): JsonResponse
    {
        return $this->readResponse($request, $this->templates->all());
    }

    public function showTemplate(Request $request, string $template): JsonResponse
    {
        $payload = collect($this->templates->all())->firstWhere('key', $template);
        abort_if($payload === null, 404);

        return $this->readResponse($request, $payload);
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

    public function resourceIndex(Request $request, string $plugin, string $resource): JsonResponse
    {
        $definition = $this->resource($plugin, $resource);

        return $this->readResponse(
            $request,
            $this->records->collection($definition, $definition->query()->get()),
            'content-catalog.resource.read',
        );
    }

    public function resourceShow(Request $request, string $plugin, string $resource, string $id): JsonResponse
    {
        $definition = $this->resource($plugin, $resource);

        return $this->readResponse(
            $request,
            $this->records->transform($definition, $definition->newModel()->newQuery()->findOrFail($id)),
            'content-catalog.resource.read',
        );
    }

    public function resourceStore(ResourceWriteRequest $request): JsonResponse
    {
        $definition = $request->definition();
        $record = $this->resourceWriter->create($definition, $request->validated());
        $this->logger->log($request, 'content-catalog.resource.create');

        return response()->json(['data' => $this->records->transform($definition, $record)], 201);
    }

    public function resourceReplace(ResourceWriteRequest $request, string $plugin, string $resource, string $id): JsonResponse
    {
        return $this->writeResource($request, $id, 'content-catalog.resource.replace');
    }

    public function resourceUpdate(ResourceWriteRequest $request, string $plugin, string $resource, string $id): JsonResponse
    {
        return $this->writeResource($request, $id, 'content-catalog.resource.update');
    }

    private function resource(string $plugin, string $resource): ResourceDefinition
    {
        $definition = $this->resources->find($plugin, $resource);
        abort_if($definition === null, 404);

        return $definition;
    }

    private function writeResource(ResourceWriteRequest $request, string $id, string $operation): JsonResponse
    {
        $definition = $request->definition();
        $record = $this->resourceWriter->update($definition, $id, $request->validated());
        $this->logger->log($request, $operation);

        return response()->json(['data' => $this->records->transform($definition, $record)]);
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
