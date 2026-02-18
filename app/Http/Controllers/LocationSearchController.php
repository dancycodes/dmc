<?php

namespace App\Http\Controllers;

use App\Services\LocationSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    /**
     * Search for locations using OpenStreetMap Nominatim.
     *
     * F-097: OpenStreetMap Neighbourhood Search
     * BR-315: Queries scoped to Cameroon
     * BR-316: Autocomplete triggers after 3+ characters
     * BR-322: API requests are made server-side to avoid CORS and control rate limiting
     *
     * Returns JSON (not Gale SSE) because this endpoint is called
     * programmatically by Alpine.js fetch() from the component.
     */
    public function search(Request $request, LocationSearchService $service): JsonResponse
    {
        $query = $request->input('q', '');

        // BR-316: Minimum 3 characters
        if (mb_strlen(trim($query)) < 3) {
            return response()->json([
                'success' => true,
                'results' => [],
                'error' => '',
            ]);
        }

        $result = $service->search($query);

        return response()->json($result);
    }
}
