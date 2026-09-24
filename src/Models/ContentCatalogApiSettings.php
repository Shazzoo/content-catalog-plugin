<?php

namespace Shazzoo\ContentCatalogApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ContentCatalogApiSettings extends Model
{
    protected $table = 'content_catalog_api_settings';

    protected $fillable = [
        'disable_after_enabled',
        'disable_after_hours',
        'enabled',
        'expires_at',
    ];

    public $incrementing = false;

    protected $attributes = [
        'disable_after_enabled' => false,
        'enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'disable_after_enabled' => 'boolean',
            'disable_after_hours' => 'integer',
            'enabled' => 'boolean',
            'expires_at' => 'datetime',
            'last_rotated_at' => 'datetime',
        ];
    }

    /**
     * There is one settings row, always with id 1. The id is set here rather
     * than through firstOrCreate(): it is not fillable, so firstOrCreate()
     * would leave it out of the insert, which MySQL rejects.
     */
    public static function current(): self
    {
        $settings = self::query()->find(1);

        if ($settings !== null) {
            return $settings;
        }

        $settings = new self(['enabled' => false]);
        $settings->id = 1;
        $settings->save();

        return $settings->refresh();
    }

    public function isAvailable(): bool
    {
        if (! $this->enabled || blank($this->api_key_hash)) {
            return false;
        }

        if ($this->expires_at?->isPast()) {
            $this->forceFill(['enabled' => false])->save();

            return false;
        }

        return true;
    }

    public function hasValidKey(?string $key): bool
    {
        if (blank($key) || blank($this->api_key_hash)) {
            return false;
        }

        return hash_equals($this->api_key_hash, hash('sha256', $key));
    }

    public function rotateApiKey(): string
    {
        $key = 'cca_'.Str::random(64);

        $this->forceFill([
            'api_key_hash' => hash('sha256', $key),
            'api_key_last_four' => substr($key, -4),
            'last_rotated_at' => now(),
        ])->save();

        return $key;
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->expires_at = $this->disable_after_enabled && $this->disable_after_hours
            ? now()->addHours($this->disable_after_hours)
            : null;
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->expires_at = null;
    }
}
