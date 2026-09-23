<?php

namespace Shazzoo\ContentCatalogApi\Models;

use Illuminate\Database\Eloquent\Model;

final class ContentCatalogApiRequestLog extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'content_catalog_api_request_logs';

    protected $fillable = [
        'api_key_last_four',
        'ip_address',
        'operation',
        'purpose',
        'request_path',
        'requested_at',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
        ];
    }
}
