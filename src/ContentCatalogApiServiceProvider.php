<?php

namespace Shazzoo\ContentCatalogApi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shazzoo\ContentCatalogApi\Http\Controllers\ContentCatalogController;
use Shazzoo\ContentCatalogApi\Http\Middleware\EnsureContentCatalogApiAccess;
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

        Route::middleware(['api', EnsureContentCatalogApiAccess::class])
            ->prefix('api/content-catalog')
            ->name('content-catalog-api.')
            ->group(function (): void {
                Route::get('/', [ContentCatalogController::class, 'index'])->name('index');
                Route::get('/blocks', [ContentCatalogController::class, 'blocks'])->name('blocks.index');
                Route::get('/pages', [ContentCatalogController::class, 'pages'])->name('pages.index');
                Route::get('/pages/{page}', [ContentCatalogController::class, 'showPage'])->whereNumber('page')->name('pages.show');
                Route::get('/plugins', [ContentCatalogController::class, 'plugins'])->name('plugins.index');
                Route::get('/plugins/{plugin}', [ContentCatalogController::class, 'showPlugin'])->name('plugins.show');
                Route::post('/pages', [ContentCatalogController::class, 'store'])
                    ->name('pages.store');
                Route::put('/pages/{page}', [ContentCatalogController::class, 'replace'])
                    ->whereNumber('page')
                    ->name('pages.replace');
                Route::patch('/pages/{page}', [ContentCatalogController::class, 'update'])
                    ->whereNumber('page')
                    ->name('pages.update');

                Route::prefix('/plugins/{plugin}/resources/{resource}')
                    ->name('resources.')
                    ->group(function (): void {
                        Route::get('/', [ContentCatalogController::class, 'resourceIndex'])->name('index');
                        Route::post('/', [ContentCatalogController::class, 'resourceStore'])->name('store');
                        Route::get('/{id}', [ContentCatalogController::class, 'resourceShow'])->name('show');
                        Route::put('/{id}', [ContentCatalogController::class, 'resourceReplace'])->name('replace');
                        Route::patch('/{id}', [ContentCatalogController::class, 'resourceUpdate'])->name('update');
                    });
            });
    }
}
