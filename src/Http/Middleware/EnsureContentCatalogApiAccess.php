<?php

namespace Shazzoo\ContentCatalogApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shazzoo\ContentCatalogApi\Support\ContentCatalogApiGuard;
use Symfony\Component\HttpFoundation\Response;

final class EnsureContentCatalogApiAccess
{
    public function __construct(private readonly ContentCatalogApiGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->guard->authorize($request);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
