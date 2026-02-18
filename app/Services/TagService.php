<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class TagService
{
    /**
     * Get all tags for a tenant with meal counts.
     * BR-252: Tags are tenant-scoped.
     *
     * @return Collection<int, Tag>
     */
    public function getTagsForTenant(Tenant $tenant): Collection
    {
        return Tag::forTenant($tenant->id)
            ->withCount('meals')
            ->orderBy(localized('name'))
            ->get();
    }

    /**
     * Create a new tag for a tenant.
     *
     * BR-253: Tag name required in both EN and FR
     * BR-254: Tag name unique within tenant per language
     * BR-258: Tag CRUD operations are logged via Spatie Activitylog
     * BR-260: Case-insensitive uniqueness
     *
     * @param  array{name_en: string, name_fr: string}  $data
     * @return array{success: bool, tag?: Tag, error?: string, field?: string}
     */
    public function createTag(Tenant $tenant, array $data): array
    {
        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);

        // BR-260: Case-insensitive uniqueness check
        $uniquenessCheck = $this->checkNameUniqueness($tenant, $nameEn, $nameFr);
        if (! $uniquenessCheck['unique']) {
            return [
                'success' => false,
                'error' => $uniquenessCheck['error'],
                'field' => $uniquenessCheck['field'],
            ];
        }

        $tag = Tag::create([
            'tenant_id' => $tenant->id,
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
        ]);

        return [
            'success' => true,
            'tag' => $tag,
        ];
    }

    /**
     * Update an existing tag.
     *
     * BR-256: Editing a tag name updates it everywhere it is displayed
     * BR-260: Case-insensitive uniqueness (excluding current tag)
     *
     * @param  array{name_en: string, name_fr: string}  $data
     * @return array{success: bool, tag?: Tag, error?: string, field?: string}
     */
    public function updateTag(Tag $tag, array $data): array
    {
        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);

        $tenant = $tag->tenant;

        // BR-260: Case-insensitive uniqueness check (excluding current tag)
        $uniquenessCheck = $this->checkNameUniqueness($tenant, $nameEn, $nameFr, $tag->id);
        if (! $uniquenessCheck['unique']) {
            return [
                'success' => false,
                'error' => $uniquenessCheck['error'],
                'field' => $uniquenessCheck['field'],
            ];
        }

        $tag->update([
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
        ]);

        return [
            'success' => true,
            'tag' => $tag->fresh(),
        ];
    }

    /**
     * Delete a tag if it is not assigned to any meals.
     *
     * BR-255: Tags cannot be deleted if assigned to any meal
     *
     * @return array{success: bool, error?: string, entity_name?: string}
     */
    public function deleteTag(Tag $tag): array
    {
        $mealCount = $tag->meals()->count();

        if ($mealCount > 0) {
            return [
                'success' => false,
                'error' => __('Cannot delete — this tag is assigned to :count meals. Remove it from all meals first.', [
                    'count' => $mealCount,
                ]),
                'entity_name' => $tag->name_en,
            ];
        }

        $tagName = $tag->name_en;
        $tag->delete();

        return [
            'success' => true,
            'entity_name' => $tagName,
        ];
    }

    /**
     * Check name uniqueness within a tenant (case-insensitive).
     *
     * BR-254: Tag name must be unique within the tenant (per language)
     * BR-260: Case-insensitive uniqueness
     *
     * @return array{unique: bool, error?: string, field?: string}
     */
    private function checkNameUniqueness(Tenant $tenant, string $nameEn, string $nameFr, ?int $excludeId = null): array
    {
        // Check English name uniqueness (case-insensitive)
        $enQuery = Tag::forTenant($tenant->id)
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)]);
        if ($excludeId) {
            $enQuery->where('id', '!=', $excludeId);
        }
        if ($enQuery->exists()) {
            return [
                'unique' => false,
                'error' => __('A tag with this English name already exists.'),
                'field' => 'name_en',
            ];
        }

        // Check French name uniqueness (case-insensitive)
        $frQuery = Tag::forTenant($tenant->id)
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)]);
        if ($excludeId) {
            $frQuery->where('id', '!=', $excludeId);
        }
        if ($frQuery->exists()) {
            return [
                'unique' => false,
                'error' => __('A tag with this French name already exists.'),
                'field' => 'name_fr',
            ];
        }

        return ['unique' => true];
    }
}
