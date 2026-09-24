<?php

namespace Shazzoo\ContentCatalogApi\Support\Resources;

/**
 * Resource declarations for plugins released before "api_resources" existed.
 * A plugin that declares its own resources in plugin.json replaces these.
 */
final class BuiltInResources
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(string $pluginKey): array
    {
        return match ($pluginKey) {
            'shazzoo/contact-form' => [[
                'key' => 'contact_forms',
                'label' => 'Contact forms',
                'model' => 'Shazzoo\\ContactForm\\Models\\ContactForm',
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'required' => true],
                    ['name' => 'key', 'type' => 'text', 'required' => true, 'unique' => true],
                    ['name' => 'subject_prefix', 'type' => 'text'],
                    ['name' => 'button_label', 'type' => 'text'],
                    ['name' => 'success_message', 'type' => 'textarea'],
                    ['name' => 'privacy_note', 'type' => 'textarea'],
                    ['name' => 'fields', 'type' => 'repeater', 'required' => true],
                ],
            ]],
            'shazzoo/employees' => [[
                'key' => 'employees',
                'label' => 'Employees',
                'model' => 'Shazzoo\\Employees\\Models\\Employee',
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'image_id', 'type' => 'media', 'required' => true],
                    ['name' => 'name', 'type' => 'text', 'required' => true],
                    ['name' => 'role', 'type' => 'text', 'required' => true],
                    ['name' => 'skills', 'type' => 'tags', 'required' => true],
                ],
            ]],
            'shazzoo/strategy-engine-plugin' => [[
                'key' => 'settings',
                'label' => 'Article settings',
                'model' => 'Shazzoo\\StrategyEngine\\Models\\ContentStudioSetting',
                // One row holds all settings; it is edited, never added.
                'creatable' => false,
                'fields' => [
                    ['name' => 'index_template_key', 'type' => 'template'],
                    ['name' => 'index_template_settings', 'type' => 'template_settings', 'template_from' => 'index_template_key'],
                    ['name' => 'article_template_key', 'type' => 'template'],
                    ['name' => 'article_template_settings', 'type' => 'template_settings', 'template_from' => 'article_template_key'],
                ],
            ]],
            default => [],
        };
    }
}
