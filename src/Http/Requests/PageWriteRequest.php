<?php

namespace Shazzoo\ContentCatalogApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Shazzoo\ContentCatalogApi\Support\BlockValidator;
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
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique((new Page)->getTable(), 'slug')
                ->where(fn ($query) => $query->where('locale', $this->input('locale', $this->routePage()?->locale ?? config('app.locale'))))
                ->ignore($pageId)],
            'translation_key' => ['sometimes', 'nullable', 'uuid'],
            'locale' => [$required, 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
            'template_key' => ['sometimes', 'nullable', 'string', 'max:255'],
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

            app(BlockValidator::class)->validate($validator, 'blocks', $this->input('blocks'));
        }];
    }

    private function routePage(): ?Page
    {
        $page = $this->route('page');

        return $page instanceof Page ? $page : Page::query()->find($page);
    }
}
