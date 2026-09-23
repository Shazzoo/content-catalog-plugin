<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Http\Request;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiRequestLog;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiSettings;

final class ContentCatalogRequestLogger
{
    public function log(Request $request, string $operation): void
    {
        $settings = $request->attributes->get('content_catalog_api_settings');

        if (! $settings instanceof ContentCatalogApiSettings) {
            $settings = ContentCatalogApiSettings::current();
        }

        $purpose = $request->input('purpose', $request->query('purpose'));

        ContentCatalogApiRequestLog::query()->create([
            'operation' => $operation,
            'purpose' => filled($purpose) ? str((string) $purpose)->limit(255)->toString() : null,
            'api_key_last_four' => $settings->api_key_last_four,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_path' => $request->path(),
            'requested_at' => now(),
        ]);
    }
}
