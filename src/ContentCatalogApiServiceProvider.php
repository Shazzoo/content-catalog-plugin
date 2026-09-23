<?php

namespace Shazzoo\ContentCatalogApi;

use Shazzoo\ContentCatalogApi\Http\Controllers\ContentCatalogController;
use Illuminate\Support\ServiceProvider;
use Shazzoo\ContentStudioCore\Support\Routing\PluginRouteRegistry;

final class ContentCatalogApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(PluginRouteRegistry::class)->register('api', ContentCatalogController::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'content-catalog-api');

    }
}
