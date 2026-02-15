<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private PushNotificationService $pushService,
    ) {}

    /**
     * Store or update a push subscription for the authenticated user.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $this->pushService->storeSubscription(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => __('Push subscription stored successfully.'),
        ]);
    }

    /**
     * Delete a push subscription for the authenticated user.
     */
    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');

        if (empty($endpoint)) {
            return response()->json([
                'message' => __('Endpoint is required.'),
            ], 422);
        }

        $this->pushService->deleteSubscription($request->user(), $endpoint);

        return response()->json([
            'message' => __('Push subscription removed successfully.'),
        ]);
    }
}
