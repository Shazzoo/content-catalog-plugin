<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiSettings;

final class ContentCatalogApiGuard
{
    public function authorize(Request $request): ContentCatalogApiSettings
    {
        $key = 'content-catalog-api:'.$request->ip();

        abort_if(RateLimiter::tooManyAttempts($key, 60), 429);
        RateLimiter::hit($key, 60);

        $settings = ContentCatalogApiSettings::current();

        abort_unless($settings->isAvailable(), 404);
        abort_unless($settings->hasValidKey($request->bearerToken()), 401);

        $request->attributes->set('content_catalog_api_settings', $settings);

        return $settings;
    }
}
