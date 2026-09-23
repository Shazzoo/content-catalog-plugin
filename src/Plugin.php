<?php

namespace Shazzoo\ContentCatalogApi;

final class Plugin
{
    public static function key(): string
    {
        return 'shazzoo/content-catalog-api';
    }

    public static function provider(): string
    {
        return ContentCatalogApiServiceProvider::class;
    }
}
