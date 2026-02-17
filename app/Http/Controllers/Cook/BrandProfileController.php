<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Cook\UpdateBrandProfileRequest;
use Illuminate\Http\Request;

class BrandProfileController extends Controller
{
    /**
     * Display the cook's brand profile view.
     *
     * F-079: Cook Brand Profile View
     * BR-180: Profile view displays data in the user's current locale (en or fr)
     * BR-181: All profile sections (name, bio, images, contact, social) are shown in a single view
     * BR-182: Each section has an "Edit" link that navigates to the corresponding edit feature
     * BR-183: Cover images display as a carousel identical to how they appear on the public site
     * BR-184: Social links display as platform icons; clicking opens the link in a new tab
     * BR-185: "Edit" links are only shown to users with profile edit permission
     * BR-186: WhatsApp number displays with a WhatsApp icon and links to wa.me/{number}
     */
    public function show(Request $request): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // Get cover images from Spatie Media Library (F-073)
        $coverImages = $tenant->getMedia('cover-images')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl('carousel'),
                'thumbnail' => $media->getUrl('thumbnail'),
                'alt' => $media->name,
            ];
        });

        // BR-185: Check if user has edit permission
        $canEdit = $user->can('can-manage-brand');

        return gale()->view('cook.profile.show', [
            'tenant' => $tenant,
            'coverImages' => $coverImages,
            'canEdit' => $canEdit,
        ], web: true);
    }

    /**
     * Display the cook's brand profile edit form.
     *
     * F-080: Cook Brand Profile Edit
     * BR-195: Only users with profile edit permission for this tenant can access.
     * BR-196: All form labels and messages must use __() localization.
     */
    public function edit(Request $request): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-195: Permission check
        if (! $user->can('can-manage-brand')) {
            abort(403);
        }

        return gale()->view('cook.profile.edit', [
            'tenant' => $tenant,
        ], web: true);
    }

    /**
     * Update the cook's brand profile.
     *
     * F-080: Cook Brand Profile Edit
     * BR-187: Brand name required in both EN and FR; max 100 chars each.
     * BR-188: If bio provided in one language, it must be provided in both; max 1000 chars.
     * BR-189: WhatsApp required; valid Cameroon format (+237).
     * BR-190: Phone optional; valid Cameroon format if provided.
     * BR-191: Social links optional; must be valid URLs if provided.
     * BR-192: All changes saved via Gale (no page reload).
     * BR-193: Success feedback via toast notification.
     * BR-194: All profile changes logged via Spatie Activitylog (old and new values).
     * BR-195: Only users with profile edit permission can submit.
     */
    public function update(Request $request): mixed
    {
        $tenant = tenant();

        // BR-195: Permission check
        if (! $request->user()->can('can-manage-brand')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $this->validateGaleState($request);

            // Check for paired bio validation error
            $bioError = $this->checkPairedBioValidation($validated);
            if ($bioError !== null) {
                return $bioError;
            }
        } else {
            $formRequest = app(UpdateBrandProfileRequest::class);
            $validated = $formRequest->validated();
        }

        // BR-194: Track old values for activity logging
        $oldValues = $tenant->only([
            'name_en', 'name_fr', 'description_en', 'description_fr',
            'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok',
        ]);

        // Update tenant brand profile
        $tenant->update([
            'name_en' => $validated['name_en'],
            'name_fr' => $validated['name_fr'],
            'description_en' => $validated['description_en'] ?? null,
            'description_fr' => $validated['description_fr'] ?? null,
            'whatsapp' => $validated['whatsapp'],
            'phone' => $validated['phone'] ?? null,
            'social_facebook' => $validated['social_facebook'] ?? null,
            'social_instagram' => $validated['social_instagram'] ?? null,
            'social_tiktok' => $validated['social_tiktok'] ?? null,
        ]);

        // BR-194: Activity logging with old/new comparison
        $this->logProfileChanges($request, $tenant, $oldValues);

        // BR-192 & BR-193: Gale redirect with toast
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/profile'))
                ->with('success', __('Profile updated successfully.'));
        }

        return redirect()->route('cook.profile.show')
            ->with('success', __('Profile updated successfully.'));
    }

    /**
     * Validate Gale state with phone normalization and field cleaning.
     *
     * @return array<string, mixed>
     */
    private function validateGaleState(Request $request): array
    {
        // Normalize phone numbers in JSON body before validateState reads them
        $rawWhatsapp = (string) $request->state('whatsapp', '');
        $rawPhone = (string) $request->state('phone', '');

        if ($rawWhatsapp !== '') {
            $request->json()->set('whatsapp', RegisterRequest::normalizePhone($rawWhatsapp));
        }

        if ($rawPhone !== '') {
            $request->json()->set('phone', RegisterRequest::normalizePhone($rawPhone));
        }

        // Trim text fields in JSON body
        foreach (['name_en', 'name_fr', 'description_en', 'description_fr'] as $field) {
            $value = $request->state($field, '');
            if (is_string($value)) {
                $request->json()->set($field, trim($value));
            }
        }

        // Clean empty social links to null
        foreach (['social_facebook', 'social_instagram', 'social_tiktok'] as $field) {
            $value = $request->state($field, '');
            if ($value === '' || $value === null) {
                $request->json()->set($field, null);
            }
        }

        // Clean empty optional phone to null
        if ($rawPhone === '' || $rawPhone === null) {
            $request->json()->set('phone', null);
        }

        return $request->validateState([
            'name_en' => ['required', 'string', 'max:100'],
            'name_fr' => ['required', 'string', 'max:100'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_fr' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['required', 'string', 'regex:'.UpdateBrandProfileRequest::CAMEROON_PHONE_REGEX],
            'phone' => ['nullable', 'string', 'regex:'.UpdateBrandProfileRequest::CAMEROON_PHONE_REGEX],
            'social_facebook' => ['nullable', 'string', 'url', 'max:500'],
            'social_instagram' => ['nullable', 'string', 'url', 'max:500'],
            'social_tiktok' => ['nullable', 'string', 'url', 'max:500'],
        ], [
            'name_en.required' => __('Brand name is required in English.'),
            'name_en.max' => __('Brand name must not exceed 100 characters.'),
            'name_fr.required' => __('Brand name is required in French.'),
            'name_fr.max' => __('Brand name must not exceed 100 characters.'),
            'description_en.max' => __('Bio must not exceed 1000 characters.'),
            'description_fr.max' => __('Bio must not exceed 1000 characters.'),
            'whatsapp.required' => __('WhatsApp number is required.'),
            'whatsapp.regex' => __('Please enter a valid Cameroon phone number.'),
            'phone.regex' => __('Please enter a valid Cameroon phone number.'),
            'social_facebook.url' => __('Please enter a valid URL.'),
            'social_instagram.url' => __('Please enter a valid URL.'),
            'social_tiktok.url' => __('Please enter a valid URL.'),
        ]);
    }

    /**
     * Check paired bio validation (BR-188).
     *
     * @param  array<string, mixed>  $validated
     */
    private function checkPairedBioValidation(array $validated): mixed
    {
        $descEn = $validated['description_en'] ?? '';
        $descFr = $validated['description_fr'] ?? '';
        $hasEn = ! empty($descEn) && trim($descEn) !== '';
        $hasFr = ! empty($descFr) && trim($descFr) !== '';

        if ($hasEn && ! $hasFr) {
            return gale()->messages([
                'description_fr' => __('Bio is required in French when provided in English.'),
            ]);
        }

        if ($hasFr && ! $hasEn) {
            return gale()->messages([
                'description_en' => __('Bio is required in English when provided in French.'),
            ]);
        }

        return null;
    }

    /**
     * Log profile changes with old/new value tracking (BR-194).
     *
     * @param  array<string, mixed>  $oldValues
     */
    private function logProfileChanges(Request $request, \App\Models\Tenant $tenant, array $oldValues): void
    {
        $newValues = $tenant->only([
            'name_en', 'name_fr', 'description_en', 'description_fr',
            'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok',
        ]);

        $changes = array_filter($newValues, function ($value, $key) use ($oldValues) {
            return ($oldValues[$key] ?? null) !== $value;
        }, ARRAY_FILTER_USE_BOTH);

        if (! empty($changes)) {
            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties([
                    'action' => 'brand_profile_updated',
                    'old' => array_intersect_key($oldValues, $changes),
                    'new' => $changes,
                ])
                ->log('Brand profile updated');
        }
    }
}
