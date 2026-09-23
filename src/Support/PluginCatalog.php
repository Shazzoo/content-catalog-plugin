<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Support\Facades\Schema;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockCatalog;
use Shazzoo\ContentStudioCore\Support\Plugins\PluginManager;

final class PluginCatalog
{
    public function __construct(
        private readonly PluginManager $plugins,
        private readonly BlockCatalog $blocks,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $activeKeys = $this->plugins->activeKeys();
        $blocks = $this->blocks->toArray();

        return collect($this->plugins->all())
            ->filter(fn (array $plugin, string $key): bool => in_array($key, $activeKeys, true))
            ->map(fn (array $plugin, string $key): array => $this->plugin($key, $plugin, $blocks))
            ->sortBy('slug')
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return collect($this->all())->firstWhere('slug', $slug);
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function plugin(string $key, array $plugin, array $blocks): array
    {
        $slug = (string) ($plugin['slug'] ?? str($key)->after('/'));
        $prefixes = collect([$slug, str($key)->after('/')->toString()])
            ->map(fn (string $value): string => (str_ends_with($value, '-plugin') ? substr($value, 0, -7) : $value).'.')
            ->unique();

        $payload = [
            'key' => $key,
            'slug' => $slug,
            'name' => $plugin['name'] ?? str($slug)->headline()->toString(),
            'description' => $plugin['description'] ?? null,
            'version' => $plugin['version'] ?? null,
            'source' => $plugin['source'] ?? null,
            'blocks' => collect($blocks)
                ->filter(fn (array $block): bool => $prefixes->contains(fn (string $prefix): bool => str_starts_with((string) ($block['type'] ?? ''), $prefix)))
                ->values()
                ->all(),
            'resources' => [],
        ];

        if ($key === 'shazzoo/contact-form') {
            $payload['resources'][] = $this->contactForms();
        }

        if ($key === 'shazzoo/employees') {
            $payload['resources'][] = $this->employees();
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function contactForms(): array
    {
        $modelClass = 'Shazzoo\\ContactForm\\Models\\ContactForm';
        $items = [];

        if (class_exists($modelClass) && Schema::hasTable('contact_forms')) {
            $items = $modelClass::query()
                ->select(['id', 'name', 'key', 'subject_prefix', 'button_label', 'success_message', 'privacy_note', 'fields'])
                ->orderBy('name')
                ->get()
                ->map(fn ($form): array => [
                    'id' => $form->id,
                    'name' => $form->name,
                    'key' => $form->key,
                    'subject_prefix' => $form->subject_prefix,
                    'button_label' => $form->button_label,
                    'success_message' => $form->success_message,
                    'privacy_note' => $form->privacy_note,
                    'fields' => $form->fields ?? [],
                ])
                ->all();
        }

        return [
            'key' => 'contact_forms',
            'label' => 'Contact forms',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'key', 'type' => 'text', 'required' => true],
                ['name' => 'subject_prefix', 'type' => 'text', 'required' => false],
                ['name' => 'button_label', 'type' => 'text', 'required' => false],
                ['name' => 'success_message', 'type' => 'textarea', 'required' => false],
                ['name' => 'privacy_note', 'type' => 'textarea', 'required' => false],
                ['name' => 'fields', 'type' => 'repeater', 'required' => true],
            ],
            'items_count' => count($items),
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function employees(): array
    {
        $modelClass = 'Shazzoo\\Employees\\Models\\Employee';
        $items = [];

        if (class_exists($modelClass) && Schema::hasTable('content_studio_employees')) {
            $items = $modelClass::query()
                ->select(['id', 'image_id', 'name', 'role', 'skills'])
                ->with('image')
                ->orderBy('name')
                ->get()
                ->map(fn ($employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'role' => $employee->role,
                    'skills' => $employee->skills ?? [],
                    'image' => $employee->image ? [
                        'id' => $employee->image->getKey(),
                        'url' => $employee->image->url,
                        'alt' => $employee->image->alt,
                    ] : null,
                ])
                ->all();
        }

        return [
            'key' => 'employees',
            'label' => 'Employees',
            'fields' => [
                ['name' => 'image_id', 'type' => 'media', 'required' => true],
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'role', 'type' => 'text', 'required' => true],
                ['name' => 'skills', 'type' => 'tags', 'required' => true],
            ],
            'items_count' => count($items),
            'items' => $items,
        ];
    }
}
