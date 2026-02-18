<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreTagRequest;
use App\Http\Requests\Cook\UpdateTagRequest;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Display the tag management page.
     *
     * F-115: Cook Tag Management
     * BR-257: Only users with manage-meals permission can manage tags
     */
    public function index(Request $request, TagService $tagService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();
        $tags = $tagService->getTagsForTenant($tenant);

        return gale()->view('cook.tags.index', [
            'tags' => $tags,
        ], web: true);
    }

    /**
     * Store a new tag.
     *
     * F-115: Cook Tag Management — Scenario 1: Create a tag
     * BR-252: Tags are tenant-scoped
     * BR-253: Tag name required in both EN and FR
     * BR-254: Tag name unique within tenant per language
     * BR-258: Tag CRUD operations are logged
     * BR-259: Tag name max length: 50 characters
     * BR-260: Case-insensitive uniqueness
     */
    public function store(Request $request, TagService $tagService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name_en' => ['required', 'string', 'max:'.Tag::NAME_MAX_LENGTH],
                'name_fr' => ['required', 'string', 'max:'.Tag::NAME_MAX_LENGTH],
            ]);
        } else {
            $validated = app(StoreTagRequest::class)->validated();
        }

        $result = $tagService->createTag($tenant, $validated);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    $result['field'] => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors([$result['field'] => $result['error']])->withInput();
        }

        // BR-258: Activity logging handled by LogsActivityTrait on Tag model

        if ($request->isGale()) {
            return gale()->redirect(url('/dashboard/tags'))->back()
                ->with('toast', ['type' => 'success', 'message' => __('Tag created.')]);
        }

        return redirect()->route('cook.tags.index')
            ->with('toast', ['type' => 'success', 'message' => __('Tag created.')]);
    }

    /**
     * Update an existing tag.
     *
     * F-115: Cook Tag Management — Scenario 2: Edit a tag
     * BR-256: Editing a tag name updates it everywhere it is displayed
     * BR-258: Tag CRUD operations are logged
     * BR-260: Case-insensitive uniqueness
     */
    public function update(Request $request, Tag $tag, TagService $tagService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();

        // Verify tag belongs to this tenant
        if ($tag->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Dual Gale/HTTP validation pattern
        // Edit form sends edit_ prefixed state keys to avoid conflicts with add form
        if ($request->isGale()) {
            $validated = $request->validateState([
                'edit_name_en' => ['required', 'string', 'max:'.Tag::NAME_MAX_LENGTH],
                'edit_name_fr' => ['required', 'string', 'max:'.Tag::NAME_MAX_LENGTH],
            ], [
                'edit_name_en.required' => __('The English name is required.'),
                'edit_name_en.max' => __('The English name must not exceed :max characters.', ['max' => Tag::NAME_MAX_LENGTH]),
                'edit_name_fr.required' => __('The French name is required.'),
                'edit_name_fr.max' => __('The French name must not exceed :max characters.', ['max' => Tag::NAME_MAX_LENGTH]),
            ]);
        } else {
            $formValidated = app(UpdateTagRequest::class)->validated();
            // Map standard keys to edit_ prefix for HTTP path
            $validated = [
                'edit_name_en' => $formValidated['name_en'],
                'edit_name_fr' => $formValidated['name_fr'],
            ];
        }

        // Map edit_ prefixed keys to service-expected keys
        $serviceData = [
            'name_en' => $validated['edit_name_en'],
            'name_fr' => $validated['edit_name_fr'],
        ];

        $result = $tagService->updateTag($tag, $serviceData);

        if (! $result['success']) {
            if ($request->isGale()) {
                // Map service field names back to edit_ prefix for Gale error messages
                $errorField = $result['field'] === 'name_en' ? 'edit_name_en' : 'edit_name_fr';

                return gale()->messages([
                    $errorField => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors([$result['field'] => $result['error']])->withInput();
        }

        if ($request->isGale()) {
            return gale()->redirect(url('/dashboard/tags'))->back()
                ->with('toast', ['type' => 'success', 'message' => __('Tag updated.')]);
        }

        return redirect()->route('cook.tags.index')
            ->with('toast', ['type' => 'success', 'message' => __('Tag updated.')]);
    }

    /**
     * Delete a tag.
     *
     * F-115: Cook Tag Management — Scenario 3 & 4: Delete tag
     * BR-255: Tags cannot be deleted if assigned to any meal
     * BR-258: Tag CRUD operations are logged
     */
    public function destroy(Request $request, Tag $tag, TagService $tagService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();

        // Verify tag belongs to this tenant
        if ($tag->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $tagService->deleteTag($tag);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->redirect(url('/dashboard/tags'))->back()
                    ->with('toast', ['type' => 'error', 'message' => $result['error']]);
            }

            return redirect()->route('cook.tags.index')
                ->with('toast', ['type' => 'error', 'message' => $result['error']]);
        }

        if ($request->isGale()) {
            return gale()->redirect(url('/dashboard/tags'))->back()
                ->with('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);
        }

        return redirect()->route('cook.tags.index')
            ->with('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);
    }
}
