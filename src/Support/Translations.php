<?php

namespace Shazzoo\ContentCatalogApi\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Pages and plugin records that exist per language are "the same page" in
 * another language when they share a translation_key. That link drives the
 * language switch, the hreflang tags and the alternates in the sitemap.
 */
final class Translations
{
    /**
     * The languages switched on in the global settings.
     *
     * @return array<int, string>
     */
    public function activeLocales(): array
    {
        return array_keys(cms_locale_options());
    }

    /**
     * Checks that linking a record in $locale to the group of $translationKey
     * keeps one record per language: the target must be in another language,
     * and its group must not already have a record in this one.
     *
     * @param  class-string<Model>  $model
     */
    public function validate(Validator $validator, string $attribute, string $model, ?string $translationKey, ?string $locale, ?Model $record): void
    {
        if (blank($translationKey) || blank($locale)) {
            return;
        }

        $taken = $model::query()
            ->where('translation_key', $translationKey)
            ->where('locale', $locale)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();

        if ($taken) {
            $validator->errors()->add($attribute, "The linked group already has a version in [{$locale}].");
        }
    }

    /**
     * The translation key of the record to link to, or an error when it is in
     * the same language.
     *
     * @param  class-string<Model>  $model
     */
    public function keyOf(Validator $validator, string $model, mixed $id, ?string $locale): ?string
    {
        $target = $model::query()->find($id);

        if (! $target) {
            $validator->errors()->add('translation_of', 'The record to link to does not exist.');

            return null;
        }

        if ($target->getAttribute('locale') === $locale) {
            $validator->errors()->add('translation_of', 'The record to link to is in the same language.');

            return null;
        }

        return $target->getAttribute('translation_key');
    }

    /**
     * The other language versions of a record.
     *
     * @return array<int, array<string, mixed>>
     */
    public function of(Model $record, string $titleAttribute): array
    {
        $key = $record->getAttribute('translation_key');

        if (blank($key)) {
            return [];
        }

        return $record->newQuery()
            ->where('translation_key', $key)
            ->whereKeyNot($record->getKey())
            ->orderBy('locale')
            ->get()
            ->map(fn (Model $translation): array => array_filter([
                'id' => $translation->getKey(),
                'locale' => $translation->getAttribute('locale'),
                'slug' => $translation->getAttribute('slug'),
                'title' => $translation->getAttribute($titleAttribute),
            ], fn ($value): bool => $value !== null))
            ->values()
            ->all();
    }
}
