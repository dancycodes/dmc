<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
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
}
