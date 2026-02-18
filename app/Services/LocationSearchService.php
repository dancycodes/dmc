<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LocationSearchService
{
    /**
     * Nominatim API base URL.
     */
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * BR-318: Maximum 1 request per second (Nominatim usage policy).
     */
    private const RATE_LIMIT_KEY = 'nominatim-api';

    /**
     * BR-318: Rate limit — max requests per minute.
     */
    private const RATE_LIMIT_PER_MINUTE = 60;

    /**
     * Maximum results to return per query.
     */
    private const MAX_RESULTS = 5;

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT_SECONDS = 3;

    /**
     * Search for locations using the Nominatim API.
     *
     * BR-315: Queries scoped to Cameroon (countrycodes=cm)
     * BR-318: Includes valid User-Agent, respects rate limits
     * BR-323: Graceful degradation on API failure
     *
     * @return array{success: bool, results: array, error: string}
     */
    public function search(string $query): array
    {
        $query = trim($query);

        // BR-316: Minimum 3 characters
        if (mb_strlen($query) < 3) {
            return [
                'success' => true,
                'results' => [],
                'error' => '',
            ];
        }

        // BR-318: Check rate limit
        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::RATE_LIMIT_PER_MINUTE)) {
            return [
                'success' => false,
                'results' => [],
                'error' => __('Too many search requests. Please try again in a moment.'),
            ];
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, 60);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => 'DancyMeals/1.0 (food-marketplace; contact@dancymeals.com)',
                    'Accept-Language' => app()->getLocale(),
                ])
                ->get(self::NOMINATIM_URL, [
                    'q' => $query,
                    'countrycodes' => 'cm',
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => self::MAX_RESULTS,
                ]);

            if (! $response->successful()) {
                Log::warning('Nominatim API returned non-success status', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [
                    'success' => false,
                    'results' => [],
                    'error' => __('Unable to search locations. Please type your address manually.'),
                ];
            }

            $data = $response->json();

            if (! is_array($data)) {
                return [
                    'success' => true,
                    'results' => [],
                    'error' => '',
                ];
            }

            $results = $this->formatResults($data);

            return [
                'success' => true,
                'results' => $results,
                'error' => '',
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Nominatim API connection failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'results' => [],
                'error' => __('Unable to search locations. Please type your address manually.'),
            ];
        } catch (\Exception $e) {
            Log::error('Nominatim API unexpected error', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'results' => [],
                'error' => __('Unable to search locations. Please type your address manually.'),
            ];
        }
    }

    /**
     * Format raw Nominatim results into a consistent structure.
     *
     * Each result contains:
     * - display_name: Full formatted name
     * - name: Primary location name
     * - area: Area/city from address details
     * - country: Always "Cameroon"
     * - lat/lon: Coordinates (for potential future use)
     *
     * @param  array<int, array>  $rawResults
     * @return array<int, array{display_name: string, name: string, area: string, country: string, lat: string, lon: string}>
     */
    private function formatResults(array $rawResults): array
    {
        return collect($rawResults)
            ->map(function (array $item) {
                $address = $item['address'] ?? [];

                // Build the primary name from the most specific address component
                $name = $this->extractPrimaryName($item, $address);

                // Build the area from city/town/state
                $area = $this->extractArea($address);

                return [
                    'display_name' => $item['display_name'] ?? $name,
                    'name' => $name,
                    'area' => $area,
                    'country' => $address['country'] ?? 'Cameroon',
                    'lat' => $item['lat'] ?? '',
                    'lon' => $item['lon'] ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Extract the primary location name from the result.
     */
    private function extractPrimaryName(array $item, array $address): string
    {
        // Use the name field if available, otherwise derive from address
        if (! empty($item['name'])) {
            return $item['name'];
        }

        // Try address components from most to least specific
        $candidates = [
            'neighbourhood',
            'suburb',
            'quarter',
            'hamlet',
            'village',
            'town',
            'city',
            'county',
        ];

        foreach ($candidates as $key) {
            if (! empty($address[$key])) {
                return $address[$key];
            }
        }

        return $item['display_name'] ?? __('Unknown location');
    }

    /**
     * Extract the area (city/region) from address details.
     */
    private function extractArea(array $address): string
    {
        $parts = [];

        // City or town
        $cityKeys = ['city', 'town', 'village', 'county'];
        foreach ($cityKeys as $key) {
            if (! empty($address[$key])) {
                $parts[] = $address[$key];
                break;
            }
        }

        // State or region
        if (! empty($address['state'])) {
            $parts[] = $address['state'];
        }

        return implode(', ', $parts);
    }
}
