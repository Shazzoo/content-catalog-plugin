<?php

namespace Shazzoo\ContentCatalogApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Shazzoo\ContentCatalogApi\Support\BlockValidator;
use Shazzoo\ContentCatalogApi\Support\TemplateSettingsValidator;
use Shazzoo\ContentCatalogApi\Support\Translations;
use Shazzoo\ContentStudioCore\Models\Page;

abstract class PageWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fullPayload = $this->isMethod('post') || $this->isMethod('put');
        $required = $fullPayload ? 'required' : 'sometimes';
        $pageId = $this->route('page');

        return [
            'title' => [$required, 'string', 'max:255'],
            // Segments of letters, digits, dashes and underscores; a slash nests
            // a page under another path, like products/core-cms.
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+(\/[A-Za-z0-9_-]+)*$/', Rule::unique((new Page)->getTable(), 'slug')
                ->where(fn ($query) => $query->where('locale', $this->input('locale', $this->routePage()?->locale ?? config('app.locale'))))
                ->ignore($pageId)],
            'translation_key' => ['sometimes', 'nullable', 'uuid'],
            'translation_of' => ['sometimes', 'nullable', 'integer', 'prohibits:translation_key'],
            'locale' => [$required, 'string', Rule::in(app(Translations::class)->activeLocales())],
            'is_active' => ['sometimes', 'boolean'],
            'template_key' => ['sometimes', 'nullable', 'max:255', ...app(TemplateSettingsValidator::class)->keyRules()],
            'template_settings' => ['sometimes', 'array'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string'],
            'seo' => ['sometimes', 'nullable', 'array'],
            'header' => ['sometimes', 'nullable', 'array'],
            ...app(BlockValidator::class)->rules('blocks', $required),
            'if_updated_at' => ['sometimes', 'nullable', 'date'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $page = $this->routePage();
            $slug = $this->input('slug', $page?->slug);
            $locale = $this->input('locale', $page?->locale);

            if ($page !== null && is_string($slug) && is_string($locale)) {
                $duplicateExists = Page::query()
                    ->where('slug', $slug)
                    ->where('locale', $locale)
                    ->whereKeyNot($page->getKey())
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add('slug', 'The slug has already been taken for this locale.');
                }
            }

            // One page per language in a group of translations.
            $translations = app(Translations::class);
            $translationKey = filled($this->input('translation_of'))
                ? $translations->keyOf($validator, Page::class, $this->input('translation_of'), $locale)
                : ($this->exists('translation_key') ? $this->input('translation_key') : $page?->translation_key);

            $translations->validate($validator, filled($this->input('translation_of')) ? 'translation_of' : 'translation_key', Page::class, $translationKey, $locale, $page);

            app(TemplateSettingsValidator::class)->validate(
                $validator,
                'template_settings',
                // A page without a template renders with "default".
                $this->input('template_key', $page?->template_key) ?: 'default',
                $this->input('template_settings'),
            );

            app(BlockValidator::class)->validate($validator, 'blocks', $this->input('blocks'));
        }];
    }

    private function routePage(): ?Page
    {
        $page = $this->route('page');

        return $page instanceof Page ? $page : Page::query()->find($page);
    }
}
