<?php

namespace App\Traits;

use Illuminate\Support\Facades\App;

/**
 * Provides transparent access to localized database columns.
 *
 * Models using this trait can define translatable attributes. When accessing
 * `$model->name`, the trait resolves to `name_en` or `name_fr` based on
 * the current application locale, with English as the fallback.
 *
 * Usage:
 *   class Meal extends Model {
 *       use HasTranslatable;
 *       protected array $translatable = ['name', 'description'];
 *   }
 *
 *   $meal->name; // Returns name_fr when locale is 'fr', name_en otherwise
 */
trait HasTranslatable
{
    /**
     * Get a translatable attribute value for the current locale.
     *
     * Falls back to the default locale (English) if the localized value is empty.
     */
    public function getTranslatedAttribute(string $attribute): ?string
    {
        $locale = App::getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        $localizedValue = $this->attributes["{$attribute}_{$locale}"] ?? null;

        if ($localizedValue !== null && $localizedValue !== '') {
            return $localizedValue;
        }

        // Fallback to default locale
        return $this->attributes["{$attribute}_{$fallbackLocale}"] ?? null;
    }

    /**
     * Override getAttribute to intercept translatable attributes.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        if ($this->isTranslatableAttribute($key)) {
            return $this->getTranslatedAttribute($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Check if an attribute is declared as translatable.
     */
    protected function isTranslatableAttribute(string $key): bool
    {
        return property_exists($this, 'translatable')
            && in_array($key, $this->translatable, true);
    }

    /**
     * Get all translatable attributes defined on the model.
     *
     * @return array<string>
     */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    /**
     * Set a translatable attribute for a specific locale.
     */
    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $this->attributes["{$attribute}_{$locale}"] = $value;

        return $this;
    }

    /**
     * Get a translatable attribute for a specific locale.
     */
    public function getTranslation(string $attribute, ?string $locale = null): ?string
    {
        $locale = $locale ?? App::getLocale();

        return $this->attributes["{$attribute}_{$locale}"] ?? null;
    }
}
